"""
OMR Scanner — clean rewrite based on the actual ballot structure.

BALLOT LAYOUT (from generated PDF):
- 8 positions, each with 2 candidates (FORWARD then UNITY)
- Candidates stack vertically within each position block
- Each position block has a shaded header row + candidate rows
- Bubbles are in the leftmost ~8% of the page width
- The ballot has 4 corner anchor squares (solid black squares, ~6mm)
- A tear-line separates the ballot from the voter stub

APPROACH:
1. Preprocess: deskew + normalize lighting (CLAHE)
2. Detect the ballot page boundary via contour or anchor squares
3. For each bubble slot, compute a clean "fill score" using two signals:
   a. Mean darkness inside the bubble circle (inverted grayscale)
   b. Ratio of dark pixels exceeding an adaptive threshold
4. Per-position winner selection with a clear confidence gap requirement
5. Multi-vote positions use a ranked threshold approach
"""

import base64
import time
from typing import Dict, List, Optional, Tuple

import cv2
import numpy as np

from models import BubbleCandidate, DetectedVote, ScanResponse


# ---------------------------------------------------------------------------
# Constants
# ---------------------------------------------------------------------------

# Fraction of image width for the bubble column
# Bubbles sit at ~3.5% of ballot width — keep lane narrow to avoid text
BUBBLE_LANE_LEFT_FRAC  = 0.00
BUBBLE_LANE_RIGHT_FRAC = 0.09   # was 0.10 — too wide, caught position headers

# After _warp_to_ballot the ballot fills the frame — skip the very top
# (ballot-number row + election header block) and the very bottom (tear line)
# These are FRACTIONS of ballot height — works at any resolution after warp
SCAN_TOP_FRAC    = 0.06
SCAN_BOTTOM_FRAC = 0.97

# Additional top skip expressed as a FRACTION of ballot height (not fixed px)
# The ballot header (number + election name + instructions) is ~12% of height
# SCAN_TOP_FRAC already accounts for 6%, this adds another 6% = 12% total
SCAN_TOP_PAD_FRAC  = 0.165   # replaces SCAN_TOP_PAD_PX = 100 (unreliable fixed px)
SCAN_TOP_PAD_PX    = 0      # kept for backwards compat — set to 0, use frac instead
SCAN_BOTTOM_PAD_PX = 0

# Bubble radius: after warp the ballot fills the frame so calibrate against
# ballot height. A4 ballot: bubble ~3.5mm, page ~297mm → 3.5/297 ≈ 0.012
# We use min(h,w) so portrait and landscape both work
BUBBLE_RADIUS_FRAC = 0.012   # was 0.008 — too small after warp

# Fill threshold — a shaded bubble typically scores 0.25–0.60
FILL_THRESHOLD   = 0.22    # lowered from 0.28 — catches light pen marks
MIN_GAP_SINGLE   = 0.06    # lowered from 0.08 — less strict gap requirement
FILL_FLOOR_MULTI = 0.18    # lowered from 0.22


class BallotScanner:
    """Main scanner: load → preprocess → detect bubbles → return votes."""

    def __init__(self) -> None:
        self.image: Optional[np.ndarray] = None
        self.debug_bubbles: List[Dict] = []
        self.debug_visualization_image: Optional[str] = None
        self.processed_preview_image: Optional[str] = None

    # ------------------------------------------------------------------
    # Public API
    # ------------------------------------------------------------------

    def scan(
        self,
        image_base64: str,
        bubble_candidates: List[BubbleCandidate],
    ) -> ScanResponse:
        t0 = time.time()

        if not self._load_base64(image_base64):
            return self._error_response("Failed to load image", t0)

        if self.image is None or self.image.size == 0:
            return self._error_response("Empty image", t0)

        # 1. Preprocess
        self.image = self._preprocess(self.image)
        self.processed_preview_image = self._encode_jpg(self.image)

        # 2. Detect bubbles
        detected = self._detect_bubbles(bubble_candidates)

        # 3. Build debug overlay
        self.debug_visualization_image = self._build_debug_overlay(
            self.image, self.debug_bubbles
        )

        ms = (time.time() - t0) * 1000
        quality = self._image_quality(self.image)

        return ScanResponse(
            success=len(detected) > 0,
            message="Scan completed." if detected else "No marks detected.",
            detected_votes=detected,
            image_quality=quality,
            markers_detected=0,
            processing_time_ms=ms,
            errors=[],
            debug_bubbles=self.debug_bubbles,
            debug_visualization_image=self.debug_visualization_image,
            processed_preview_image=self.processed_preview_image,
        )

    # ------------------------------------------------------------------
    # Step 1: Image loading
    # ------------------------------------------------------------------

    def _load_base64(self, b64: str) -> bool:
        try:
            if "," in b64:
                b64 = b64.split(",", 1)[1]
            data = base64.b64decode(b64)
            arr = np.frombuffer(data, np.uint8)
            img = cv2.imdecode(arr, cv2.IMREAD_COLOR)
            if img is None or img.size == 0:
                return False
            self.image = img
            return True
        except Exception:
            return False

    # ------------------------------------------------------------------
    # Step 2: Preprocessing
    # ------------------------------------------------------------------

    def _preprocess(self, img: np.ndarray) -> np.ndarray:
        """
        1. Try to find the ballot page boundary and warp to it.
        2. Deskew using Hough lines.
        3. Enhance local contrast with CLAHE.
        """
        img = self._warp_to_ballot(img)
        img = self._deskew(img)
        img = self._clahe_enhance(img)
        return img

    def _warp_to_ballot(self, img: np.ndarray) -> np.ndarray:
        """
        Find the largest near-rectangular contour that could be the ballot
        page and warp to it. If nothing suitable is found, return unchanged.
        Handles both portrait (tall) and landscape ballot orientations.
        """
        h, w = img.shape[:2]
        area = h * w

        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
        blur = cv2.GaussianBlur(gray, (5, 5), 0)
        edges = cv2.Canny(blur, 50, 150)
        edges = cv2.dilate(edges, np.ones((3, 3), np.uint8), iterations=2)

        contours, _ = cv2.findContours(
            edges, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE
        )
        contours = sorted(contours, key=cv2.contourArea, reverse=True)[:8]

        for cnt in contours:
            cnt_area = cv2.contourArea(cnt)
            if cnt_area < area * 0.15:
                break

            peri = cv2.arcLength(cnt, True)
            approx = cv2.approxPolyDP(cnt, 0.02 * peri, True)
            if len(approx) != 4:
                continue

            pts = approx.reshape(4, 2).astype("float32")
            rect = self._order_points(pts)

            wa = float(np.linalg.norm(rect[1] - rect[0]))
            wb = float(np.linalg.norm(rect[2] - rect[3]))
            ha = float(np.linalg.norm(rect[3] - rect[0]))
            hb = float(np.linalg.norm(rect[2] - rect[1]))
            tw = int(max(wa, wb))
            th = int(max(ha, hb))

            if tw < w * 0.25 or th < h * 0.25:
                continue

            ar = tw / max(1, th)
            # Accept portrait (tall, ar < 1) and landscape (wide, ar > 1)
            # Ballot is A4-ish so portrait ar ~ 0.70, landscape ~ 1.41
            # Widen the gate to 0.20–2.0 to avoid rejecting slightly mis-detected corners
            if not (0.20 <= ar <= 2.0):
                continue

            dst = np.array(
                [[0, 0], [tw - 1, 0], [tw - 1, th - 1], [0, th - 1]],
                dtype="float32",
            )
            M = cv2.getPerspectiveTransform(rect, dst)
            warped = cv2.warpPerspective(img, M, (tw, th))
            return warped

        return img

    def _deskew(self, img: np.ndarray) -> np.ndarray:
        """
        Correct small rotation via Hough line analysis.

        Phone photos of portrait ballots produce mostly near-vertical edges
        (columns, ballot borders) — not near-horizontal ones. The original
        code only accepted abs(deg) <= 10 which filtered out all vertical
        lines (deg ~±80–90°) leaving angles[] empty → no correction applied.

        Fix: normalize every line angle to its deviation from the nearest
        axis (horizontal OR vertical), take the median deviation, then
        rotate by that small amount. Max correction ±15° from either axis.
        """
        h, w = img.shape[:2]
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
        edges = cv2.Canny(gray, 50, 150)
        lines = cv2.HoughLinesP(
            edges,
            1,
            np.pi / 180,
            threshold=80,
            minLineLength=int(min(w, h) * 0.20),  # accept shorter lines too
            maxLineGap=20,
        )
        if lines is None:
            return img

        deviations = []
        for line in lines[:300]:
            x1, y1, x2, y2 = line[0]
            dx, dy = float(x2 - x1), float(y2 - y1)
            if abs(dx) < 1 and abs(dy) < 1:
                continue

            # Angle from horizontal in degrees, range (-90, 90]
            deg = float(np.degrees(np.arctan2(dy, dx)))

            # Normalize to deviation from nearest axis:
            #   near-horizontal lines: dev = deg itself (small)
            #   near-vertical lines:   dev = deg - sign(deg)*90 (small)
            if abs(deg) <= 45.0:
                dev = deg           # deviation from horizontal
            else:
                dev = deg - (90.0 if deg > 0 else -90.0)  # deviation from vertical

            # Only accept deviations within ±15° (genuine skew, not random noise)
            if abs(dev) <= 15.0:
                deviations.append(dev)

        if not deviations:
            return img

        angle = float(np.median(deviations))

        # Skip trivially small corrections
        if abs(angle) < 0.3:
            return img

        M = cv2.getRotationMatrix2D((w / 2.0, h / 2.0), -angle, 1.0)
        return cv2.warpAffine(
            img, M, (w, h),
            flags=cv2.INTER_LINEAR,
            borderMode=cv2.BORDER_REPLICATE,
        )

    @staticmethod
    def _clahe_enhance(img: np.ndarray) -> np.ndarray:
        """Improve local contrast so faint pen marks become detectable."""
        lab = cv2.cvtColor(img, cv2.COLOR_BGR2LAB)
        l, a, b = cv2.split(lab)
        clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8))
        l = clahe.apply(l)
        return cv2.cvtColor(cv2.merge((l, a, b)), cv2.COLOR_LAB2BGR)

    @staticmethod
    def _order_points(pts: np.ndarray) -> np.ndarray:
        rect = np.zeros((4, 2), dtype="float32")
        s = pts.sum(axis=1)
        rect[0] = pts[np.argmin(s)]   # top-left
        rect[2] = pts[np.argmax(s)]   # bottom-right
        d = np.diff(pts, axis=1)
        rect[1] = pts[np.argmin(d)]   # top-right
        rect[3] = pts[np.argmax(d)]   # bottom-left
        return rect

    # ------------------------------------------------------------------
    # Step 3: Bubble detection
    # ------------------------------------------------------------------

    def _detect_bubbles(
        self, candidates: List[BubbleCandidate]
    ) -> List[DetectedVote]:
        """
        Core detection pipeline:
        1. Build grayscale + global adaptive threshold map.
        2. Compute bubble radius from image dimensions.
        3. Estimate X center of bubble lane via HoughCircles.
        4. Detect Y positions of printed bubble rings.
        5. Map candidates to pixel coordinates (proportional layout-aware).
        6. Score each bubble using global threshold map.
        7. Per-position winner selection.
        """
        if not candidates:
            return []

        h, w = self.image.shape[:2]
        gray = cv2.cvtColor(self.image, cv2.COLOR_BGR2GRAY)

        # Compute global adaptive threshold ONCE — used by all _fill_score calls.
        # Block size must be odd and large enough to cover a bubble + surroundings.
        bubble_r = max(8, int(min(h, w) * BUBBLE_RADIUS_FRAC))
        block = max(11, (bubble_r * 4) | 1)  # ensure odd
        global_thresh = cv2.adaptiveThreshold(
            gray, 255,
            cv2.ADAPTIVE_THRESH_GAUSSIAN_C,
            cv2.THRESH_BINARY_INV,
            block, 8,
        )

        # ---- locate the bubble lane X center ----
        lane_cx = self._estimate_lane_cx(gray, w, h, bubble_r)

        # ---- detect printed bubble rings in the left lane ----
        expected_slots = sum(
            1 for c in candidates if not getattr(c, "is_placeholder", False)
        )
        ring_ys = self._detect_ring_ys(
            gray, w, h, bubble_r, lane_cx, expected_slots
        )

        # ---- map candidates to pixel coords (proportional to candidate count) ----
        slot_measurements = self._assign_slots(
            candidates, ring_ys, h, w, bubble_r, lane_cx
        )

        # ---- score each slot with the global threshold map ----
        self.debug_bubbles = []
        for slot in slot_measurements:
            score = self._fill_score(
                gray, slot["cx"], slot["cy"], bubble_r, global_thresh
            )
            slot["fill_score"] = float(score)
            slot["threshold"]  = FILL_THRESHOLD
            self.debug_bubbles.append(dict(slot))

        return self._select_winners(slot_measurements)

    def _estimate_lane_cx(
        self, gray: np.ndarray, w: int, h: int, bubble_r: int
    ) -> int:
        """
        Find the X centre of the bubble column.

        HoughCircles only finds circular EDGES (rings). Filled/shaded bubbles
        have no strong circular edge — they're solid dark blobs. So we use
        column projection instead:

        1. Threshold to dark pixels in the left lane strip
        2. Sum dark pixels per column → find the column with the most ink
        3. Smooth and find the peak X

        Falls back to geometric default (3.5% of width) if nothing found.
        """
        default_cx = int(w * 0.07)
        lane_right = int(w * 0.12)

        # Only look in the scannable vertical range to avoid header/footer noise
        top    = int(h * (SCAN_TOP_FRAC + SCAN_TOP_PAD_FRAC))
        bottom = int(h * SCAN_BOTTOM_FRAC)
        if top >= bottom:
            top = int(h * SCAN_TOP_FRAC)
        strip  = gray[top:bottom, :lane_right]

        # Threshold: pixels darker than 160 are "ink"
        dark = (strip < 160).astype(np.float32)

        # Column sum = how much dark ink is in each vertical column
        col_sum = dark.sum(axis=0)
        col_dark_ratio = col_sum / max(1.0, float(strip.shape[0]))
        if col_sum.max() < 3:
            return default_cx

        # Smooth to suppress noise then find peak
        col_smooth = np.convolve(
            col_sum,
            np.ones(max(3, bubble_r)) / max(3, bubble_r),
            mode='same',
        )

        # Suppress columns that are likely just the page border (solid vertical lines)
        border_band = max(5, int(w * 0.05))
        for x in range(min(border_band, len(col_smooth))):
            col_smooth[x] *= 0.1

        for x in range(len(col_smooth)):
            if col_dark_ratio[x] >= 0.70:
                col_smooth[x] *= 0.25

        peak_x = int(np.argmax(col_smooth))
        peak_x = max(bubble_r, min(lane_right - bubble_r, peak_x))
        return peak_x

    def _detect_ring_ys(
        self,
        gray: np.ndarray,
        w: int,
        h: int,
        bubble_r: int,
        lane_cx: int,
        expected_count: Optional[int] = None,
    ) -> List[int]:
        """
        Find Y centres of all bubbles (empty OR filled) in the bubble column.

        Instead of HoughCircles (which only finds rings/edges), we use
        blob detection on the dark pixels in a narrow vertical strip
        centred on lane_cx. This finds both empty bubble outlines AND
        filled/shaded solid circles.

        Returns sorted deduplicated list of Y centres.
        """
        top       = int(h * (SCAN_TOP_FRAC + SCAN_TOP_PAD_FRAC))
        bottom    = int(h * SCAN_BOTTOM_FRAC)
        if top >= bottom:
            top = int(h * SCAN_TOP_FRAC)
        half_lane = max(bubble_r + 4, int(w * 0.025))

        x1 = max(0, lane_cx - half_lane)
        x2 = min(w, lane_cx + half_lane)

        strip = gray[top:bottom, x1:x2]

        blur = cv2.GaussianBlur(strip, (3, 3), 0)

        # Adaptive threshold — handles varying paper brightness
        block = max(9, (bubble_r * 2) | 1)
        thresh = cv2.adaptiveThreshold(
            blur, 255,
            cv2.ADAPTIVE_THRESH_GAUSSIAN_C,
            cv2.THRESH_BINARY_INV,
            block, 6,
        )

        # Morphological open + close to reduce text noise and merge bubble outlines
        k = max(3, bubble_r // 2)
        kernel = cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (k, k))
        opened = cv2.morphologyEx(thresh, cv2.MORPH_OPEN, kernel, iterations=1)
        closed = cv2.morphologyEx(opened, cv2.MORPH_CLOSE, kernel, iterations=1)

        # Find connected components — each bubble should be one blob
        n_labels, labels, stats, centroids = cv2.connectedComponentsWithStats(
            closed, connectivity=8
        )

        min_area = int(np.pi * (bubble_r * 0.4) ** 2)
        max_area = int(np.pi * (bubble_r * 2.5) ** 2)

        raw_ys: List[int] = []
        for i in range(1, n_labels):  # skip background label 0
            area = int(stats[i, cv2.CC_STAT_AREA])
            if not (min_area <= area <= max_area):
                continue
            bw = int(stats[i, cv2.CC_STAT_WIDTH])
            bh = int(stats[i, cv2.CC_STAT_HEIGHT])
            # Must be roughly round (not a horizontal line from borders)
            if bh == 0 or not (0.3 <= bw / bh <= 3.0):
                continue
            cy_rel = float(centroids[i][1])
            raw_ys.append(int(cy_rel) + top)

        # Fallback: HoughCircles on the strip if blob detection is empty
        if not raw_ys:
            circles = cv2.HoughCircles(
                blur,
                cv2.HOUGH_GRADIENT,
                dp=1.2,
                minDist=max(10, int(bubble_r * 1.6)),
                param1=80,
                param2=12,
                minRadius=max(3, int(bubble_r * 0.6)),
                maxRadius=max(5, int(bubble_r * 1.4)),
            )
            if circles is not None:
                for circ in circles[0, :]:
                    raw_ys.append(int(circ[1]) + top)

        # Deduplicate: merge detections within 1.5 × bubble diameter
        raw_ys.sort()
        merged: List[int] = []
        min_gap = int(bubble_r * 1.5)
        for y in raw_ys:
            if not merged or (y - merged[-1]) >= min_gap:
                merged.append(y)

        if expected_count and len(merged) > expected_count:
            merged = self._select_consistent_rings(merged, expected_count)

        return merged

    @staticmethod
    def _select_consistent_rings(ring_ys: List[int], count: int) -> List[int]:
        """
        Keep the most consistently spaced run of rings, dropping header noise.
        """
        if count <= 0 or len(ring_ys) <= count:
            return ring_ys

        gaps = np.diff(ring_ys)
        median_gap = float(np.median(gaps)) if gaps.size else 0.0
        if median_gap <= 0.0:
            return ring_ys[:count]

        best_i = 0
        best_score = float("inf")
        for i in range(0, len(ring_ys) - count + 1):
            window = ring_ys[i:i + count]
            window_gaps = np.diff(window)
            if window_gaps.size == 0:
                score = 0.0
            else:
                score = float(np.mean(np.abs(window_gaps - median_gap)))
            if score < best_score:
                best_score = score
                best_i = i

        return ring_ys[best_i:best_i + count]

    def _assign_slots(
        self,
        candidates: List[BubbleCandidate],
        ring_ys: List[int],
        h: int,
        w: int,
        bubble_r: int,
        lane_cx: int,
    ) -> List[Dict]:
        """
        Map each candidate to a concrete (cx, cy) pixel coordinate.

        GEOMETRY IS THE PRIMARY SOURCE — ring detection only fine-tunes.

        The ballot layout is deterministic: each position block has a header
        row plus one row per candidate. We compute exact Y positions from
        the proportional layout, then snap ±tight_tolerance to a detected
        ring only if one is very close. This means different shading patterns
        on different ballots never shift the ring assignments.

        Snapping tolerance = 25% of per-candidate row height (very tight).
        If no ring is within tolerance, the geometric Y is used as-is.
        """
        top    = int(h * (SCAN_TOP_FRAC + SCAN_TOP_PAD_FRAC))
        bottom = int(h * SCAN_BOTTOM_FRAC)
        if top >= bottom:
            top = int(h * SCAN_TOP_FRAC)
            bottom = int(h * SCAN_BOTTOM_FRAC)
        scan_height = bottom - top

        # Group by position row
        rows: Dict[int, List[BubbleCandidate]] = {}
        for c in candidates:
            rows.setdefault(c.row, []).append(c)
        sorted_rows = sorted(rows.keys())
        if not sorted_rows:
            return []

        # Count real (non-placeholder) candidates per position
        HEADER_UNITS = 1.0  # treat header as almost a full candidate for spacing purposes
        row_real_counts: Dict[int, int] = {}
        total_units = 0.0
        for row_idx in sorted_rows:
            real = [c for c in rows[row_idx] if not getattr(c, "is_placeholder", False)]
            row_real_counts[row_idx] = max(1, len(real))
            total_units += row_real_counts[row_idx] + HEADER_UNITS

        slots: List[Dict] = []
        cursor = float(top)

        for row_idx in sorted_rows:
            row_candidates = sorted(
                [c for c in rows[row_idx] if not getattr(c, "is_placeholder", False)],
                key=lambda c: c.col,
            )
            n_cands = row_real_counts[row_idx]

            # Proportional height for this position
            pos_units  = n_cands + HEADER_UNITS
            pos_height = scan_height * (pos_units / total_units)
            header_h   = pos_height * (HEADER_UNITS / pos_units)
            content_top = cursor + header_h
            content_h   = pos_height - header_h

            # Per-candidate step height — used for snap tolerance
            step_h = content_h / n_cands

            # Tight snap tolerance: only snap if ring is within 25% of step_h
            # This prevents rings from voted bubbles in adjacent rows from
            # stealing the assignment
            snap_tol = int(step_h * 0.25)

            for i, cand in enumerate(row_candidates):
                # GEOMETRIC Y — primary source, always computed
                geom_y = int(content_top + step_h * (i + 0.45))
                geom_y = int(np.clip(geom_y, bubble_r, h - bubble_r))

                # Fine-tune: find the nearest ring within tight tolerance
                best_ring_y = None
                best_dist   = snap_tol + 1
                for ry in ring_ys:
                    d = abs(ry - geom_y)
                    if d < best_dist:
                        best_dist   = d
                        best_ring_y = ry

                # Only snap if ring is genuinely close — otherwise trust geometry
                final_y = best_ring_y if best_ring_y is not None else geom_y
                final_y = int(np.clip(final_y, bubble_r, h - bubble_r))

                slots.append({
                    "position_id":         int(cand.position_id),
                    "candidate_id":        int(cand.candidate_id),
                    "candidate_name":      cand.candidate_name,
                    "candidate_party":     getattr(cand, "candidate_party", None),
                    "position_name":       getattr(cand, "position_name", None),
                    "position_vote_limit": max(1, int(getattr(cand, "position_vote_limit", 1) or 1)),
                    "row":     int(cand.row),
                    "col":     int(cand.col),
                    "cx":      lane_cx,
                    "cy":      final_y,
                    "geom_y":  geom_y,
                    "snapped": best_ring_y is not None,
                })

            cursor += pos_height

        return slots

    # ------------------------------------------------------------------
    # Step 4: Fill scoring
    # ------------------------------------------------------------------

    def _fill_score(
        self, gray: np.ndarray, cx: int, cy: int, r: int,
        global_thresh: Optional[np.ndarray] = None,
    ) -> float:
        """
        Compute a fill score in [0, 1] for a circular bubble region.

        Three signals combined:
          1. contrast:   how much darker is the bubble interior vs its surround
          2. dark_ratio: fraction of pixels below the global adaptive threshold
          3. mean_ink:   raw mean darkness inside the bubble

        Using a global adaptive threshold (computed once per image) is far
        more reliable than per-bubble Otsu — small ROIs on empty bubbles give
        Otsu arbitrary results because there's no bimodal distribution to find.
        """
        h, w = gray.shape[:2]
        r_inner = max(4, int(r * 0.78))
        r_outer = max(r_inner + 3, int(r * 1.40))

        x1 = max(0, cx - r_outer)
        x2 = min(w, cx + r_outer + 1)
        y1 = max(0, cy - r_outer)
        y2 = min(h, cy + r_outer + 1)

        if x2 <= x1 or y2 <= y1:
            return 0.0

        roi  = gray[y1:y2, x1:x2].astype(np.float32)
        rh, rw = roi.shape
        yy, xx = np.mgrid[:rh, :rw]
        cx_roi, cy_roi = cx - x1, cy - y1
        dist2 = (xx - cx_roi) ** 2 + (yy - cy_roi) ** 2

        inner_mask = dist2 <= r_inner ** 2
        ring_mask  = (dist2 > r_inner ** 2) & (dist2 <= r_outer ** 2)

        n_inner = int(inner_mask.sum())
        if n_inner == 0:
            return 0.0

        inner_ink = float((255.0 - roi[inner_mask]).mean() / 255.0)
        n_ring    = int(ring_mask.sum())
        bg_ink    = float((255.0 - roi[ring_mask]).mean() / 255.0) if n_ring > 0 else 0.0
        contrast  = max(0.0, inner_ink - bg_ink)

        # Dark pixel ratio using global adaptive threshold if available
        if global_thresh is not None:
            thresh_roi = global_thresh[y1:y2, x1:x2]
            dark_ratio = float(thresh_roi[inner_mask].sum()) / (255.0 * n_inner)
        else:
            # Fallback: pixels darker than mean − 1 std of the inner region
            inner_vals = roi[inner_mask]
            dark_cut   = max(0, float(inner_vals.mean()) - float(inner_vals.std()))
            dark_ratio = float((inner_vals < dark_cut).sum()) / n_inner

        # Weighted combination — contrast is the most reliable signal
        score = 0.50 * contrast + 0.35 * dark_ratio + 0.15 * inner_ink
        return float(np.clip(score, 0.0, 1.0))

    # ------------------------------------------------------------------
    # Step 5: Winner selection per position
    # ------------------------------------------------------------------

    def _select_winners(self, slots: List[Dict]) -> List[DetectedVote]:
        """
        For each position:
        - Single-winner: pick top candidate if score >= FILL_THRESHOLD AND
          it beats the runner-up by at least MIN_GAP_SINGLE.
        - Multi-winner: select candidates in descending score order that
          exceed FILL_FLOOR_MULTI, up to votes_allowed.
        """
        # Group by position
        by_pos: Dict[int, List[Dict]] = {}
        for s in slots:
            by_pos.setdefault(s["position_id"], []).append(s)

        detected: List[DetectedVote] = []

        for pid, pos_slots in by_pos.items():
            ranked = sorted(pos_slots, key=lambda s: s["fill_score"], reverse=True)
            votes_allowed = max(1, int(ranked[0]["position_vote_limit"]))

            if votes_allowed == 1:
                # Single winner
                top = ranked[0]
                runner = ranked[1] if len(ranked) > 1 else None
                gap = (
                    top["fill_score"] - runner["fill_score"]
                    if runner else 1.0
                )
                if top["fill_score"] >= FILL_THRESHOLD and gap >= MIN_GAP_SINGLE:
                    top["detected"] = True
                    top["detection_mode"] = "single_winner"
                    confidence = min(1.0, top["fill_score"])
                    detected.append(self._make_vote(top, confidence))
                else:
                    # Mark as not detected in debug
                    for s in pos_slots:
                        s.setdefault("detected", False)
                        s.setdefault("detection_mode", "ambiguous" if top["fill_score"] >= FILL_THRESHOLD else "below_threshold")
            else:
                # Multi-winner
                selected = 0
                for s in ranked:
                    if selected >= votes_allowed:
                        break
                    if s["fill_score"] >= FILL_FLOOR_MULTI:
                        s["detected"] = True
                        s["detection_mode"] = "multi_winner"
                        confidence = min(1.0, s["fill_score"])
                        detected.append(self._make_vote(s, confidence))
                        selected += 1

            # Ensure all slots have detected flag for debug output
            for s in pos_slots:
                s.setdefault("detected", False)
                s.setdefault("detection_mode", "not_selected")

        return detected

    @staticmethod
    def _make_vote(slot: Dict, confidence: float) -> DetectedVote:
        return DetectedVote(
            position_id=slot["position_id"],
            candidate_id=slot["candidate_id"],
            candidate_name=slot.get("candidate_name"),
            candidate_party=slot.get("candidate_party"),
            position_name=slot.get("position_name"),
            confidence=confidence,
            row=slot["row"],
            col=slot["col"],
        )

    # ------------------------------------------------------------------
    # Debug utilities
    # ------------------------------------------------------------------

    def _build_debug_overlay(
        self, img: np.ndarray, bubbles: List[Dict]
    ) -> Optional[str]:
        """Draw bubble locations and scores on a copy of the image."""
        try:
            canvas = img.copy()
            h, w = canvas.shape[:2]
            bubble_r = max(8, int(min(h, w) * BUBBLE_RADIUS_FRAC))

            for b in bubbles:
                cx, cy = int(b.get("cx", 0)), int(b.get("cy", 0))
                score = float(b.get("fill_score", 0.0))
                detected = bool(b.get("detected", False))

                color = (0, 200, 60) if detected else (0, 120, 255)
                cv2.circle(canvas, (cx, cy), bubble_r, color, 2)
                cv2.putText(
                    canvas,
                    f"{score:.2f}",
                    (cx + bubble_r + 2, cy + 4),
                    cv2.FONT_HERSHEY_SIMPLEX,
                    0.38,
                    color,
                    1,
                    cv2.LINE_AA,
                )

            ok, buf = cv2.imencode(
                ".jpg", canvas, [int(cv2.IMWRITE_JPEG_QUALITY), 88]
            )
            if not ok:
                return None
            return "data:image/jpeg;base64," + base64.b64encode(buf).decode()
        except Exception:
            return None

    @staticmethod
    def _encode_jpg(img: np.ndarray, quality: int = 88) -> Optional[str]:
        ok, buf = cv2.imencode(".jpg", img, [int(cv2.IMWRITE_JPEG_QUALITY), quality])
        if not ok:
            return None
        return "data:image/jpeg;base64," + base64.b64encode(buf).decode()

    @staticmethod
    def _image_quality(img: np.ndarray) -> float:
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
        lap_var = cv2.Laplacian(gray, cv2.CV_64F).var()
        blur_score = min(lap_var / 100.0, 1.0)
        mean_b = float(np.mean(gray))
        bright_score = 1.0 - abs(mean_b - 128.0) / 128.0
        contrast_score = min(float(np.std(gray)) / 64.0, 1.0)
        return float(0.5 * blur_score + 0.25 * bright_score + 0.25 * contrast_score)

    @staticmethod
    def _error_response(msg: str, t0: float) -> ScanResponse:
        return ScanResponse(
            success=False,
            message=msg,
            detected_votes=[],
            image_quality=0.0,
            markers_detected=0,
            processing_time_ms=(time.time() - t0) * 1000,
            errors=[msg],
        )
"""
OMR Scanner — clean rewrite based on the actual ballot structure.

BALLOT LAYOUT (from generated PDF):
- 8 positions, each with 2 candidates (FORWARD then UNITY)
- Candidates stack vertically within each position block
- Each position block has a shaded header row + candidate rows
- Bubbles are in the leftmost ~8% of the page width
- The ballot has 4 corner anchor squares (solid black squares, ~6mm)
- A tear-line separates the ballot from the voter stub
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

BUBBLE_LANE_LEFT_FRAC  = 0.00
BUBBLE_LANE_RIGHT_FRAC = 0.09   

SCAN_TOP_FRAC    = 0.06
SCAN_BOTTOM_FRAC = 0.97

# Kept for the mathematical fallback
SCAN_TOP_PAD_FRAC  = 0.15   
SCAN_TOP_PAD_PX    = 0      
SCAN_BOTTOM_PAD_PX = 0

BUBBLE_RADIUS_FRAC = 0.012   

FILL_THRESHOLD   = 0.22    
MIN_GAP_SINGLE   = 0.06    
FILL_FLOOR_MULTI = 0.18    
MIN_GAP_RELATIVE = 0.18


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

        self.image = self._preprocess(self.image)
        self.processed_preview_image = self._encode_jpg(self.image)

        detected = self._detect_bubbles(bubble_candidates)

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
    # Step 1 & 2: Preprocessing
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

    def _preprocess(self, img: np.ndarray) -> np.ndarray:
        img = self._warp_to_ballot(img)
        img = self._deskew(img)
        img = self._clahe_enhance(img)
        return img

    def _warp_to_ballot(self, img: np.ndarray) -> np.ndarray:
        h, w = img.shape[:2]
        area = h * w

        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
        blur = cv2.GaussianBlur(gray, (5, 5), 0)
        edges = cv2.Canny(blur, 50, 150)
        edges = cv2.dilate(edges, np.ones((3, 3), np.uint8), iterations=2)

        contours, _ = cv2.findContours(edges, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
        contours = sorted(contours, key=cv2.contourArea, reverse=True)[:8]

        for cnt in contours:
            if cv2.contourArea(cnt) < area * 0.15:
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
            tw, th = int(max(wa, wb)), int(max(ha, hb))

            if tw < w * 0.25 or th < h * 0.25:
                continue

            ar = tw / max(1, th)
            if not (0.20 <= ar <= 2.0):
                continue

            dst = np.array([[0, 0], [tw - 1, 0], [tw - 1, th - 1], [0, th - 1]], dtype="float32")
            M = cv2.getPerspectiveTransform(rect, dst)
            return cv2.warpPerspective(img, M, (tw, th))

        return img

    def _deskew(self, img: np.ndarray) -> np.ndarray:
        h, w = img.shape[:2]
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
        edges = cv2.Canny(gray, 50, 150)
        lines = cv2.HoughLinesP(edges, 1, np.pi / 180, threshold=80, minLineLength=int(min(w, h) * 0.20), maxLineGap=20)
        
        if lines is None:
            return img

        deviations = []
        for line in lines[:300]:
            x1, y1, x2, y2 = line[0]
            dx, dy = float(x2 - x1), float(y2 - y1)
            if abs(dx) < 1 and abs(dy) < 1:
                continue

            deg = float(np.degrees(np.arctan2(dy, dx)))
            dev = deg if abs(deg) <= 45.0 else deg - (90.0 if deg > 0 else -90.0)

            if abs(dev) <= 15.0:
                deviations.append(dev)

        if not deviations:
            return img

        angle = float(np.median(deviations))
        if abs(angle) < 0.3:
            return img

        M = cv2.getRotationMatrix2D((w / 2.0, h / 2.0), -angle, 1.0)
        return cv2.warpAffine(img, M, (w, h), flags=cv2.INTER_LINEAR, borderMode=cv2.BORDER_REPLICATE)

    @staticmethod
    def _clahe_enhance(img: np.ndarray) -> np.ndarray:
        lab = cv2.cvtColor(img, cv2.COLOR_BGR2LAB)
        l, a, b = cv2.split(lab)
        clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8))
        return cv2.cvtColor(cv2.merge((clahe.apply(l), a, b)), cv2.COLOR_LAB2BGR)

    @staticmethod
    def _order_points(pts: np.ndarray) -> np.ndarray:
        rect = np.zeros((4, 2), dtype="float32")
        s = pts.sum(axis=1)
        rect[0], rect[2] = pts[np.argmin(s)], pts[np.argmax(s)]
        d = np.diff(pts, axis=1)
        rect[1], rect[3] = pts[np.argmin(d)], pts[np.argmax(d)]
        return rect

    # ------------------------------------------------------------------
    # NEW: Physical Line Detection (Option 2)
    # ------------------------------------------------------------------

    def _find_row_centers_via_lines(self, gray: np.ndarray, w: int, h: int) -> List[int]:
        """Uses computer vision to find the physical dividing lines on the ballot."""
        
        # Safely skip the ballot header/instructions at the top
        top = int(h * 0.16)
        bottom = int(h * SCAN_BOTTOM_FRAC)
        
        roi = gray[top:bottom, :]
        _, binary = cv2.threshold(roi, 150, 255, cv2.THRESH_BINARY_INV)

        # Isolate horizontal lines by using a wide, flat structural kernel
        kernel_len = int(w * 0.15) 
        horiz_kernel = cv2.getStructuringElement(cv2.MORPH_RECT, (kernel_len, 1))
        
        # Erase everything that isn't a horizontal line (text, bubbles disappear)
        image_lines = cv2.morphologyEx(binary, cv2.MORPH_OPEN, horiz_kernel, iterations=2)

        contours, _ = cv2.findContours(image_lines, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)

        y_coords = []
        for c in contours:
            x, y, bw, bh = cv2.boundingRect(c)
            # Only keep lines that are reasonably wide
            if bw > w * 0.15:
                y_coords.append(y + top) # Add 'top' back to get absolute Y coordinates

        y_coords.sort()

        # Clean up duplicates (thick lines might be detected twice)
        clean_y = []
        for y in y_coords:
            if not clean_y or y - clean_y[-1] > 10:  
                clean_y.append(y)

        # Calculate the exact middle point between each line
        row_centers = []
        for i in range(len(clean_y) - 1):
            center_y = (clean_y[i] + clean_y[i+1]) // 2
            row_centers.append(center_y)

        return row_centers

    # ------------------------------------------------------------------
    # Step 3: Bubble detection
    # ------------------------------------------------------------------

    def _detect_bubbles(self, candidates: List[BubbleCandidate]) -> List[DetectedVote]:
        if not candidates:
            return []

        h, w = self.image.shape[:2]
        gray = cv2.cvtColor(self.image, cv2.COLOR_BGR2GRAY)

        bubble_r = max(8, int(min(h, w) * BUBBLE_RADIUS_FRAC))
        block = max(11, (bubble_r * 4) | 1)
        global_thresh = cv2.adaptiveThreshold(
            gray, 255, cv2.ADAPTIVE_THRESH_GAUSSIAN_C, cv2.THRESH_BINARY_INV, block, 8
        )

        lane_cx = self._estimate_lane_cx(gray, w, h, bubble_r)
        
        # NEW: Find the exact physical rows based on lines
        exact_y_centers = self._find_row_centers_via_lines(gray, w, h)

        expected_slots = sum(1 for c in candidates if not getattr(c, "is_placeholder", False))
        ring_ys = self._detect_ring_ys(gray, w, h, bubble_r, lane_cx, expected_slots)

        slot_measurements = self._assign_slots(
            candidates, ring_ys, h, w, bubble_r, lane_cx, exact_y_centers
        )

        self.debug_bubbles = []
        for slot in slot_measurements:
            score = self._fill_score(gray, slot["cx"], slot["cy"], bubble_r, global_thresh)
            slot["fill_score"] = float(score)
            slot["threshold"]  = FILL_THRESHOLD
            self.debug_bubbles.append(dict(slot))

        return self._select_winners(slot_measurements)

    def _estimate_lane_cx(self, gray: np.ndarray, w: int, h: int, bubble_r: int) -> int:
        default_cx, lane_right = int(w * 0.07), int(w * 0.12)
        top = int(h * (SCAN_TOP_FRAC + SCAN_TOP_PAD_FRAC))
        bottom = int(h * SCAN_BOTTOM_FRAC)
        if top >= bottom: top = int(h * SCAN_TOP_FRAC)
        
        strip = gray[top:bottom, :lane_right]
        dark = (strip < 160).astype(np.float32)
        col_sum = dark.sum(axis=0)
        col_dark_ratio = col_sum / max(1.0, float(strip.shape[0]))
        
        if col_sum.max() < 3: return default_cx

        col_smooth = np.convolve(col_sum, np.ones(max(3, bubble_r)) / max(3, bubble_r), mode='same')
        
        border_band = max(5, int(w * 0.05))
        for x in range(min(border_band, len(col_smooth))): col_smooth[x] *= 0.1
        for x in range(len(col_smooth)):
            if col_dark_ratio[x] >= 0.70: col_smooth[x] *= 0.25

        peak_x = int(np.argmax(col_smooth))
        return max(bubble_r, min(lane_right - bubble_r, peak_x))

    def _detect_ring_ys(self, gray: np.ndarray, w: int, h: int, bubble_r: int, lane_cx: int, expected_count: Optional[int] = None) -> List[int]:
        top = int(h * (SCAN_TOP_FRAC + SCAN_TOP_PAD_FRAC))
        bottom = int(h * SCAN_BOTTOM_FRAC)
        if top >= bottom: top = int(h * SCAN_TOP_FRAC)
        half_lane = max(bubble_r + 4, int(w * 0.025))

        x1, x2 = max(0, lane_cx - half_lane), min(w, lane_cx + half_lane)
        strip = gray[top:bottom, x1:x2]
        blur = cv2.GaussianBlur(strip, (3, 3), 0)

        block = max(9, (bubble_r * 2) | 1)
        thresh = cv2.adaptiveThreshold(blur, 255, cv2.ADAPTIVE_THRESH_GAUSSIAN_C, cv2.THRESH_BINARY_INV, block, 6)

        k = max(3, bubble_r // 2)
        kernel = cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (k, k))
        opened = cv2.morphologyEx(thresh, cv2.MORPH_OPEN, kernel, iterations=1)
        closed = cv2.morphologyEx(opened, cv2.MORPH_CLOSE, kernel, iterations=1)

        n_labels, labels, stats, centroids = cv2.connectedComponentsWithStats(closed, connectivity=8)
        min_area, max_area = int(np.pi * (bubble_r * 0.4) ** 2), int(np.pi * (bubble_r * 2.5) ** 2)

        raw_ys: List[int] = []
        for i in range(1, n_labels):
            area, bw, bh = int(stats[i, cv2.CC_STAT_AREA]), int(stats[i, cv2.CC_STAT_WIDTH]), int(stats[i, cv2.CC_STAT_HEIGHT])
            if not (min_area <= area <= max_area) or bh == 0 or not (0.3 <= bw / bh <= 3.0): continue
            raw_ys.append(int(centroids[i][1]) + top)

        if not raw_ys:
            circles = cv2.HoughCircles(blur, cv2.HOUGH_GRADIENT, dp=1.2, minDist=max(10, int(bubble_r * 1.6)), param1=80, param2=12, minRadius=max(3, int(bubble_r * 0.6)), maxRadius=max(5, int(bubble_r * 1.4)))
            if circles is not None:
                for circ in circles[0, :]: raw_ys.append(int(circ[1]) + top)

        raw_ys.sort()
        merged, min_gap = [], int(bubble_r * 1.5)
        for y in raw_ys:
            if not merged or (y - merged[-1]) >= min_gap: merged.append(y)

        if expected_count and len(merged) > expected_count:
            merged = self._select_consistent_rings(merged, expected_count)
        return merged

    @staticmethod
    def _select_consistent_rings(ring_ys: List[int], count: int) -> List[int]:
        if count <= 0 or len(ring_ys) <= count: return ring_ys
        gaps = np.diff(ring_ys)
        median_gap = float(np.median(gaps)) if gaps.size else 0.0
        if median_gap <= 0.0: return ring_ys[:count]

        best_i, best_score = 0, float("inf")
        for i in range(0, len(ring_ys) - count + 1):
            window_gaps = np.diff(ring_ys[i:i + count])
            score = 0.0 if window_gaps.size == 0 else float(np.mean(np.abs(window_gaps - median_gap)))
            if score < best_score: best_score, best_i = score, i
        return ring_ys[best_i:best_i + count]

    def _assign_slots(self, candidates: List[BubbleCandidate], ring_ys: List[int], h: int, w: int, bubble_r: int, lane_cx: int, exact_y_centers: List[int]) -> List[Dict]:
        rows: Dict[int, List[BubbleCandidate]] = {}
        for c in candidates: rows.setdefault(c.row, []).append(c)
        sorted_rows = sorted(rows.keys())
        if not sorted_rows: return []

        slots: List[Dict] = []
        
        # Smart Fallback check:
        # Determine total rows (1 header + candidates per position)
        total_expected_rows = sum(1 + len([c for c in rows[r] if not getattr(c, "is_placeholder", False)]) for r in sorted_rows)
        use_lines = len(exact_y_centers) >= total_expected_rows

        if use_lines:
            # OPTION 2: Physically map to the detected line centers
            center_idx = 0
            for row_idx in sorted_rows:
                row_candidates = sorted([c for c in rows[row_idx] if not getattr(c, "is_placeholder", False)], key=lambda c: c.col)
                
                # Each position block has 1 header row. Skip its center.
                center_idx += 1 
                
                for i, cand in enumerate(row_candidates):
                    # Grab the exact physical coordinate
                    if center_idx < len(exact_y_centers):
                        geom_y = exact_y_centers[center_idx]
                    else:
                        geom_y = slots[-1]["geom_y"] + int(h * 0.035) if slots else int(h * 0.2)
                    
                    center_idx += 1
                    
                    # Snap to nearest detected ring within a tight tolerance
                    best_ring_y, best_dist = None, int(h * 0.015)
                    for ry in ring_ys:
                        if abs(ry - geom_y) < best_dist:
                            best_dist, best_ring_y = abs(ry - geom_y), ry
                            
                    final_y = best_ring_y if best_ring_y is not None else geom_y
                    final_y = int(np.clip(final_y, bubble_r, h - bubble_r))

                    slots.append(self._create_slot_dict(cand, lane_cx, final_y, geom_y, best_ring_y is not None))
        else:
            # FALLBACK OPTION 1: Math Fractions (if line detection fails due to blur/lighting)
            top = int(h * (SCAN_TOP_FRAC + SCAN_TOP_PAD_FRAC))
            bottom = int(h * SCAN_BOTTOM_FRAC)
            if top >= bottom: top, bottom = int(h * SCAN_TOP_FRAC), int(h * SCAN_BOTTOM_FRAC)
            scan_height = bottom - top

            HEADER_UNITS = 0.85  
            total_units = sum(max(1, len([c for c in rows[r] if not getattr(c, "is_placeholder", False)])) + HEADER_UNITS for r in sorted_rows)

            cursor = float(top)
            for row_idx in sorted_rows:
                row_candidates = sorted([c for c in rows[row_idx] if not getattr(c, "is_placeholder", False)], key=lambda c: c.col)
                n_cands = max(1, len(row_candidates))
                pos_height = scan_height * ((n_cands + HEADER_UNITS) / total_units)
                content_top = cursor + pos_height * (HEADER_UNITS / (n_cands + HEADER_UNITS))
                step_h = (pos_height - pos_height * (HEADER_UNITS / (n_cands + HEADER_UNITS))) / n_cands
                snap_tol = int(step_h * 0.25)

                for i, cand in enumerate(row_candidates):
                    geom_y = int(np.clip(int(content_top + step_h * (i + 0.45)), bubble_r, h - bubble_r))
                    best_ring_y, best_dist = None, snap_tol + 1
                    for ry in ring_ys:
                        if abs(ry - geom_y) < best_dist: best_dist, best_ring_y = abs(ry - geom_y), ry
                    
                    final_y = int(np.clip(best_ring_y if best_ring_y is not None else geom_y, bubble_r, h - bubble_r))
                    slots.append(self._create_slot_dict(cand, lane_cx, final_y, geom_y, best_ring_y is not None))
                cursor += pos_height

        return slots

    def _create_slot_dict(self, cand: BubbleCandidate, cx: int, cy: int, geom_y: int, snapped: bool) -> Dict:
        return {
            "position_id":         int(cand.position_id),
            "candidate_id":        int(cand.candidate_id),
            "candidate_name":      cand.candidate_name,
            "candidate_party":     getattr(cand, "candidate_party", None),
            "position_name":       getattr(cand, "position_name", None),
            "position_vote_limit": max(1, int(getattr(cand, "position_vote_limit", 1) or 1)),
            "row":     int(cand.row),
            "col":     int(cand.col),
            "cx":      cx,
            "cy":      cy,
            "geom_y":  geom_y,
            "snapped": snapped,
        }

    # ------------------------------------------------------------------
    # Step 4 & 5: Scoring & Winner Selection
    # ------------------------------------------------------------------

    def _fill_score(self, gray: np.ndarray, cx: int, cy: int, r: int, global_thresh: Optional[np.ndarray] = None) -> float:
        h, w = gray.shape[:2]
        r_inner, r_outer = max(4, int(r * 0.78)), max(max(4, int(r * 0.78)) + 3, int(r * 1.40))
        x1, x2, y1, y2 = max(0, cx - r_outer), min(w, cx + r_outer + 1), max(0, cy - r_outer), min(h, cy + r_outer + 1)
        
        if x2 <= x1 or y2 <= y1: return 0.0

        roi = gray[y1:y2, x1:x2].astype(np.float32)
        yy, xx = np.mgrid[:roi.shape[0], :roi.shape[1]]
        dist2 = (xx - (cx - x1)) ** 2 + (yy - (cy - y1)) ** 2

        inner_mask, ring_mask = dist2 <= r_inner ** 2, (dist2 > r_inner ** 2) & (dist2 <= r_outer ** 2)
        n_inner = int(inner_mask.sum())
        if n_inner == 0: return 0.0

        inner_ink = float((255.0 - roi[inner_mask]).mean() / 255.0)
        n_ring = int(ring_mask.sum())
        bg_ink = float((255.0 - roi[ring_mask]).mean() / 255.0) if n_ring > 0 else 0.0
        contrast = max(0.0, inner_ink - bg_ink)

        if global_thresh is not None:
            dark_ratio = float(global_thresh[y1:y2, x1:x2][inner_mask].sum()) / (255.0 * n_inner)
        else:
            inner_vals = roi[inner_mask]
            dark_ratio = float((inner_vals < max(0, float(inner_vals.mean()) - float(inner_vals.std()))).sum()) / n_inner

        return float(np.clip(0.50 * contrast + 0.35 * dark_ratio + 0.15 * inner_ink, 0.0, 1.0))

    def _select_winners(self, slots: List[Dict]) -> List[DetectedVote]:
        by_pos: Dict[int, List[Dict]] = {}
        for s in slots: by_pos.setdefault(s["position_id"], []).append(s)

        detected: List[DetectedVote] = []
        for pid, pos_slots in by_pos.items():
            ranked = sorted(pos_slots, key=lambda s: s["fill_score"], reverse=True)
            votes_allowed = max(1, int(ranked[0]["position_vote_limit"]))

            if votes_allowed == 1:
                top, runner = ranked[0], ranked[1] if len(ranked) > 1 else None
                gap = top["fill_score"] - runner["fill_score"] if runner else 1.0
                n_candidates = len(ranked)

                # Adaptive gap handling:
                # - Relax the absolute gap slightly when the candidate count is odd (layout/centering effects).
                # - Also allow selection when the relative gap to the top score is sufficiently large.
                adjusted_min_gap = MIN_GAP_SINGLE
                if n_candidates > 2 and (n_candidates % 2) == 1:
                    adjusted_min_gap *= 0.80

                rel_gap = gap / max(1e-6, top["fill_score"])
                if top["fill_score"] >= FILL_THRESHOLD and (gap >= adjusted_min_gap or rel_gap >= MIN_GAP_RELATIVE):
                    top["detected"], top["detection_mode"] = True, "single_winner"
                    detected.append(self._make_vote(top, min(1.0, top["fill_score"])))
                else:
                    for s in pos_slots:
                        s["detected"], s["detection_mode"] = False, "ambiguous" if top["fill_score"] >= FILL_THRESHOLD else "below_threshold"
            else:
                selected = 0
                for s in ranked:
                    if selected >= votes_allowed: break
                    if s["fill_score"] >= FILL_FLOOR_MULTI:
                        s["detected"], s["detection_mode"] = True, "multi_winner"
                        detected.append(self._make_vote(s, min(1.0, s["fill_score"])))
                        selected += 1

            for s in pos_slots:
                s.setdefault("detected", False)
                s.setdefault("detection_mode", "not_selected")

        return detected

    @staticmethod
    def _make_vote(slot: Dict, confidence: float) -> DetectedVote:
        return DetectedVote(
            position_id=slot["position_id"], candidate_id=slot["candidate_id"],
            candidate_name=slot.get("candidate_name"), candidate_party=slot.get("candidate_party"),
            position_name=slot.get("position_name"), confidence=confidence,
            row=slot["row"], col=slot["col"],
        )

    # ------------------------------------------------------------------
    # Debug utilities
    # ------------------------------------------------------------------

    def _build_debug_overlay(self, img: np.ndarray, bubbles: List[Dict]) -> Optional[str]:
        try:
            canvas = img.copy()
            h, w = canvas.shape[:2]
            bubble_r = max(8, int(min(h, w) * BUBBLE_RADIUS_FRAC))

            for b in bubbles:
                cx, cy, score, detected = int(b.get("cx", 0)), int(b.get("cy", 0)), float(b.get("fill_score", 0.0)), bool(b.get("detected", False))
                color = (0, 200, 60) if detected else (0, 120, 255)
                cv2.circle(canvas, (cx, cy), bubble_r, color, 2)
                cv2.putText(canvas, f"{score:.2f}", (cx + bubble_r + 2, cy + 4), cv2.FONT_HERSHEY_SIMPLEX, 0.38, color, 1, cv2.LINE_AA)

            ok, buf = cv2.imencode(".jpg", canvas, [int(cv2.IMWRITE_JPEG_QUALITY), 88])
            return "data:image/jpeg;base64," + base64.b64encode(buf).decode() if ok else None
        except Exception:
            return None

    @staticmethod
    def _encode_jpg(img: np.ndarray, quality: int = 88) -> Optional[str]:
        ok, buf = cv2.imencode(".jpg", img, [int(cv2.IMWRITE_JPEG_QUALITY), quality])
        return "data:image/jpeg;base64," + base64.b64encode(buf).decode() if ok else None

    @staticmethod
    def _image_quality(img: np.ndarray) -> float:
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
        lap_var = cv2.Laplacian(gray, cv2.CV_64F).var()
        blur_score, mean_b = min(lap_var / 100.0, 1.0), float(np.mean(gray))
        bright_score, contrast_score = 1.0 - abs(mean_b - 128.0) / 128.0, min(float(np.std(gray)) / 64.0, 1.0)
        return float(0.5 * blur_score + 0.25 * bright_score + 0.25 * contrast_score)

    @staticmethod
    def _error_response(msg: str, t0: float) -> ScanResponse:
        return ScanResponse(success=False, message=msg, detected_votes=[], image_quality=0.0, markers_detected=0, processing_time_ms=(time.time() - t0) * 1000, errors=[msg])
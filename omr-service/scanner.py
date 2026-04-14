"""
Core scanning logic with OpenCV for marker detection, perspective correction,
and bubble detection.
"""

import base64
import cv2
import numpy as np
import time
from typing import List, Tuple, Optional, Dict
from models import DetectedVote, BubbleCandidate, ScanResponse
from config import MARKER_CONFIG, BUBBLE_SIZE, HSV_LOWER, HSV_UPPER


class BallotScanner:
    """Main scanner class for OMR ballot processing."""
    
    def __init__(self):
        self.image = None
        self.original_image = None
        self.warped_image = None
        self.processed_image = None
        self.detected_markers = []
        self.debug_bubbles = []
        self.debug_visualization_image = None
        self.processed_preview_image = None
        self.detection_threshold = 0.40

    @staticmethod
    def _encode_preview_image(image: Optional[np.ndarray], quality: int = 88) -> Optional[str]:
        """Encode image as data URL for lightweight frontend preview."""
        if image is None or image.size == 0:
            return None
        ok, buffer = cv2.imencode(".jpg", image, [int(cv2.IMWRITE_JPEG_QUALITY), int(quality)])
        if not ok:
            return None
        encoded = base64.b64encode(buffer).decode("utf-8")
        return f"data:image/jpeg;base64,{encoded}"

    @staticmethod
    def _order_quad_points(points: np.ndarray) -> np.ndarray:
        """Return points in TL, TR, BR, BL order for perspective transform."""
        rect = np.zeros((4, 2), dtype="float32")
        sums = points.sum(axis=1)
        rect[0] = points[np.argmin(sums)]
        rect[2] = points[np.argmax(sums)]

        diffs = np.diff(points, axis=1)
        rect[1] = points[np.argmin(diffs)]
        rect[3] = points[np.argmax(diffs)]
        return rect

    def _normalize_ballot_image(self, image: np.ndarray) -> np.ndarray:
        """Apply document-style normalization similar to mobile scan apps."""
        if image is None or image.size == 0:
            return image

        height, width = image.shape[:2]
        image_area = float(height * width)

        gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
        blurred = cv2.GaussianBlur(gray, (5, 5), 0)
        edges = cv2.Canny(blurred, 60, 180)
        edges = cv2.dilate(edges, np.ones((3, 3), np.uint8), iterations=1)
        edges = cv2.morphologyEx(edges, cv2.MORPH_CLOSE, np.ones((5, 5), np.uint8), iterations=1)

        contours, _ = cv2.findContours(edges, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
        contours = sorted(contours, key=cv2.contourArea, reverse=True)[:12]

        warped = image
        for contour in contours:
            area = cv2.contourArea(contour)
            if area < (image_area * 0.18):
                continue

            perimeter = cv2.arcLength(contour, True)
            if perimeter <= 0:
                continue

            approx = cv2.approxPolyDP(contour, 0.02 * perimeter, True)
            if len(approx) != 4:
                continue

            quad = approx.reshape(4, 2).astype("float32")
            rect = self._order_quad_points(quad)

            width_a = np.linalg.norm(rect[2] - rect[3])
            width_b = np.linalg.norm(rect[1] - rect[0])
            height_a = np.linalg.norm(rect[1] - rect[2])
            height_b = np.linalg.norm(rect[0] - rect[3])

            target_w = int(max(width_a, width_b))
            target_h = int(max(height_a, height_b))

            if target_w < int(width * 0.35) or target_h < int(height * 0.35):
                continue

            aspect_ratio = float(target_w) / float(max(1, target_h))
            if not (0.35 <= aspect_ratio <= 1.25):
                continue

            dst = np.array(
                [
                    [0, 0],
                    [target_w - 1, 0],
                    [target_w - 1, target_h - 1],
                    [0, target_h - 1],
                ],
                dtype="float32",
            )
            matrix = cv2.getPerspectiveTransform(rect, dst)
            warped_candidate = cv2.warpPerspective(image, matrix, (target_w, target_h))

            if warped_candidate is not None and warped_candidate.size > 0:
                warped = warped_candidate
                break

        # Auto-deskew using long horizontal ballot lines to stabilize bubble alignment.
        deskew_gray = cv2.cvtColor(warped, cv2.COLOR_BGR2GRAY)
        deskew_edges = cv2.Canny(deskew_gray, 70, 180)
        lines = cv2.HoughLinesP(
            deskew_edges,
            rho=1,
            theta=np.pi / 180,
            threshold=120,
            minLineLength=max(80, int(warped.shape[1] * 0.35)),
            maxLineGap=14,
        )

        if lines is not None and len(lines) > 0:
            angles = []
            for line in lines[:300]:
                x1, y1, x2, y2 = line[0]
                dx = float(x2 - x1)
                dy = float(y2 - y1)
                if abs(dx) < 1e-6:
                    continue
                angle_deg = float(np.degrees(np.arctan2(dy, dx)))
                # Keep only near-horizontal lines for reliable skew estimation.
                if abs(angle_deg) <= 15.0:
                    angles.append(angle_deg)

            if angles:
                median_angle = float(np.median(np.array(angles, dtype=float)))
                if 0.20 <= abs(median_angle) <= 8.0:
                    h, w = warped.shape[:2]
                    center = (w / 2.0, h / 2.0)
                    rot_mat = cv2.getRotationMatrix2D(center, -median_angle, 1.0)
                    warped = cv2.warpAffine(
                        warped,
                        rot_mat,
                        (w, h),
                        flags=cv2.INTER_LINEAR,
                        borderMode=cv2.BORDER_REPLICATE,
                    )

        # Local contrast enhancement for cleaner mark separation.
        lab = cv2.cvtColor(warped, cv2.COLOR_BGR2LAB)
        l_channel, a_channel, b_channel = cv2.split(lab)
        clahe = cv2.createCLAHE(clipLimit=2.4, tileGridSize=(8, 8))
        l_channel = clahe.apply(l_channel)
        enhanced_lab = cv2.merge((l_channel, a_channel, b_channel))
        enhanced = cv2.cvtColor(enhanced_lab, cv2.COLOR_LAB2BGR)
        return enhanced
        
    def load_image_base64(self, base64_string: str) -> bool:
        """Load image from base64 encoded string."""
        try:
            # Remove data URL prefix if present
            if "," in base64_string:
                base64_string = base64_string.split(",")[1]
            
            image_data = base64.b64decode(base64_string)
            nparr = np.frombuffer(image_data, np.uint8)
            self.image = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
            self.original_image = self.image.copy()
            
            if self.image is None:
                return False
            return True
        except Exception as e:
            print(f"Error loading image: {e}")
            return False
    
    def detect_markers(self) -> Tuple[List[Tuple[int, int]], int]:
        """
        Markers are no longer used for perspective correction.
        Returns empty list for compatibility.
        """
        self.detected_markers = []
        return self.detected_markers, 0
    
    def perspective_warp(self) -> bool:
        """
        Perspective warp is no longer used (markers removed).
        Returns False for compatibility.
        """
        return False
    
    def detect_bubbles(self, bubble_candidates: List[BubbleCandidate]) -> List[DetectedVote]:
        """
        Detect marked bubbles using geometry, ring detection, and fill scoring.
        """
        detected_votes = []
        self.debug_bubbles = []
        
        if not bubble_candidates:
            return detected_votes
        
        image_to_scan = self.image
        height, width = image_to_scan.shape[:2]
        
        gray = cv2.cvtColor(image_to_scan, cv2.COLOR_BGR2GRAY)
        hsv = cv2.cvtColor(image_to_scan, cv2.COLOR_BGR2HSV)
        
        lower = np.array(HSV_LOWER)
        upper = np.array(HSV_UPPER)
        dark_mask = cv2.inRange(hsv, lower, upper)

        scan_y_min = int(height * 0.12)
        scan_y_max = int(height * 0.995)

        # Detect section bands early so scan scope can be applied per section.
        line_section_bands = []
        border_roi = gray[:, : int(width * 0.92)]
        border_mask = cv2.threshold(border_roi, 0, 255, cv2.THRESH_BINARY_INV + cv2.THRESH_OTSU)[1]
        row_strength = np.sum(border_mask > 0, axis=1)
        row_threshold = max(int(width * 0.22), int(np.max(row_strength) * 0.48) if row_strength.size else 0)

        line_rows = np.where(row_strength >= row_threshold)[0].tolist()
        if line_rows:
            grouped_rows = []
            current_group = [line_rows[0]]
            for row in line_rows[1:]:
                if row - current_group[-1] <= 3:
                    current_group.append(row)
                else:
                    grouped_rows.append(current_group)
                    current_group = [row]
            if current_group:
                grouped_rows.append(current_group)

            section_lines = [int(np.mean(group)) for group in grouped_rows]
            section_lines = [line for line in section_lines if line > scan_y_min]

            for idx, top_line in enumerate(section_lines):
                if idx + 1 < len(section_lines):
                    bottom_line = section_lines[idx + 1]
                else:
                    bottom_line = scan_y_max
                band_top = max(0, top_line + 3)
                band_bottom = min(height, bottom_line - 3)
                if band_bottom - band_top > 40:
                    line_section_bands.append((band_top, band_bottom))

            # Merge adjacent thin bands (header/content splits) into one position-level scope.
            if line_section_bands:
                merged_bands = [line_section_bands[0]]
                merge_gap = max(8, int(height * 0.008))
                for band_top, band_bottom in line_section_bands[1:]:
                    prev_top, prev_bottom = merged_bands[-1]
                    if (band_top - prev_bottom) <= merge_gap:
                        merged_bands[-1] = (prev_top, max(prev_bottom, band_bottom))
                    else:
                        merged_bands.append((band_top, band_bottom))
                line_section_bands = merged_bands

        def in_section_scope(y: int, bands: List[Tuple[int, int]], pad: int = 0) -> bool:
            yi = int(y)
            if not bands:
                return scan_y_min <= yi <= scan_y_max
            for top, bottom in bands:
                if (int(top) - pad) <= yi <= (int(bottom) + pad):
                    return True
            return False

        max_row = max((int(b.row) for b in bubble_candidates), default=0)
        if line_section_bands:
            section_start_min = int(line_section_bands[0][0])
            section_start_max = int(line_section_bands[-1][1])
        else:
            section_start_min = int(height * 0.22)
            section_start_max = int(height * 0.74)

        section_span = max(1, section_start_max - section_start_min)
        section_step = max(
            int(height * 0.06),
            int(section_span / max(1, max_row + 1))
        )
        # Layout now keeps vote-limit text in the header row; first bubble starts slightly earlier.
        first_candidate_offset = max(18, int(section_step * 0.14))
        candidate_step = max(22, int(section_step * 0.23))
        default_bubble_center_x = int(width * 0.025)
        bubble_center_x = default_bubble_center_x
        bubble_radius = max(10, int(min(width, height) * 0.0105))

        thresh = cv2.threshold(gray, 0, 255, cv2.THRESH_BINARY_INV + cv2.THRESH_OTSU)[1]
        thresh = cv2.morphologyEx(thresh, cv2.MORPH_OPEN, np.ones((2, 2), np.uint8))
        contours, _ = cv2.findContours(thresh, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)

        ring_centers = []
        left_bound = int(width * 0.24)
        top_bound = scan_y_min
        bottom_bound = scan_y_max
        min_area = max(30, int(np.pi * (bubble_radius * 0.45) ** 2))
        max_area = int(np.pi * (bubble_radius * 1.8) ** 2)

        for contour in contours:
            x, y, bw, bh = cv2.boundingRect(contour)
            if x > left_bound or y < top_bound or y > bottom_bound:
                continue
            if bw <= 0 or bh <= 0:
                continue

            area = cv2.contourArea(contour)
            if area < min_area or area > max_area:
                continue

            aspect = bw / float(bh)
            if not (0.60 <= aspect <= 1.40):
                continue

            perimeter = cv2.arcLength(contour, True)
            if perimeter <= 0:
                continue
            circularity = (4.0 * np.pi * area) / (perimeter * perimeter)
            if circularity < 0.35:
                continue

            moments = cv2.moments(contour)
            if moments["m00"] == 0:
                continue
            cx = int(moments["m10"] / moments["m00"])
            cy = int(moments["m01"] / moments["m00"])
            if not in_section_scope(cy, line_section_bands, pad=max(8, int(bubble_radius * 0.9))):
                continue
            ring_centers.append((cx, cy))

        # Hough fallback: detect printed bubble rings in the left bubble column.
        roi_top = max(0, scan_y_min)
        roi_bottom = min(height, scan_y_max)
        roi_left = max(0, int(width * 0.00))
        roi_right = min(width, int(width * 0.10))
        roi = gray[roi_top:roi_bottom, roi_left:roi_right]
        if roi.size > 0:
            roi_blurred = cv2.medianBlur(roi, 5)
            circles = cv2.HoughCircles(
                roi_blurred,
                cv2.HOUGH_GRADIENT,
                dp=1.2,
                minDist=max(16, int(candidate_step * 0.55)),
                param1=90,
                param2=14,
                minRadius=max(6, int(bubble_radius * 0.45)),
                maxRadius=max(9, int(bubble_radius * 1.45)),
            )
            if circles is not None:
                for circle in np.round(circles[0, :]).astype("int"):
                    cx = int(circle[0] + roi_left)
                    cy = int(circle[1] + roi_top)
                    if not in_section_scope(cy, line_section_bands, pad=max(8, int(bubble_radius * 0.9))):
                        continue
                    ring_centers.append((cx, cy))

        unique_rings = []
        for cx, cy in sorted(ring_centers, key=lambda p: p[1]):
            if all(np.hypot(cx - ux, cy - uy) > max(6, int(bubble_radius * 0.75)) for ux, uy in unique_rings):
                unique_rings.append((cx, cy))

        # Directly detect likely filled bubbles as dense dark circular blobs in the left lane.
        filled_blob_centers = []
        left_lane_roi = dark_mask[:, : int(width * 0.12)]
        if left_lane_roi.size > 0:
            blob_mask = cv2.morphologyEx(left_lane_roi, cv2.MORPH_OPEN, np.ones((2, 2), np.uint8))
            blob_contours, _ = cv2.findContours(blob_mask, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
            blob_min_area = max(45, int(np.pi * (bubble_radius * 0.70) ** 2))
            blob_max_area = int(np.pi * (bubble_radius * 2.10) ** 2)

            for contour in blob_contours:
                x, y, bw, bh = cv2.boundingRect(contour)
                if bw <= 0 or bh <= 0:
                    continue

                area = cv2.contourArea(contour)
                if area < blob_min_area or area > blob_max_area:
                    continue

                aspect = bw / float(bh)
                if not (0.65 <= aspect <= 1.35):
                    continue

                density = area / float(max(1, bw * bh))
                if density < 0.34:
                    continue

                perimeter = cv2.arcLength(contour, True)
                if perimeter <= 0:
                    continue
                circularity = (4.0 * np.pi * area) / (perimeter * perimeter)
                if circularity < 0.42:
                    continue

                moments = cv2.moments(contour)
                if moments["m00"] == 0:
                    continue

                cx = int(moments["m10"] / moments["m00"])
                cy = int(moments["m01"] / moments["m00"])
                if not in_section_scope(cy, line_section_bands, pad=max(8, int(bubble_radius * 0.9))):
                    continue
                filled_blob_centers.append((cx, cy, float(area)))

        # Calibrate bubble lane X conservatively; reject large shifts that likely target text/borders.
        if unique_rings:
            x_pool = [
                int(cx)
                for cx, cy in unique_rings
                if in_section_scope(cy, line_section_bands, pad=max(8, int(bubble_radius * 0.9))) and cx <= int(width * 0.12)
            ]
            if len(x_pool) >= 6:
                candidate_x = int(np.percentile(x_pool, 30))
                max_shift = max(4, int(width * 0.006))
                if abs(candidate_x - default_bubble_center_x) <= max_shift:
                    bubble_center_x = candidate_x

        bubble_center_x = int(np.clip(bubble_center_x, int(width * 0.015), int(width * 0.070)))

        # Keep a fixed bubble lane X; dynamic X calibration can drift into text blocks.
        ring_lane_tolerance = max(10, int(bubble_radius * 1.6))
        ring_lane = [(cx, cy) for cx, cy in unique_rings if abs(cx - bubble_center_x) <= ring_lane_tolerance]
        lane_rings = ring_lane if len(ring_lane) >= 6 else unique_rings

        # Section bands are detected from table borders; used as per-section scan scopes.
        section_bands = list(line_section_bands)

        if not section_bands:
            # Fallback: derive bands from detected ring positions if line detection fails.
            if lane_rings:
                sorted_lane_rings = sorted(lane_rings, key=lambda p: p[1])
                lane_gaps = [sorted_lane_rings[i + 1][1] - sorted_lane_rings[i][1] for i in range(len(sorted_lane_rings) - 1)]
                median_gap = float(np.median(lane_gaps)) if lane_gaps else float(candidate_step)
                section_gap_threshold = max(int(median_gap * 1.8), int(bubble_radius * 2.8), int(candidate_step * 1.4))

                current_group = [sorted_lane_rings[0]]
                for idx in range(1, len(sorted_lane_rings)):
                    previous_y = sorted_lane_rings[idx - 1][1]
                    current_y = sorted_lane_rings[idx][1]
                    if (current_y - previous_y) > section_gap_threshold:
                        section_bands.append((current_group[0][1] - 20, current_group[-1][1] + 20))
                        current_group = []
                    current_group.append(sorted_lane_rings[idx])
                if current_group:
                    section_bands.append((current_group[0][1] - 20, current_group[-1][1] + 20))

        if not section_bands:
            section_bands = [(section_start_min, scan_y_max)]

        # Match positions and sections in vertical order, but only compare circles within the same band.
        ordered_positions = []
        for row_index in sorted({int(b.row) for b in bubble_candidates}):
            position_candidates = sorted(
                [bubble for bubble in bubble_candidates if int(bubble.row) == row_index],
                key=lambda b: (int(b.col), int(b.candidate_id)),
            )
            ordered_positions.append((row_index, position_candidates))

        # Prefer ring-derived position bands when they better match expected row count.
        expected_rows = len(ordered_positions)
        ring_derived_bands = []
        if lane_rings:
            sorted_lane_rings = sorted(lane_rings, key=lambda p: p[1])
            ring_gap_threshold = max(int(candidate_step * 1.55), int(bubble_radius * 2.8))
            current_group = [sorted_lane_rings[0]]
            for idx in range(1, len(sorted_lane_rings)):
                prev_y = int(sorted_lane_rings[idx - 1][1])
                curr_y = int(sorted_lane_rings[idx][1])
                if (curr_y - prev_y) > ring_gap_threshold:
                    y_values = [int(point[1]) for point in current_group]
                    pad = max(10, int(candidate_step * 0.6))
                    ring_derived_bands.append((max(0, min(y_values) - pad), min(height - 1, max(y_values) + pad)))
                    current_group = []
                current_group.append(sorted_lane_rings[idx])
            if current_group:
                y_values = [int(point[1]) for point in current_group]
                pad = max(10, int(candidate_step * 0.6))
                ring_derived_bands.append((max(0, min(y_values) - pad), min(height - 1, max(y_values) + pad)))

        if ring_derived_bands:
            if not section_bands:
                section_bands = ring_derived_bands
            else:
                current_delta = abs(len(section_bands) - expected_rows)
                ring_delta = abs(len(ring_derived_bands) - expected_rows)
                if ring_delta <= current_delta:
                    section_bands = ring_derived_bands

        # Deterministic global mapping: assign detected bubble rings sequentially by known candidate counts.
        # This avoids section-anchor drift when ballot separators are noisy.
        global_row_ring_points = {}
        sorted_lane_rings = sorted(lane_rings, key=lambda p: p[1])
        total_slots = sum(len(position_candidates) for _, position_candidates in ordered_positions)

        if total_slots > 0 and len(sorted_lane_rings) >= total_slots:
            expected_all = []
            row_counts = []
            for row_index, position_candidates in ordered_positions:
                count = len(position_candidates)
                row_counts.append((row_index, count))
                for idx in range(count):
                    expected_all.append(int(section_start_min + (row_index * section_step) + first_candidate_offset + (idx * candidate_step)))

            best_window = None
            best_score = float("inf")
            max_start = len(sorted_lane_rings) - total_slots

            for start_idx in range(0, max_start + 1):
                window = sorted_lane_rings[start_idx:start_idx + total_slots]
                ys = np.array([int(point[1]) for point in window], dtype=float)

                row_penalty = 0.0
                boundary_penalty = 0.0
                offset = 0
                for _, count in row_counts:
                    row_ys = ys[offset:offset + count]
                    if count > 1:
                        gaps = np.diff(row_ys)
                        row_penalty += float(np.std(gaps))
                        row_penalty += abs(float(np.median(gaps)) - float(candidate_step)) * 0.25
                    offset += count

                offset = 0
                for idx, (_, count) in enumerate(row_counts[:-1]):
                    last_y = ys[offset + count - 1]
                    next_first_y = ys[offset + count]
                    boundary_gap = float(next_first_y - last_y)
                    boundary_penalty += max(0.0, (candidate_step * 1.10) - boundary_gap) * 0.90
                    offset += count

                diffs = ys - np.array(expected_all, dtype=float)
                align_penalty = abs(float(np.median(diffs))) * 0.08 + float(np.std(diffs)) * 0.06

                score = row_penalty + boundary_penalty + align_penalty
                if score < best_score:
                    best_score = score
                    best_window = window

            if best_window is not None:
                best_ys = np.array([int(point[1]) for point in best_window], dtype=float)
                expected_ys = np.array(expected_all, dtype=float)
                abs_diffs = np.abs(best_ys - expected_ys)
                median_abs_diff = float(np.median(abs_diffs))
                max_abs_diff = float(np.max(abs_diffs))

                boundary_min_gap = float("inf")
                offset = 0
                for _, count in row_counts[:-1]:
                    last_current = best_ys[offset + count - 1]
                    first_next = best_ys[offset + count]
                    boundary_min_gap = min(boundary_min_gap, float(first_next - last_current))
                    offset += count
                if boundary_min_gap == float("inf"):
                    boundary_min_gap = float(candidate_step)

                use_global_mapping = (
                    median_abs_diff <= max(12, int(candidate_step * 0.55))
                    and max_abs_diff <= max(26, int(candidate_step * 1.10))
                    and boundary_min_gap >= max(6, int(candidate_step * 0.55))
                )

                if use_global_mapping:
                    x_span = max(4, int(bubble_radius * 0.6))
                    offset = 0
                    for row_index, count in row_counts:
                        row_points = []
                        for point in best_window[offset:offset + count]:
                            row_points.append(
                                (
                                    int(np.clip(point[0], bubble_center_x - x_span, bubble_center_x + x_span)),
                                    int(point[1]),
                                )
                            )
                        global_row_ring_points[row_index] = row_points
                        offset += count

        populated_bands = []
        for band_top, band_bottom in section_bands:
            band_rings = [ring for ring in lane_rings if band_top <= ring[1] <= band_bottom]
            if band_rings:
                populated_bands.append((band_top, band_bottom))

        if populated_bands:
            section_bands = populated_bands

        # Assign each position to the nearest section band center while preserving top-to-bottom order.
        sorted_bands = sorted(section_bands, key=lambda b: ((b[0] + b[1]) / 2.0))
        section_pairs = []
        next_band_start = 0

        for row_index, position_candidates in ordered_positions:
            candidate_count = max(1, len(position_candidates))
            geometric_mid = (
                section_start_min
                + (row_index * section_step)
                + first_candidate_offset
                + ((candidate_count - 1) * candidate_step / 2.0)
            )

            selected_band = None
            selected_band_idx = None

            if next_band_start < len(sorted_bands):
                search_slice = list(enumerate(sorted_bands[next_band_start:], start=next_band_start))
                selected_band_idx, selected_band = min(
                    search_slice,
                    key=lambda item: abs(((item[1][0] + item[1][1]) / 2.0) - geometric_mid),
                )
                next_band_start = selected_band_idx + 1

            if selected_band is None:
                # Fallback geometric band for missing detections.
                fallback_top = int(section_start_min + (row_index * section_step) - max(10, int(candidate_step * 0.8)))
                fallback_bottom = int(
                    section_start_min
                    + (row_index * section_step)
                    + first_candidate_offset
                    + max(1, candidate_count - 1) * candidate_step
                    + max(12, int(candidate_step * 1.2))
                )
                selected_band = (max(0, fallback_top), min(height - 1, fallback_bottom))

            section_pairs.append(((row_index, position_candidates), selected_band))

        def region_fill_ratio(center_x: int, center_y: int) -> float:
            x1 = int(max(0, center_x - bubble_radius - 2))
            x2 = int(min(width, center_x + bubble_radius + 2))
            y1 = int(max(0, center_y - bubble_radius - 2))
            y2 = int(min(height, center_y + bubble_radius + 2))

            dark_roi = dark_mask[y1:y2, x1:x2]
            gray_roi = gray[y1:y2, x1:x2]
            if dark_roi.size == 0 or gray_roi.size == 0:
                return 0.0

            roi_h, roi_w = dark_roi.shape[:2]
            yy, xx = np.ogrid[:roi_h, :roi_w]
            cx = center_x - x1
            cy = center_y - y1

            inner_radius = max(4, int(bubble_radius * 0.52))
            center_radius = max(2, int(bubble_radius * 0.26))
            outer_radius = max(inner_radius + 2, int(bubble_radius * 0.92))
            dist2 = (xx - cx) ** 2 + (yy - cy) ** 2
            center_mask = dist2 <= (center_radius ** 2)
            inner_mask = dist2 <= (inner_radius ** 2)
            ring_mask = (dist2 > (inner_radius ** 2)) & (dist2 <= (outer_radius ** 2))

            if np.count_nonzero(inner_mask) == 0:
                return 0.0

            dark_ratio_inner = float(np.mean(dark_roi[inner_mask] > 0))
            dark_ratio_ring = float(np.mean(dark_roi[ring_mask] > 0)) if np.count_nonzero(ring_mask) else 0.0
            ink_inner = float(np.mean((255.0 - gray_roi[inner_mask]) / 255.0))
            ink_ring = float(np.mean((255.0 - gray_roi[ring_mask]) / 255.0)) if np.count_nonzero(ring_mask) else 0.0
            ink_center = float(np.mean((255.0 - gray_roi[center_mask]) / 255.0)) if np.count_nonzero(center_mask) else 0.0
            dark_ratio_center = float(np.mean(dark_roi[center_mask] > 0)) if np.count_nonzero(center_mask) else 0.0
            center_black = float(np.mean(gray_roi[center_mask] < 118)) if np.count_nonzero(center_mask) else 0.0
            inner_black = float(np.mean(gray_roi[inner_mask] < 118))
            ring_black = float(np.mean(gray_roi[ring_mask] < 118)) if np.count_nonzero(ring_mask) else 0.0

            band = max(1, int(bubble_radius * 0.14))
            h_line_mask = (np.abs(yy - cy) <= band) & (dist2 > (outer_radius ** 2))
            v_line_mask = (np.abs(xx - cx) <= band) & (dist2 > (outer_radius ** 2))
            h_line_dark = float(np.mean(dark_roi[h_line_mask] > 0)) if np.count_nonzero(h_line_mask) else 0.0
            v_line_dark = float(np.mean(dark_roi[v_line_mask] > 0)) if np.count_nonzero(v_line_mask) else 0.0

            # Prioritize tiny-core blackness to reject printed ring/line artifacts.
            core_minus_ring = max(0.0, center_black - (0.85 * ring_black))
            inner_minus_ring = max(0.0, inner_black - (0.75 * ring_black))
            center_strength = max(0.0, (0.55 * ink_center + 0.45 * dark_ratio_center) - 0.12)
            texture_guard = max(0.0, (0.50 * ink_inner + 0.50 * dark_ratio_inner) - (0.65 * ink_ring + 0.35 * dark_ratio_ring))
            line_penalty = (0.24 * h_line_dark) + (0.14 * v_line_dark)

            score = (
                (0.46 * core_minus_ring)
                + (0.24 * inner_minus_ring)
                + (0.18 * center_strength)
                + (0.12 * texture_guard)
                - line_penalty
            )
            return float(max(0.0, min(1.0, score * 1.85)))

        bubble_measurements = []
        prev_anchor_end = -10**9
        for (row_index, position_candidates), section_band in section_pairs:
            section_top, section_bottom = section_band
            section_rings = sorted(
                [ring for ring in lane_rings if section_top <= ring[1] <= section_bottom],
                key=lambda p: p[1],
            )
            section_center_x = bubble_center_x

            # Start from global row geometry, but prefer detected section top as local anchor when credible.
            geometric_section_start = int(section_start_min + (row_index * section_step))
            candidate_count = max(1, len(position_candidates))
            geometric_mid_global = int(
                geometric_section_start
                + first_candidate_offset
                + ((max(1, candidate_count) - 1) * candidate_step / 2.0)
            )

            # If detected section is too short for this position, use a geometry-sized fallback band.
            min_required_height = int(first_candidate_offset + max(1, candidate_count - 1) * candidate_step + (bubble_radius * 2))
            if (section_bottom - section_top) < min_required_height:
                section_top = int(geometric_section_start - max(10, int(candidate_step * 0.8)))
                section_bottom = int(
                    geometric_section_start
                    + first_candidate_offset
                    + max(1, candidate_count - 1) * candidate_step
                    + max(12, int(candidate_step * 1.2))
                )
                section_top = max(0, section_top)
                section_bottom = min(height - 1, section_bottom)

                # Re-scope rings to the fallback band.
                section_rings = sorted(
                    [ring for ring in lane_rings if section_top <= ring[1] <= section_bottom],
                    key=lambda p: p[1],
                )

            band_center = int((section_top + section_bottom) / 2)
            band_is_consistent = abs(band_center - geometric_mid_global) <= max(int(section_step * 0.75), int(candidate_step * 3.0))

            row_span = int(first_candidate_offset + max(1, candidate_count - 1) * candidate_step)
            if band_is_consistent:
                row_anchor_start = int(section_top)
            else:
                row_anchor_start = int((0.65 * geometric_section_start) + (0.35 * section_top))

            # Anchor row start to observed rings to absorb header-layout changes.
            if section_rings:
                inferred_start = int(min(int(point[1]) for point in section_rings) - first_candidate_offset)
                blend = 0.65 if candidate_count >= 2 else 0.45
                row_anchor_start = int(((1.0 - blend) * row_anchor_start) + (blend * inferred_start))

            # Keep rows strictly top-to-bottom so neighboring positions cannot share Y slots.
            min_start_from_prev = int(prev_anchor_end + max(12, int(candidate_step * 0.90)))
            if row_anchor_start < min_start_from_prev:
                row_anchor_start = min_start_from_prev

            max_start_in_band = int(section_bottom - row_span - max(8, bubble_radius))
            if max_start_in_band > 0:
                row_anchor_start = min(row_anchor_start, max_start_in_band)

            row_anchor_start = max(0, row_anchor_start)
            prev_anchor_end = int(row_anchor_start + row_span)

            geometric_mid = int(
                row_anchor_start
                + first_candidate_offset
                + ((max(1, candidate_count) - 1) * candidate_step / 2.0)
            )

            usable_top = int(section_top + max(8, bubble_radius))
            usable_bottom = int(section_bottom - max(8, bubble_radius))

            # Guarantee enough vertical room so different candidate slots don't collapse into one Y.
            if usable_bottom - usable_top < max(18, int(candidate_step * 0.8)):
                usable_top = int(max(0, row_anchor_start + first_candidate_offset - max(10, candidate_step // 2)))
                usable_bottom = int(min(height - 1, usable_top + max(24, int((candidate_count + 1) * candidate_step))))

            use_band_constraints = band_is_consistent

            assigned_ring_points = None
            global_points = global_row_ring_points.get(row_index)
            if global_points is not None and len(global_points) == candidate_count:
                assigned_ring_points = global_points

            # Use ordered in-section ring windows for multi-candidate rows to prevent slot shifts.
            window_rings = section_rings
            use_window_matching = len(window_rings) >= candidate_count and candidate_count >= 2
            if assigned_ring_points is None and use_window_matching:
                best_window = None
                best_score = float("inf")
                geometric_ys = [
                    int(row_anchor_start + first_candidate_offset + (i * candidate_step))
                    for i in range(candidate_count)
                ]

                for start_idx in range(0, len(window_rings) - candidate_count + 1):
                    window = window_rings[start_idx:start_idx + candidate_count]
                    ys = [int(point[1]) for point in window]

                    if candidate_count > 1:
                        gaps = [ys[i + 1] - ys[i] for i in range(candidate_count - 1)]
                        gap_std = float(np.std(gaps))
                        median_gap = float(np.median(gaps))
                        gap_penalty = gap_std + (abs(median_gap - candidate_step) * 0.35)
                    else:
                        gap_penalty = 0.0

                    window_center = (ys[0] + ys[-1]) / 2.0
                    center_penalty = abs(window_center - geometric_mid) * 0.05
                    # Penalize windows that are globally shifted away from this row's expected slots.
                    diffs = [ys[i] - geometric_ys[i] for i in range(candidate_count)]
                    median_shift = float(np.median(diffs))
                    shift_spread = float(np.std(diffs)) if candidate_count > 1 else 0.0
                    shift_penalty = abs(median_shift) * 0.18 + shift_spread * 0.12
                    score = gap_penalty + center_penalty + shift_penalty

                    if score < best_score:
                        best_score = score
                        best_window = window

                if best_window is not None:
                    best_ys = [int(point[1]) for point in best_window]
                    best_diffs = [best_ys[i] - geometric_ys[i] for i in range(candidate_count)]
                    median_shift = float(np.median(best_diffs))
                    shift_spread = float(np.std(best_diffs)) if candidate_count > 1 else 0.0
                    max_allowed_shift = max(16, int(candidate_step * 0.95))
                    max_allowed_spread = max(10, int(candidate_step * 0.55))
                    window_center = (best_ys[0] + best_ys[-1]) / 2.0
                    center_ok = abs(window_center - geometric_mid) <= max(int(section_step * 0.95), int(candidate_step * 1.9))

                    gaps = [best_ys[i + 1] - best_ys[i] for i in range(candidate_count - 1)] if candidate_count > 1 else []
                    gap_quality_ok = True
                    if gaps:
                        gap_quality_ok = (
                            float(np.std(gaps)) <= max_allowed_spread
                            and abs(float(np.median(gaps)) - float(candidate_step)) <= max(10, int(candidate_step * 0.65))
                        )

                    relaxed_shift_ok = abs(median_shift) <= max(34, int(candidate_step * 2.0))

                    # Reject windows that point to another section; fall back to geometry in that case.
                    strict_ok = center_ok and abs(median_shift) <= max_allowed_shift and shift_spread <= max_allowed_spread
                    if strict_ok or (gap_quality_ok and relaxed_shift_ok):
                        x_span = max(4, int(bubble_radius * 0.6))
                        assigned_ring_points = [
                            (
                                int(np.clip(point[0], bubble_center_x - x_span, bubble_center_x + x_span)),
                                int(point[1]),
                            )
                            for point in best_window
                        ]

            # For single-candidate rows, anchor directly to the nearest in-section ring.
            if assigned_ring_points is None and candidate_count == 1 and section_rings:
                nearest_single = min(section_rings, key=lambda r: abs(int(r[1]) - geometric_mid))
                single_tolerance = max(24, int(candidate_step * 1.4))
                if abs(int(nearest_single[1]) - geometric_mid) <= single_tolerance or len(section_rings) == 1:
                    x_span = max(4, int(bubble_radius * 0.6))
                    assigned_ring_points = [
                        (
                            int(np.clip(nearest_single[0], bubble_center_x - x_span, bubble_center_x + x_span)),
                            int(nearest_single[1]),
                        )
                    ]

            prev_ring_y = None
            min_slot_gap = max(10, int(candidate_step * 0.50))
            for idx, bubble in enumerate(position_candidates):
                ring_x = section_center_x
                # ORIGINAL geometry formula is CORRECT for vertical stacking:
                # Each col represents a separate line at different Y
                geometric_y = int(row_anchor_start + first_candidate_offset + (idx * candidate_step))
                ring_y = int(geometric_y)
                calibration_mode = "geometry"

                # KEY FIX: For multi-candidate positions, skip pre-assigned ring points
                # (they may be ordered incorrectly) and snap directly to detected rings
                if candidate_count >= 2:
                    # Multi-candidate (vertical stacking): snap to NEAREST detected ring
                    if section_rings:
                        nearest_ring = min(section_rings, key=lambda r: abs(r[1] - geometric_y))
                        snap_tolerance = max(12, int(candidate_step * 0.70))  # Generous tolerance
                        if abs(nearest_ring[1] - geometric_y) <= snap_tolerance:
                            x_span = max(4, int(bubble_radius * 0.6))
                            ring_x = int(np.clip(nearest_ring[0], bubble_center_x - x_span, bubble_center_x + x_span))
                            ring_y = int(nearest_ring[1])
                            calibration_mode = "detected_ring_snap"
                    # If no nearby ring or outside tolerance, use geometric Y (no override)
                else:
                    # Single candidate: allow assigned_ring_points (original behavior)
                    if assigned_ring_points is not None and idx < len(assigned_ring_points):
                        ring_x = int(assigned_ring_points[idx][0])
                        ring_y = int(assigned_ring_points[idx][1])
                        calibration_mode = "section_rings_window"
                    else:
                        if use_band_constraints:
                            pad = max(6, int(candidate_step * 0.25))
                            if ring_y < (usable_top - pad):
                                ring_y = int(usable_top)
                            elif ring_y > (usable_bottom + pad):
                                ring_y = int(usable_bottom)

                        if section_rings:
                            nearest_ring = min(section_rings, key=lambda r: abs(r[1] - ring_y))
                            snap_tolerance = max(8, int(candidate_step * (0.45 if candidate_count >= 3 else 0.55)))
                            if abs(nearest_ring[1] - ring_y) <= snap_tolerance:
                                x_span = max(4, int(bubble_radius * 0.6))
                                ring_x = int(np.clip(nearest_ring[0], bubble_center_x - x_span, bubble_center_x + x_span))
                                ring_y = int(nearest_ring[1])
                                calibration_mode = "section_cluster"
                            elif use_band_constraints:
                                calibration_mode = "section_constrained"

                # Stabilize slot ordering within each position to reduce jitter/swaps.
                if prev_ring_y is not None and ring_y < (prev_ring_y + min_slot_gap):
                    stabilized = prev_ring_y + min_slot_gap
                    ring_y = int(min(stabilized, usable_bottom))
                    calibration_mode = f"{calibration_mode}+stabilized"
                prev_ring_y = int(ring_y)

                calibrated_y = int(ring_y)
                expected_y = int(geometric_y)
                best_fill = 0.0
                best_dx, best_dy = 0, 0

                # For multi-candidate positions, search wider X range with MILD penalty
                # (wider range finds actual bubbles, mild penalty prefers closer offsets)
                if candidate_count >= 2:
                    # Wider range for multi-candidate: ±20 pixels
                    probe_x_offsets = list(range(-20, 21, 2))  # [-20, -18, ..., 18, 20]
                    small_penalty = 0.005  # Very small penalty on offset (find peaks but prefer close)
                else:
                    # Single candidate: standard range
                    probe_x_offsets = [-6, -3, 0, 3, 6]
                    small_penalty = 0.018  # Standard penalty
                
                for dx in probe_x_offsets:
                    for dy in (-5, -2, 0, 2, 5):
                        raw_score = region_fill_ratio(int(ring_x + dx), int(calibrated_y + dy))
                        # Apply mild penalty on offsets (prefers nearby peaks)
                        score = raw_score - (abs(dx) * small_penalty) - (abs(dy) * 0.005)
                        if score > best_fill:
                            best_fill = score
                            best_dx, best_dy = dx, dy

                measurement = {
                    "position_id": int(bubble.position_id),
                    "candidate_id": int(bubble.candidate_id),
                    "candidate_name": str(bubble.candidate_name) if bubble.candidate_name else None,
                    "candidate_party": str(bubble.candidate_party) if getattr(bubble, "candidate_party", None) else None,
                    "position_name": str(bubble.position_name) if getattr(bubble, "position_name", None) else None,
                    "row": int(bubble.row),
                    "col": int(bubble.col),
                    "expected_x": int(ring_x),
                    "expected_y": int(expected_y),
                    "calibrated_y": int(calibrated_y),
                    "calibration_mode": calibration_mode,
                    "best_x": int(ring_x + best_dx),
                    "best_y": int(calibrated_y + best_dy),
                    "fill_score": float(best_fill),
                    "threshold": float(self.detection_threshold),
                    "detected": bool(best_fill >= self.detection_threshold),
                    "detection_mode": "threshold" if best_fill >= self.detection_threshold else "none",
                }
                bubble_measurements.append(measurement)

        votes_by_position = {}
        for measurement in bubble_measurements:
            position_id = measurement["position_id"]
            votes_by_position.setdefault(position_id, []).append(measurement)

        # Blob detections remain available for debugging, but do not force candidate scores.

        all_page_scores = np.array([float(m["fill_score"]) for m in bubble_measurements], dtype=float) if bubble_measurements else np.array([], dtype=float)
        if all_page_scores.size > 0:
            global_med = float(np.median(all_page_scores))
            global_p90 = float(np.percentile(all_page_scores, 90))
            global_p95 = float(np.percentile(all_page_scores, 95))
            global_max = float(np.max(all_page_scores))
            global_std = float(np.std(all_page_scores))
            global_blank_like = (
                global_std < 0.055
                and (global_p95 - global_med) < 0.16
                and global_max < 0.86
            )
        else:
            global_med = 0.0
            global_p90 = 0.0
            global_p95 = 0.0
            global_max = 0.0
            global_blank_like = True

        adaptive_floor_multi = max(0.42, self.detection_threshold)
        adaptive_floor_single = max(0.34, self.detection_threshold - 0.04)

        def sigmoid(value: float) -> float:
            return float(1.0 / (1.0 + np.exp(-value)))

        for _, position_votes in votes_by_position.items():
            ranked = sorted(position_votes, key=lambda item: item["fill_score"], reverse=True)
            if not ranked:
                continue

            raw_scores = np.array([float(item["fill_score"]) for item in ranked], dtype=float)
            score_max = float(np.max(raw_scores))
            score_min = float(np.min(raw_scores))
            score_med = float(np.median(raw_scores))
            score_mean = float(np.mean(raw_scores))
            score_std = float(np.std(raw_scores))
            mad = float(np.median(np.abs(raw_scores - score_med)))
            robust_sigma = max(0.03, 1.4826 * mad)
            span = max(0.05, score_max - score_min)

            for item in ranked:
                raw = float(item["fill_score"])
                rel = (raw - score_min) / span
                z = (raw - score_med) / robust_sigma
                norm = (0.68 * rel) + (0.32 * sigmoid((z - 0.90) * 1.35))
                item["normalized_score"] = float(max(0.0, min(1.0, norm)))
                item["detected"] = False
                item["detection_mode"] = "none"

            ranked_norm = sorted(ranked, key=lambda item: item.get("normalized_score", 0.0), reverse=True)
            top = ranked_norm[0]
            second = ranked_norm[1] if len(ranked_norm) > 1 else None
            top_raw = float(top["fill_score"])
            top_norm = float(top.get("normalized_score", 0.0))
            second_raw = float(second["fill_score"]) if second else 0.0
            second_norm = float(second.get("normalized_score", 0.0)) if second else 0.0
            norm_gap = top_norm - second_norm
            raw_gap = top_raw - second_raw
            is_small_position = len(ranked_norm) <= 3

            if is_small_position:
                raw_floor = max(adaptive_floor_multi, 0.66)
                min_raw_gap = 0.11
                min_norm_gap = 0.10
            else:
                raw_floor = max(adaptive_floor_multi, 0.60)
                min_raw_gap = 0.08
                min_norm_gap = 0.08

            evidence_floor = max(
                raw_floor,
                score_med + (0.08 if is_small_position else 0.06),
                score_mean + (0.45 * score_std) + 0.03,
            )
            if global_blank_like:
                evidence_floor = max(evidence_floor, global_med + 0.20, global_p95 + 0.03, 0.68)
            has_strong_evidence = top_raw >= evidence_floor

            if len(ranked_norm) == 1:
                # Single-candidate positions need stricter evidence to avoid blank-ballot false positives.
                single_candidate_floor = max(0.66, adaptive_floor_single + 0.20, evidence_floor)
                if global_blank_like:
                    single_candidate_floor = max(single_candidate_floor, 0.72)
                if float(top["fill_score"]) >= single_candidate_floor:
                    top["detected"] = True
                    top["detection_mode"] = "single_candidate_strict"
                continue

            # Primary ballot-specific rule: winner must have clear within-position margin.
            if has_strong_evidence and top_norm >= 0.56 and norm_gap >= min_norm_gap and raw_gap >= min_raw_gap:
                top["detected"] = True
                top["detection_mode"] = "normalized_margin"
            elif has_strong_evidence and top_raw >= max(raw_floor + 0.05, 0.74) and raw_gap >= (min_raw_gap * 0.8):
                top["detected"] = True
                top["detection_mode"] = "adaptive_top"

            # Allow likely second vote only for larger positions (e.g., representative lists).
            if len(ranked_norm) >= 5 and second is not None:
                # Secondary picks are only valid when a confident primary was already selected.
                if top["detected"] and second_norm >= 0.52 and second_raw >= raw_floor:
                    if top_raw - second_raw <= 0.12 and (second_raw - score_med) >= 0.10:
                        second["detected"] = True
                        second["detection_mode"] = "normalized_secondary"

        if global_blank_like:
            strongest_mark = max((float(m["fill_score"]) for m in bubble_measurements), default=0.0)
            strong_evidence_floor = max(0.74, global_p95 + 0.06, global_p90 + 0.08)
            if strongest_mark < strong_evidence_floor:
                for measurement in bubble_measurements:
                    if measurement.get("detected"):
                        measurement["detected"] = False
                        measurement["detection_mode"] = "global_blank_guard"

        for measurement in bubble_measurements:
            self.debug_bubbles.append({
                "position_id": measurement["position_id"],
                "candidate_id": measurement["candidate_id"],
                "candidate_name": measurement["candidate_name"],
                "row": measurement["row"],
                "col": measurement["col"],
                "expected_x": measurement["expected_x"],
                "expected_y": measurement["expected_y"],
                "calibrated_y": measurement["calibrated_y"],
                "calibration_mode": measurement["calibration_mode"],
                "best_x": measurement["best_x"],
                "best_y": measurement["best_y"],
                "fill_score": measurement["fill_score"],
                "normalized_score": measurement.get("normalized_score"),
                "threshold": measurement["threshold"],
                "detected": measurement["detected"],
                "detection_mode": measurement["detection_mode"],
            })

            if measurement["detected"]:
                detected_votes.append(
                    DetectedVote(
                        position_id=measurement["position_id"],
                        candidate_id=measurement["candidate_id"],
                        candidate_name=measurement["candidate_name"],
                        candidate_party=measurement.get("candidate_party"),
                        position_name=measurement.get("position_name"),
                        confidence=min(float(measurement.get("normalized_score", measurement["fill_score"])), 1.0),
                        row=measurement["row"],
                        col=measurement["col"],
                    )
                )

        scan_scopes = []
        scope_left = 0
        scope_right = min(width - 1, int(width * 0.14))
        for band_top, band_bottom in section_bands:
            section_top = max(0, int(band_top) - 2)
            section_bottom = min(height - 1, int(band_bottom) + 2)
            if section_bottom > section_top:
                scan_scopes.append((scope_left, section_top, scope_right, section_bottom))
        if not scan_scopes:
            scan_scopes.append((scope_left, max(0, scan_y_min), scope_right, min(height - 1, scan_y_max)))

        self.debug_visualization_image = self._build_debug_visualization(
            image=image_to_scan,
            unique_rings=unique_rings,
            filled_blob_centers=filled_blob_centers,
            bubble_measurements=bubble_measurements,
            section_bands=section_bands,
            scan_scopes=scan_scopes,
        )
        
        return detected_votes

    def _build_debug_visualization(
        self,
        image: np.ndarray,
        unique_rings: List[Tuple[int, int]],
        filled_blob_centers: List[Tuple[int, int, float]],
        bubble_measurements: List[Dict],
        section_bands: List[Tuple[int, int]],
        scan_scopes: List[Tuple[int, int, int, int]],
    ) -> Optional[str]:
        """Create a temporary debug overlay image for scanner tuning."""
        try:
            if image is None or image.size == 0:
                return None

            canvas = image.copy()
            overlay = canvas.copy()
            height, width = canvas.shape[:2]

            for scope_idx, (x1, y1, x2, y2) in enumerate(scan_scopes):
                x1 = int(np.clip(x1, 0, max(0, width - 1)))
                x2 = int(np.clip(x2, 0, max(0, width - 1)))
                y1 = int(np.clip(y1, 0, max(0, height - 1)))
                y2 = int(np.clip(y2, 0, max(0, height - 1)))
                color = (0, 255, 120)
                thickness = 1
                cv2.rectangle(overlay, (x1, y1), (x2, y2), color, thickness)
                label = f"section scope {scope_idx + 1}"
                cv2.putText(
                    overlay,
                    label,
                    (x1 + 4, max(18, y1 - 8)),
                    cv2.FONT_HERSHEY_SIMPLEX,
                    0.45,
                    color,
                    1,
                    cv2.LINE_AA,
                )

            for index, (top, bottom) in enumerate(section_bands):
                top_y = int(np.clip(top, 0, max(0, height - 1)))
                bottom_y = int(np.clip(bottom, 0, max(0, height - 1)))
                cv2.rectangle(overlay, (0, top_y), (int(width * 0.35), bottom_y), (180, 80, 255), 1)
                cv2.putText(
                    overlay,
                    f"band {index + 1}",
                    (6, max(18, top_y + 16)),
                    cv2.FONT_HERSHEY_SIMPLEX,
                    0.45,
                    (180, 80, 255),
                    1,
                    cv2.LINE_AA,
                )

            for cx, cy in unique_rings:
                cv2.circle(overlay, (int(cx), int(cy)), 3, (255, 220, 0), 1)

            for cx, cy, _ in filled_blob_centers:
                cv2.circle(overlay, (int(cx), int(cy)), 4, (255, 0, 255), 1)

            for measurement in bubble_measurements:
                expected_x = int(measurement.get("expected_x", 0))
                expected_y = int(measurement.get("expected_y", 0))
                best_x = int(measurement.get("best_x", expected_x))
                best_y = int(measurement.get("best_y", expected_y))
                detected = bool(measurement.get("detected", False))
                marker_color = (40, 210, 40) if detected else (0, 150, 255)

                cv2.circle(overlay, (expected_x, expected_y), 5, (185, 185, 185), 1)
                cv2.circle(overlay, (best_x, best_y), 6, marker_color, 2)
                cv2.line(overlay, (expected_x, expected_y), (best_x, best_y), marker_color, 1)

                if detected:
                    label = f"P{measurement.get('position_id')} C{measurement.get('candidate_id')}"
                    cv2.putText(
                        overlay,
                        label,
                        (best_x + 8, best_y - 6),
                        cv2.FONT_HERSHEY_SIMPLEX,
                        0.40,
                        marker_color,
                        1,
                        cv2.LINE_AA,
                    )

            canvas = cv2.addWeighted(overlay, 0.86, canvas, 0.14, 0)
            encoded_ok, buffer = cv2.imencode(".jpg", canvas, [int(cv2.IMWRITE_JPEG_QUALITY), 92])
            if not encoded_ok:
                return None

            encoded = base64.b64encode(buffer).decode("utf-8")
            return f"data:image/jpeg;base64,{encoded}"
        except Exception:
            return None
    
    def _calculate_fill_ratio(self, dark_mask: np.ndarray, cx: int, cy: int, radius: int) -> float:
        """Calculate the fill ratio of a circular region."""
        height, width = dark_mask.shape[:2]
        
        x1 = max(0, cx - radius)
        x2 = min(width, cx + radius)
        y1 = max(0, cy - radius)
        y2 = min(height, cy + radius)
        
        if x1 >= x2 or y1 >= y2:
            return 0.0
        
        region = dark_mask[y1:y2, x1:x2]
        if region.size == 0:
            return 0.0
        
        filled_pixels = np.count_nonzero(region)
        total_pixels = region.size
        return float(filled_pixels / total_pixels)
    
    def calculate_image_quality(self) -> float:
        """
        Calculate image quality score based on blur, brightness, and contrast.
        Returns score from 0.0 to 1.0.
        """
        gray = cv2.cvtColor(self.image, cv2.COLOR_BGR2GRAY)
        
        # Laplacian variance (focus quality)
        laplacian_var = cv2.Laplacian(gray, cv2.CV_64F).var()
        blur_score = min(laplacian_var / 100, 1.0)  # Normalize
        
        # Brightness score (not too dark or too bright)
        mean_brightness = np.mean(gray)
        brightness_score = 1.0 - abs(mean_brightness - 128) / 128
        
        # Contrast score
        std_dev = np.std(gray)
        contrast_score = min(std_dev / 64, 1.0)  # Normalize
        
        # Weighted average
        quality_score = (blur_score * 0.5 + brightness_score * 0.25 + contrast_score * 0.25)
        
        return quality_score
    
    def scan(self, image_base64: str, bubble_candidates: List[BubbleCandidate]) -> ScanResponse:
        """
        Main scanning process: load image, detect markers, warp, detect bubbles.
        """
        start_time = time.time()
        errors = []
        self.processed_preview_image = None
        
        # Load image
        if not self.load_image_base64(image_base64):
            return ScanResponse(
                success=False,
                message="Failed to load image",
                detected_votes=[],
                image_quality=0.0,
                markers_detected=0,
                processing_time_ms=0.0,
                errors=["Image loading failed"]
            )

        # CamScanner-like preprocessing pass for cleaner ballot scans.
        self.image = self._normalize_ballot_image(self.image)
        self.processed_preview_image = self._encode_preview_image(self.image)
        
        # Detect markers (now disabled - markers removed for simplified layout)
        markers = []
        marker_count = 0
        
        # Apply perspective warp (now disabled - using original image)
        warp_success = False
        
        # Detect bubbles
        detected_votes = self.detect_bubbles(bubble_candidates)
        
        # Calculate image quality
        quality_score = self.calculate_image_quality()
        
        # Determine success (no longer depends on markers)
        success = len(detected_votes) > 0
        
        processing_time_ms = (time.time() - start_time) * 1000
        
        return ScanResponse(
            success=success,
            message="Scan completed successfully" if success else "Scan completed with issues",
            detected_votes=detected_votes,
            image_quality=quality_score,
            markers_detected=marker_count,
            processing_time_ms=processing_time_ms,
            errors=errors,
            debug_bubbles=self.debug_bubbles,
            debug_visualization_image=self.debug_visualization_image,
            processed_preview_image=self.processed_preview_image,
        )

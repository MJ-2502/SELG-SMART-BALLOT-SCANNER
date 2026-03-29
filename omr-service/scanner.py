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
        Detect the 4 corner anchor markers on the ballot.
        Returns list of marker centers and count of detected markers.
        """
        # Convert to grayscale
        gray = cv2.cvtColor(self.image, cv2.COLOR_BGR2GRAY)
        
        # Apply Gaussian blur
        blurred = cv2.GaussianBlur(gray, (5, 5), 0)
        
        # Thresholding to find dark circles (markers)
        _, thresh = cv2.threshold(blurred, 127, 255, cv2.THRESH_BINARY_INV)
        
        # Find contours
        contours, _ = cv2.findContours(thresh, cv2.RETR_COMPONENT, cv2.CHAIN_APPROX_SIMPLE)
        
        marker_candidates = []
        
        for contour in contours:
            # Filter by area (markers should be consistently sized)
            area = cv2.contourArea(contour)
            if area < 500 or area > 5000:  # Rough size estimate
                continue
            
            # Check if circular
            (x, y), radius = cv2.minEnclosingCircle(contour)
            circularity = area / (np.pi * radius ** 2) if radius > 0 else 0
            
            if circularity > 0.7:  # Circular enough
                marker_candidates.append((int(x), int(y), int(radius)))
        
        # Sort by position to identify corners
        # Top-left, top-right, bottom-left, bottom-right
        if len(marker_candidates) >= 4:
            marker_candidates.sort(key=lambda m: (m[1], m[0]))  # Sort by y, then x
            self.detected_markers = [
                (marker_candidates[0][0], marker_candidates[0][1]),  # Top-left
                (marker_candidates[1][0], marker_candidates[1][1]),  # Top-right
                (marker_candidates[2][0], marker_candidates[2][1]),  # Bottom-left
                (marker_candidates[3][0], marker_candidates[3][1]),  # Bottom-right
            ]
        else:
            self.detected_markers = [(m[0], m[1]) for m in marker_candidates]
        
        return self.detected_markers, len(self.detected_markers)
    
    def perspective_warp(self) -> bool:
        """
        Apply perspective warp using detected markers to straighten the ballot.
        """
        if len(self.detected_markers) != 4:
            return False
        
        # Expected marker positions (reference)
        expected_width = 1940
        expected_height = 2700
        
        # Source points (detected markers)
        src_points = np.float32(self.detected_markers)
        
        # Destination points (where markers should be)
        dst_points = np.float32([
            [30, 30],
            [expected_width + 30, 30],
            [30, expected_height + 30],
            [expected_width + 30, expected_height + 30]
        ])
        
        # Calculate perspective transformation matrix
        matrix = cv2.getPerspectiveTransform(src_points, dst_points)
        
        # Apply warp
        h, w = self.image.shape[:2]
        self.warped_image = cv2.warpPerspective(self.image, matrix, (expected_width + 60, expected_height + 60))
        
        return True
    
    def detect_bubbles(self, bubble_candidates: List[BubbleCandidate]) -> List[DetectedVote]:
        """
        Detect filled bubbles at configured positions and return detected votes.
        """
        detected_votes = []
        
        # Use warped image, or original if warp failed
        image_to_scan = self.warped_image if self.warped_image is not None else self.image
        
        # Convert to HSV for better color detection
        hsv = cv2.cvtColor(image_to_scan, cv2.COLOR_BGR2HSV)
        
        # Create mask for dark colors (filled bubbles)
        lower = np.array(HSV_LOWER)
        upper = np.array(HSV_UPPER)
        mask = cv2.inRange(hsv, lower, upper)
        
        # For each candidate bubble position
        for bubble in bubble_candidates:
            # Estimate pixel position based on bubble layout
            # This is a simplified calculation - adjust based on actual ballot layout
            x = 150 + (bubble.col * 150)  # Column spacing
            y = 200 + (bubble.row * 100)  # Row spacing
            
            radius = 25  # Bubble radius
            
            # Extract region around bubble
            x1 = max(0, x - radius)
            x2 = min(image_to_scan.shape[1], x + radius)
            y1 = max(0, y - radius)
            y2 = min(image_to_scan.shape[0], y + radius)
            
            region = mask[y1:y2, x1:x2]
            
            # Calculate fill percentage
            total_pixels = region.size
            filled_pixels = np.count_nonzero(region)
            fill_percentage = filled_pixels / total_pixels if total_pixels > 0 else 0
            
            # If bubble is marked (threshold 50% filled)
            if fill_percentage > 0.5:
                vote = DetectedVote(
                    position_id=bubble.position_id,
                    candidate_id=bubble.candidate_id,
                    candidate_name=bubble.candidate_name,
                    confidence=min(fill_percentage, 1.0),
                    row=bubble.row,
                    col=bubble.col
                )
                detected_votes.append(vote)
        
        return detected_votes
    
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
        
        # Detect markers
        markers, marker_count = self.detect_markers()
        if marker_count < 4:
            errors.append(f"Only {marker_count}/4 markers detected")
        
        # Apply perspective warp
        warp_success = self.perspective_warp()
        if not warp_success and len(self.detected_markers) > 0:
            errors.append("Perspective warp failed, using original image")
        
        # Detect bubbles
        detected_votes = self.detect_bubbles(bubble_candidates)
        
        # Calculate image quality
        quality_score = self.calculate_image_quality()
        
        # Determine success
        success = len(self.detected_markers) == 4 and warp_success
        
        processing_time_ms = (time.time() - start_time) * 1000
        
        return ScanResponse(
            success=success,
            message="Scan completed successfully" if success else "Scan completed with issues",
            detected_votes=detected_votes,
            image_quality=quality_score,
            markers_detected=marker_count,
            processing_time_ms=processing_time_ms,
            errors=errors
        )

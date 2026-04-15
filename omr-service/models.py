"""
Pydantic models for request and response handling.
"""

from pydantic import BaseModel, Field
from typing import List, Dict, Optional


class BubbleCandidate(BaseModel):
    """Map bubble position to candidate information."""
    row: int = Field(..., description="Row index for the candidate")
    col: int = Field(..., description="Column index within the row")
    candidate_id: int = Field(..., description="Database candidate ID")
    candidate_name: Optional[str] = Field(None, description="Candidate name")
    candidate_party: Optional[str] = Field(None, description="Candidate party")
    position_id: int = Field(..., description="Position ID this candidate is running for")
    position_name: Optional[str] = Field(None, description="Position name")
    position_vote_limit: int = Field(1, description="Maximum allowed votes for this position")


class ScanRequest(BaseModel):
    """Request body for ballot scanning."""
    ballot_image_base64: str = Field(..., description="Base64 encoded ballot image")
    ballot_layout: List[BubbleCandidate] = Field(..., description="Bubble to candidate mapping for this ballot")
    election_id: Optional[int] = Field(None, description="Election ID for tracking")
    ballot_number: Optional[str] = Field(None, description="Unique ballot number for reference")


class DetectedVote(BaseModel):
    """A single detected vote on the ballot."""
    position_id: int = Field(..., description="Position ID")
    candidate_id: int = Field(..., description="Candidate ID that was selected")
    candidate_name: Optional[str] = Field(None, description="Candidate name")
    candidate_party: Optional[str] = Field(None, description="Candidate party")
    position_name: Optional[str] = Field(None, description="Position name")
    confidence: float = Field(..., description="Confidence score (0.0 to 1.0)")
    row: int = Field(..., description="Row index where bubble was detected")
    col: int = Field(..., description="Column index where bubble was detected")


class ScanResponse(BaseModel):
    """Response body for ballot scanning result."""
    success: bool = Field(..., description="Whether scan was successful")
    message: str = Field(..., description="Status message")
    detected_votes: List[DetectedVote] = Field(default_factory=list, description="List of detected votes")
    image_quality: float = Field(..., description="Image quality score (0.0 to 1.0)")
    markers_detected: int = Field(..., description="Number of markers detected (0-4)")
    processing_time_ms: float = Field(..., description="Processing time in milliseconds")
    errors: List[str] = Field(default_factory=list, description="Any errors or warnings")
    debug_bubbles: Optional[List[Dict]] = Field(default=None, description="Debug: fill scores for each bubble position")
    debug_visualization_image: Optional[str] = Field(
        default=None,
        description="Temporary debug overlay image (base64 data URL) showing scan scope and detected marks",
    )
    processed_preview_image: Optional[str] = Field(
        default=None,
        description="Processed ballot preview image after normalization (base64 data URL)",
    )


class HealthResponse(BaseModel):
    """Health check response."""
    status: str = Field(..., description="Service status")
    version: str = Field(..., description="API version")

"""
FastAPI application for ballot scanning (OMR service).
"""

from fastapi import FastAPI, File, UploadFile, HTTPException, Query
from fastapi.middleware.cors import CORSMiddleware
import os
import base64
from typing import List

from models import ScanRequest, ScanResponse, HealthResponse, BubbleCandidate, DetectedVote
from scanner import BallotScanner

# Initialize FastAPI app
app = FastAPI(
    title="SELG Ballot Scanner API",
    description="OMR service for scanning ballots and detecting votes",
    version="1.0.0"
)

# Add CORS middleware to allow requests from Laravel frontend
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # In production, restrict to your Laravel domain
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Global scanner instance
scanner = BallotScanner()


@app.get("/health", response_model=HealthResponse)
async def health_check():
    """
    Health check endpoint.
    """
    return HealthResponse(
        status="healthy",
        version="1.0.0"
    )


@app.post("/scan", response_model=ScanResponse)
async def scan_ballot(
    request: ScanRequest,
    include_debug_image: bool = Query(False, description="Include debug visualization image in response"),
    include_debug_bubbles: bool = Query(False, description="Include detailed bubble debug data in response")
) -> ScanResponse:
    """
    Main endpoint for scanning a ballot image.
    
    Expects:
    - ballot_image_base64: Base64 encoded ballot image
    - ballot_layout: List of bubble positions with candidate mappings
    - election_id: Optional election ID for tracking
    - ballot_number: Optional ballot number for reference
    
    Query parameters:
    - include_debug_image: (default: false) Include debug overlay image in response
    - include_debug_bubbles: (default: false) Include detailed bubble measurements in response
    
    Returns:
    - ScanResponse with detected votes, image quality, and marker detection count
    """
    try:
        # Validate inputs
        if not request.ballot_image_base64:
            raise HTTPException(status_code=400, detail="No image provided")
        
        if not request.ballot_layout:
            raise HTTPException(status_code=400, detail="No ballot layout provided")
        
        # Perform scan
        response = scanner.scan(request.ballot_image_base64, request.ballot_layout)

        has_scan_issues = (not bool(response.success)) or (len(response.detected_votes) == 0)
        
        # Conditionally include debug data based on query parameters
        if not include_debug_image and not has_scan_issues:
            response.debug_visualization_image = None
        
        if not include_debug_bubbles and not has_scan_issues:
            response.debug_bubbles = None
        
        return response
        
    except Exception as e:
        return ScanResponse(
            success=False,
            message=f"Error during scanning: {str(e)}",
            detected_votes=[],
            image_quality=0.0,
            markers_detected=0,
            processing_time_ms=0.0,
            errors=[str(e)]
        )


@app.post("/upload")
async def upload_image(file: UploadFile = File(...)):
    """
    Upload an image and return it as base64.
    """
    try:
        contents = await file.read()
        base64_str = base64.b64encode(contents).decode('utf-8')
        
        return {
            "filename": file.filename,
            "base64": base64_str,
            "size": len(contents)
        }
    except Exception as e:
        raise HTTPException(status_code=400, detail=f"Error uploading file: {str(e)}")


@app.post("/batch-scan")
async def batch_scan_ballots(requests: List[ScanRequest]) -> List[ScanResponse]:
    """
    Scan multiple ballots in batch.
    """
    responses = []
    
    for request in requests:
        response = scanner.scan(request.ballot_image_base64, request.ballot_layout)
        responses.append(response)
    
    return responses


@app.get("/")
async def root():
    """
    Root endpoint with API information.
    """
    return {
        "name": "SELG Ballot Scanner API",
        "version": "1.0.0",
        "description": "OMR service for scanning ballots and detecting votes",
        "endpoints": {
            "health": "/health",
            "scan_ballot": "/scan (POST)",
            "upload_image": "/upload (POST)",
            "batch_scan": "/batch-scan (POST)"
        }
    }


if __name__ == "__main__":
    import uvicorn
    port = int(os.getenv("PORT", 8000))
    uvicorn.run(app, host="0.0.0.0", port=port)

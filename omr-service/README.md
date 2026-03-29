# SELG Ballot Scanner - OMR Service

This is the scanning engine (Phase 4) of the SELG Ballot Scanner system. It's a FastAPI-based service that performs Optical Mark Recognition (OMR) on ballot images to detect votes.

## Features

- **Image Upload & Processing**: Accept ballot images as base64 or file uploads
- **Marker Detection**: Identify 4 corner anchor markers for image alignment
- **Perspective Correction**: Automatically correct rotated/skewed ballot images
- **Bubble Detection**: Detect filled answer bubbles at configured positions
- **Vote Extraction**: Map detected bubbles to candidate IDs
- **Batch Processing**: Scan multiple ballots in parallel
- **Image Quality Assessment**: Evaluate scan quality and provide confidence scores

## Prerequisites

- Python 3.8+
- pip package manager

## Installation

1. Create a Python virtual environment:
```bash
cd omr-service
python -m venv venv
source venv/bin/activate  # On Windows: venv\Scripts\activate
```

2. Install dependencies:
```bash
pip install -r requirements.txt
```

## Running the Service

### Development
```bash
python main.py
```
The service will start on `http://localhost:8000`

### Production (with Uvicorn)
```bash
uvicorn main:app --host 0.0.0.0 --port 8000 --workers 4
```

## API Endpoints

### 1. Health Check
```
GET /health
```
Returns service status and version.

### 2. Scan Ballot
```
POST /scan
Content-Type: application/json

{
  "ballot_image_base64": "iVBORw0KGgoAAAANS...",
  "ballot_layout": [
    {
      "row": 0,
      "col": 0,
      "candidate_id": 1,
      "candidate_name": "John Doe",
      "position_id": 1
    }
  ],
  "election_id": 1,
  "ballot_number": "BALLOT-001"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Scan completed successfully",
  "detected_votes": [
    {
      "position_id": 1,
      "candidate_id": 1,
      "candidate_name": "John Doe",
      "confidence": 0.85,
      "row": 0,
      "col": 0
    }
  ],
  "image_quality": 0.92,
  "markers_detected": 4,
  "processing_time_ms": 245.5,
  "errors": []
}
```

### 3. Upload Image
```
POST /upload
Content-Type: multipart/form-data

[Binary image file]
```

Returns the image as base64 string for use with the scan endpoint.

### 4. Batch Scan
```
POST /batch-scan
Content-Type: application/json

[
  { /* ScanRequest 1 */ },
  { /* ScanRequest 2 */ }
]
```

Processes multiple ballots and returns array of ScanResponse objects.

## Configuration

Edit `config.py` to adjust:

- **Marker positions**: Coordinates of the 4 corner anchor circles
- **Bubble size**: Radius and fill threshold for vote detection
- **Color ranges**: HSV values for detecting filled bubbles
- **Image dimensions**: Expected ballot dimensions for perspective correction

## Integration with Laravel

From your Laravel frontend (Scanner page):

1. Capture image from camera: `getUserMedia()` API
2. Convert image to base64
3. Send to `/scan` endpoint with ballot layout
4. Process response and show results

Example from Blade view:
```javascript
const ballotLayout = [
  { row: 0, col: 0, candidate_id: 1, position_id: 1 },
  { row: 1, col: 0, candidate_id: 2, position_id: 1 }
];

fetch('/api/scan', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    ballot_image_base64: imageBase64,
    ballot_layout: ballotLayout,
    election_id: electionId
  })
})
.then(r => r.json())
.then(data => {
  console.log('Detected votes:', data.detected_votes);
});
```

## Performance Notes

- Average processing time: 200-500ms per ballot
- Supports batch processing for faster throughput
- Image quality threshold: 0.5 (50%) or higher recommended

## Troubleshooting

### No markers detected
- Ensure 4 corner circles are clearly visible
- Check image brightness and contrast
- Adjust `MARKER_CONFIG` coordinates if using different ballot format

### Low bubble detection accuracy
- Increase `BUBBLE_SIZE.fill_threshold` if detecting false positives
- Decrease it if missing marks
- Verify `HSV_LOWER` and `HSV_UPPER` range matches pen/pencil colors

### Slow scanning
- Check image resolution (optimal: 1500-2400 DPI)
- Reduce batch size if processing many ballots
- Consider running multiple worker processes

## Next Steps (Phase 5)

1. Build Scanner Blade page in Laravel
2. Integrate camera capture with `getUserMedia()`
3. Add real-time preview and review screen
4. Implement ballot validation pipeline
5. Create ballot submission transaction flow

## Development Roadmap

- [ ] Add handwriting/signature detection
- [ ] Implement duplicate ballot detection
- [ ] Add support for multiple ballot formats
- [ ] Optimize for mobile camera captures
- [ ] Add audit logging
- [ ] Performance optimization for batch processing

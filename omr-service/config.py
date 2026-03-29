"""
Configuration for ballot marker and bubble detection.
These coordinates are based on the A4/Letter ballot design from Phase 3.
"""

# Marker configuration (4 corner anchor markers)
# Positions are in pixels from top-left, assuming A4 at 300 DPI
MARKER_CONFIG = {
    "top_left": {"x": 30, "y": 30, "radius": 15},
    "top_right": {"x": 1910, "y": 30, "radius": 15},
    "bottom_left": {"x": 30, "y": 2670, "radius": 15},
    "bottom_right": {"x": 1910, "y": 2670, "radius": 15},
}

# Expected ballot dimensions (A4 at 300 DPI = 2480 x 3508 pixels)
BALLOT_WIDTH = 1940
BALLOT_HEIGHT = 2700

# Bubble detection configuration
# Columns represent positions (Left, Center, Right) for each candidate option
# Rows represent candidates
BUBBLE_COLUMNS = {
    "single_choice": {"x": 150},  # Single column for yes/no style
    "multiple_choice": [100, 250, 400],  # Multiple columns for ranking
}

# Bubble dimensions
BUBBLE_SIZE = {
    "radius": 25,  # Approximate bubble radius in pixels
    "fill_threshold": 0.5,  # Percentage of bubble area that must be filled to count as marked
}

# Color detection for filled bubbles (HSV range)
# Bubbles are typically filled with pen/pencil marks (dark colors)
HSV_LOWER = [0, 0, 0]      # Dark colors (broad range)
HSV_UPPER = [180, 255, 100]  # Dark colors

# Candidate configuration (dynamically loaded from database in production)
# This maps bubble row/column positions to candidate IDs
BUBBLE_TO_CANDIDATE = {
    # Position format: (row, col) -> candidate_id
    # Example: (0, 0) -> 1 means first row, first column is candidate ID 1
    # Will be populated from API request
}

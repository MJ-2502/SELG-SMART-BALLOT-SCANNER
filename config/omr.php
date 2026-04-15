<?php

return [
    'service_url' => env('OMR_SERVICE_URL', 'http://127.0.0.1:8001'),
    'timeout' => (int) env('OMR_SERVICE_TIMEOUT', 30),
    'minimum_confidence' => (float) env('OMR_MINIMUM_CONFIDENCE', 0.34),
    'minimum_confidence_multi' => (float) env('OMR_MINIMUM_CONFIDENCE_MULTI', 0.18),
    'minimum_gap_single_seat' => (float) env('OMR_MINIMUM_GAP_SINGLE_SEAT', 0.05),
];
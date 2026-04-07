<?php

return [
    'service_url' => env('OMR_SERVICE_URL', 'http://127.0.0.1:8001'),
    'timeout' => (int) env('OMR_SERVICE_TIMEOUT', 30),
];
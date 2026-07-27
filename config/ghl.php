<?php

return [
    /*
    |------------------------------------------
    | GoHighLevel API
    |------------------------------------------
    */

    'base_url' => env('GHL_BASE_URL', 'https://services.leadconnectorhq.com'),

    'api_key' => env('GHL_API_KEY'),

    'location_id' => env('GHL_LOCATION_ID'),

    'version' => env('GHL_API_VERSION', '2021-07-28'),

    'timeout' => env('GHL_TIMEOUT', 30),

    'retry_times' => env('GHL_RETRY_TIMES', 3),

    'retry_delay_ms' => env('GHL_RETRY_DELAY_MS', 1000),

    // Only disable TLS verification for local dev behind a proxy/self-signed cert.
    // Must stay true in production.
    'verify_ssl' => env('GHL_VERIFY_SSL', true),
];
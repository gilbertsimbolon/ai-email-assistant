<?php

return [
    /*
    |--------------------------------------------------------------------------
    | GoHighLevel Private Integration
    |--------------------------------------------------------------------------
    | Inbox reads/writes conversations through GHL's API using a Private
    | Integration token (Bearer). This is backend-only: never expose api_key
    | to the frontend, never log it. See GoHighLevelApiService.
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

    /*
    |--------------------------------------------------------------------------
    | Sync
    |--------------------------------------------------------------------------
    */

    'sync_interval_seconds' => env('GHL_SYNC_INTERVAL_SECONDS', 30),
];

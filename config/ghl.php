<?php

return [
    /*
    |------------------------------------------
    | GoHighLevel API
    |------------------------------------------
    */

    'base_url' => env('GHL_BASE_URL', 'https://services.leadconnectorhq.com'),

    'api_key' => env('GHL_API_KEY'),

    'version' => env('GHL_API_VERSION', '2021-07-28'),

    'timeout' => env('GHL_TIMEOUT', 30),
];
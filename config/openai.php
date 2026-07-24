<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OpenAI Configuration
    |--------------------------------------------------------------------------
    */

    'api_key' => env('OPENAI_API_KEY'),

    'model' => env('OPENAI_MODEL', 'gpt-4o'),

    'temperature' => env('OPENAI_TEMPERATURE', 0.3),

    'max_tokens' => env('OPENAI_MAX_TOKENS', 1200),

];
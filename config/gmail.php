<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Google OAuth 2.0 Client
    |--------------------------------------------------------------------------
    | Create an OAuth 2.0 Client ID in Google Cloud Console with the Gmail API
    | enabled, and register GOOGLE_REDIRECT_URI as an authorized redirect URI.
    |
    | These three values are a *fallback only*. The application reads Gmail
    | OAuth credentials through GmailConfigurationService, which prefers the
    | admin-managed `gmail_settings` database row and only falls back to
    | these env-based values until an administrator saves a configuration
    | from Settings > Gmail API Configuration. Do not read these config()
    | keys directly anywhere else — go through GmailConfigurationService.
    */

    'client_id' => env('GOOGLE_CLIENT_ID'),

    'client_secret' => env('GOOGLE_CLIENT_SECRET'),

    'redirect_uri' => env('GOOGLE_REDIRECT_URI'),

    'scopes' => [
        'https://www.googleapis.com/auth/gmail.modify',
        'openid',
        'email',
        'profile',
    ],

    /*
    |--------------------------------------------------------------------------
    | Google/Gmail Endpoints
    |--------------------------------------------------------------------------
    */

    'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth',

    'token_url' => 'https://oauth2.googleapis.com/token',

    'revoke_url' => 'https://oauth2.googleapis.com/revoke',

    'userinfo_url' => 'https://openidconnect.googleapis.com/v1/userinfo',

    'api_base_url' => 'https://gmail.googleapis.com/gmail/v1',

    'timeout' => env('GMAIL_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Sync
    |--------------------------------------------------------------------------
    */

    'sync_interval_seconds' => env('GMAIL_SYNC_INTERVAL_SECONDS', 30),

    'history_max_results' => env('GMAIL_HISTORY_MAX_RESULTS', 100),
];

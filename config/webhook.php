<?php

return [
    /*
    |------------------------------------------
    | Inbound Email Webhook
    |------------------------------------------
    */

    'secret' => env('WEBHOOK_SECRET'),

    /*
    |------------------------------------------
    | GoHighLevel Conversation Webhook
    |------------------------------------------
    | Shared secret expected in the X-GHL-Webhook-Secret header. Set this as a
    | custom header on the GHL Workflow "Webhook" action (or Marketplace app
    | webhook config) that calls POST /api/webhooks/ghl/conversation.
    */
    'ghl_secret' => env('GHL_WEBHOOK_SECRET'),
];

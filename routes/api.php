<?php

use App\Http\Controllers\Api\GhlWebhookController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

// Route Webhook
Route::prefix('webhooks')->name('webhooks.')->group(function () {
    Route::post('/email', [WebhookController::class, 'email'])
        ->middleware('verify.webhook')
        ->name('email');

    // Realtime GHL Conversation/Message webhook (see VerifyGhlWebhookSignature
    // for the shared-secret header this expects).
    Route::prefix('ghl')->name('ghl.')->group(function () {
        Route::post('/conversation', [GhlWebhookController::class, 'conversation'])
            ->middleware('verify.ghl-webhook')
            ->name('conversation');
    });
});

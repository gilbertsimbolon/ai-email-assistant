<?php

use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

// Route Webhook
Route::prefix('webhooks')->name('webhooks.')->group(function () {
    Route::post('/email', [WebhookController::class, 'email'])
        ->middleware('verify.webhook')
        ->name('email');
});

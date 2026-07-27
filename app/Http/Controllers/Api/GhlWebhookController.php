<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\GhlConversationWebhookRequest;
use App\Jobs\ProcessGhlWebhookJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class GhlWebhookController extends Controller
{
    /**
     * Receive a GHL conversation/message event. Work is dispatched to a queued
     * job so the response comes back immediately, regardless of how long AI
     * analysis/draft generation takes.
     */
    public function conversation(GhlConversationWebhookRequest $request): JsonResponse
    {
        $payload = $request->validated();

        Log::info('GHL webhook received', [
            'conversation_id' => $payload['conversationId'] ?? null,
        ]);

        ProcessGhlWebhookJob::dispatch($payload);

        return response()->json(['status' => 'received'], 202);
    }
}

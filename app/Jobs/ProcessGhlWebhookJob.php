<?php

namespace App\Jobs;

use App\Services\WebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Kept off the request/response cycle so a slow OpenAI call never blocks the
 * webhook response GHL is waiting on. See WebhookService for the actual logic.
 */
class ProcessGhlWebhookJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload  Tolerant field extraction — the
     *                                          exact shape depends on whether this
     *                                          came from a GHL Workflow "Webhook"
     *                                          action or a Marketplace app event.
     */
    public function __construct(protected array $payload)
    {
    }

    public function handle(WebhookService $webhookService): void
    {
        $webhookService->processConversationEvent($this->payload);
    }
}

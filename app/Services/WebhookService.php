<?php

namespace App\Services;

use App\Repositories\ConversationRepository;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates one inbound GHL conversation/message webhook event: parse →
 * upsert → trigger AI. Pulled out of ProcessGhlWebhookJob so the job is just a
 * queue wrapper, matching the pattern SyncGhlConversationsJob uses for GhlSyncService.
 */
class WebhookService
{
    public function __construct(
        protected ParserService $parser,
        protected ConversationRepository $conversations,
        protected ConversationService $conversationService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function processConversationEvent(array $payload): void
    {
        if (blank($payload['conversationId'] ?? null)) {
            Log::warning('GHL webhook event skipped: missing conversationId', $payload);

            return;
        }

        $messageData = $this->parser->messageFromWebhookEvent($payload);

        if (!$messageData) {
            Log::warning('GHL webhook event skipped: missing messageId/id', $payload);

            return;
        }

        $conversationData = $this->parser->conversationFromWebhookEvent($payload);
        $conversation = $this->conversations->upsertConversation($conversationData);

        $message = $this->conversations->recordMessage($conversation, $messageData);

        if (!$message) {
            // Already processed — e.g. webhook retry, or the polling fallback beat us to it.
            return;
        }

        Log::info('GHL webhook message created', [
            'conversation_id' => $conversation->id,
            'direction' => $messageData->direction,
        ]);

        if ($messageData->isInbound()) {
            $this->conversationService->triggerAiReply($conversation->fresh());
        }
    }
}

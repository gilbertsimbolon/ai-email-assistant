<?php

namespace App\Services\Ghl;

use App\Enums\ConversationStatus;
use App\Enums\DraftStatus;
use App\Models\Draft;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Sends an approved AI/manual draft back out through GHL — the only caller
 * of GoHighLevelApiService::sendEmailMessage(). This is how "Send" on the
 * Laravel composer actually reaches the customer: the agent never opens
 * GHL (see claude.txt section 4).
 */
class GhlSendService
{
    public function __construct(
        protected GoHighLevelApiService $api,
    ) {
    }

    public function sendDraft(Draft $draft): void
    {
        $conversation = $draft->conversation;

        if (blank($conversation?->ghl_conversation_id)) {
            throw new RuntimeException('Percakapan ini belum terhubung dengan GoHighLevel, tidak bisa mengirim balasan.');
        }

        $subject = $draft->content['subject'] ?? ('Re: '.($conversation->subject ?? 'percakapan Anda'));
        $body = $draft->content['body'] ?? '';
        $html = nl2br(e($body));

        $this->api->sendEmailMessage(
            $conversation->ghl_conversation_id,
            $conversation->contact_id,
            $subject,
            $html,
            $body,
        );

        $draft->update(['status' => DraftStatus::Sent]);
        $conversation->update(['status' => ConversationStatus::Replied]);

        Log::info('Draft sent via GHL', [
            'conversation_id' => $conversation->id,
            'draft_id' => $draft->id,
        ]);
    }
}

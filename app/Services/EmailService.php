<?php

namespace App\Services;

use App\Enums\ConversationStatus;
use App\Enums\DraftStatus;
use App\Models\Draft;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Sends an approved AI draft back out through GoHighLevel. This is the only
 * caller of GoHighLevelService::sendEmailMessage() — closing the loop between
 * "AI generated a reply" and "the customer actually receives it".
 */
class EmailService
{
    public function __construct(
        protected GoHighLevelService $ghl,
    ) {
    }

    public function sendDraft(Draft $draft): void
    {
        $conversation = $draft->conversation;

        if (blank($conversation?->ghl_conversation_id)) {
            throw new RuntimeException('Percakapan ini belum terhubung dengan GoHighLevel, tidak bisa mengirim balasan.');
        }

        $subject = $draft->content['subject'] ?? 'Re: percakapan Anda';
        $body = $draft->content['body'] ?? '';
        $html = nl2br(e($body));

        $this->ghl->sendEmailMessage(
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

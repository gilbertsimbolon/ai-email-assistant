<?php

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched by GmailSyncService right after a new inbound customer message
 * has been persisted. Currently has no listeners — AI analysis/draft
 * generation is user-triggered only (see AiGenerationService, called from
 * DraftController::generate()), never run automatically on sync. Kept as a
 * hook for future non-AI reactions to new mail (e.g. an unread badge/toast).
 */
class MessageReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Conversation $conversation,
    ) {
    }
}

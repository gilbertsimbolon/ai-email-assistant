<?php

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched by GmailSyncService right after a new inbound customer message
 * has been persisted, decoupling "a message arrived" from "go run AI
 * analysis + draft generation for it".
 */
class MessageReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Conversation $conversation,
    ) {
    }
}

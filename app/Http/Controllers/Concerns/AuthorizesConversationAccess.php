<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Conversation;
use Illuminate\Http\Request;

/**
 * Ownership check: GHL-sourced conversations are a shared inbox (one GHL
 * Private Integration per location, not per-agent), so any user who already
 * passed the route's `permission:inbox` gate may access them. Legacy
 * Gmail-synced conversations stay scoped to the account owner.
 */
trait AuthorizesConversationAccess
{
    protected function authorizeConversation(Request $request, Conversation $conversation): void
    {
        if (filled($conversation->ghl_conversation_id)) {
            return;
        }

        abort_unless($conversation->gmailAccount?->user_id === $request->user()->id, 403);
    }
}

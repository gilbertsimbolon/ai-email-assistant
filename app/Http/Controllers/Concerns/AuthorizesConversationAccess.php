<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Conversation;
use Illuminate\Http\Request;

/**
 * Shared ownership check: a conversation only belongs to the user who owns
 * the Gmail account it was synced from.
 */
trait AuthorizesConversationAccess
{
    protected function authorizeConversation(Request $request, Conversation $conversation): void
    {
        abort_unless($conversation->gmailAccount?->user_id === $request->user()->id, 403);
    }
}

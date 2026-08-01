<?php

namespace App\Services\Ghl;

use App\DataTransferObjects\ParsedGhlConversationData;
use App\Enums\ConversationStatus;
use App\Models\Conversation;
use Illuminate\Support\Str;

/**
 * The local `conversations` row for a GHL conversation is never a mirror —
 * it's a lazily-created anchor that exists only so Draft/Analysis/AiLog (and
 * the agent-only is_read/is_starred/status workflow state, which GHL has no
 * concept of) have somewhere to attach to (claude.txt section 25). It is
 * never created by a background job; only when an agent actually interacts
 * with a conversation — opens it, runs an AI tool, generates/sends a draft,
 * or toggles star/read/status. Contact name/email/channel/etc are seeded
 * once at creation for convenience only — GHL live data is always what gets
 * displayed, never this row.
 */
class GhlConversationAnchorService
{
    public function findOrCreate(ParsedGhlConversationData $data): Conversation
    {
        return Conversation::firstOrCreate(
            ['ghl_conversation_id' => $data->ghlConversationId],
            [
                'ghl_location_id' => $data->ghlLocationId,
                'contact_id' => $data->contactId,
                'contact_name' => $data->contactName,
                'contact_email' => $data->contactEmail,
                'contact_phone' => $data->contactPhone,
                'channel' => $data->channelSlug(),
                'subject' => $data->subject ? Str::limit($data->subject, 100) : null,
                'status' => ConversationStatus::PendingReview,
                'is_read' => ! $data->isUnread(),
            ]
        );
    }

    /**
     * Look up an existing anchor without creating one — used by the
     * conversation list to overlay local workflow state (is_read/is_starred/
     * status/has_draft) without creating a row for every conversation GHL
     * returns.
     */
    public function find(string $ghlConversationId): ?Conversation
    {
        return Conversation::where('ghl_conversation_id', $ghlConversationId)->first();
    }
}

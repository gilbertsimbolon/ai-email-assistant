<?php

namespace App\DataTransferObjects;

use App\Enums\ConversationStatus;
use Carbon\Carbon;

/**
 * One row in the live GHL-driven Inbox conversation list — a merge of a
 * fresh GHL conversation summary with the local anchor's workflow-only
 * overlay (is_read/is_starred/status/has_draft), which GHL has no concept
 * of. Never persisted itself; rebuilt on every request/poll.
 */
final class GhlConversationListItem
{
    public function __construct(
        public readonly string $ghlConversationId,
        public readonly ?string $contactName,
        public readonly ?string $contactEmail,
        public readonly ?string $contactPhone,
        public readonly string $channelLabel,
        public readonly ?string $preview,
        public readonly ?Carbon $lastActivityAt,
        public readonly bool $isRead,
        public readonly bool $isStarred,
        public readonly ConversationStatus $status,
        public readonly bool $hasDraft,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ghl_conversation_id' => $this->ghlConversationId,
            'contact_name' => $this->contactName,
            'contact_email' => $this->contactEmail,
            'contact_phone' => $this->contactPhone,
            'channel_label' => $this->channelLabel,
            'preview' => $this->preview,
            'last_activity_at' => $this->lastActivityAt?->toIso8601String(),
            'last_activity_human' => $this->lastActivityAt?->diffForHumans(null, true),
            'is_read' => $this->isRead,
            'is_starred' => $this->isStarred,
            'status' => $this->status->value,
            'has_draft' => $this->hasDraft,
        ];
    }
}

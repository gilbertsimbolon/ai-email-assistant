<?php

namespace App\DataTransferObjects;

use Carbon\Carbon;

/**
 * Normalized message parsed from a GHL message resource. Unlike Gmail, GHL
 * returns attachments as direct URLs rather than ids that need a follow-up
 * authenticated fetch, so they're rendered as plain links (see
 * inbox.components.attachment).
 */
final class ParsedGhlMessageData
{
    /**
     * @param  array<int, array{url: string}>  $attachments
     */
    public function __construct(
        public readonly string $ghlMessageId,
        public readonly string $direction,
        public readonly string $body,
        public readonly Carbon $sentAt,
        public readonly array $attachments = [],
    ) {
    }

    public function isInbound(): bool
    {
        return $this->direction === 'inbound';
    }
}

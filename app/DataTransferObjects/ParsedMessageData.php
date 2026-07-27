<?php

namespace App\DataTransferObjects;

use Carbon\Carbon;

/**
 * Normalized message parsed from a Gmail message resource.
 */
final class ParsedMessageData
{
    /**
     * @param  array<int, array{id: string, filename: string, mime_type: string, size: int}>  $attachments
     * @param  array<int, string>  $labelIds
     */
    public function __construct(
        public readonly string $gmailMessageId,
        public readonly string $direction,
        public readonly string $body,
        public readonly Carbon $sentAt,
        public readonly array $attachments = [],
        public readonly array $labelIds = [],
        public readonly ?string $messageIdHeader = null,
    ) {
    }

    public function isInbound(): bool
    {
        return $this->direction === 'inbound';
    }
}

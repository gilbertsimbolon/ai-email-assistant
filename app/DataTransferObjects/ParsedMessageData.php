<?php

namespace App\DataTransferObjects;

use Carbon\Carbon;

/**
 * Normalized message, regardless of whether it came from GHL's
 * /conversations/{id}/messages response or a conversation webhook event.
 */
final class ParsedMessageData
{
    public function __construct(
        public readonly string $ghlMessageId,
        public readonly string $direction,
        public readonly string $body,
        public readonly Carbon $sentAt,
    ) {
    }

    public function isInbound(): bool
    {
        return $this->direction === 'inbound';
    }
}

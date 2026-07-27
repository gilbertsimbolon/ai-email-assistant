<?php

namespace App\DataTransferObjects;

/**
 * Normalized conversation identity, regardless of whether it came from GHL's
 * /conversations/search response or a conversation webhook event.
 */
final class ParsedConversationData
{
    public function __construct(
        public readonly string $ghlConversationId,
        public readonly ?string $ghlLocationId,
        public readonly ?string $contactId,
        public readonly ?string $contactName,
        public readonly ?string $contactEmail,
        public readonly ?string $contactPhone,
        public readonly ?string $subject,
    ) {
    }
}

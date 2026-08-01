<?php

namespace App\DataTransferObjects;

/**
 * Normalized conversation identity parsed from a GHL conversation resource.
 */
final class ParsedGhlConversationData
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

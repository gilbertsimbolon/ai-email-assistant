<?php

namespace App\DataTransferObjects;

/**
 * Normalized conversation identity parsed from a Gmail thread.
 */
final class ParsedConversationData
{
    public function __construct(
        public readonly string $gmailThreadId,
        public readonly ?string $contactId,
        public readonly ?string $contactName,
        public readonly ?string $contactEmail,
        public readonly ?string $contactPhone,
        public readonly ?string $subject,
    ) {
    }
}

<?php

namespace App\DataTransferObjects;

/**
 * Normalized contact details parsed from GHL's /contacts/{id} resource, for
 * the Conversations Column 3 "Contact Details" panel. Every field is
 * nullable/empty-array by default — GHL doesn't guarantee any of these are
 * present, and the UI must never fabricate a value that isn't actually there.
 */
final class ParsedGhlContactData
{
    public function __construct(
        public readonly string $contactId,
        public readonly ?string $firstName,
        public readonly ?string $lastName,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly ?string $dateOfBirth,
        /** @var array<int, string> */
        public readonly array $tags,
        /** @var array<int, array{id: ?string, key: ?string, value: mixed}> */
        public readonly array $customFields,
        public readonly ?bool $dnd,
        public readonly ?string $companyName,
        public readonly ?string $address1,
        public readonly ?string $city,
        public readonly ?string $state,
        public readonly ?string $postalCode,
        public readonly ?string $country,
        public readonly ?string $website,
        public readonly ?string $timezone,
        public readonly ?string $source,
        public readonly ?string $assignedTo,
        public readonly ?string $dateAdded,
        public readonly ?string $dateUpdated,
    ) {
    }

    public function fullName(): ?string
    {
        $name = trim(($this->firstName ?? '').' '.($this->lastName ?? ''));

        return $name !== '' ? $name : null;
    }
}

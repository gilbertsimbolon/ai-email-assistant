<?php

namespace App\Services\Ghl;

use App\DataTransferObjects\ParsedGhlContactData;
use App\DataTransferObjects\ParsedGhlConversationData;
use App\DataTransferObjects\ParsedGhlMessageData;
use Carbon\Carbon;

/**
 * Normalizes the raw shape returned by GHL's /conversations/search,
 * /conversations/{id} and /conversations/{id}/messages endpoints into DTOs,
 * so downstream code (InboxController, GhlThreadLoader, GhlSendService)
 * doesn't need to know GHL's field names directly.
 */
class GhlParserService
{
    public function conversationFromSearchApi(array $raw): ParsedGhlConversationData
    {
        $lastActivity = $raw['dateUpdated'] ?? $raw['lastMessageDate'] ?? $raw['dateAdded'] ?? null;

        return new ParsedGhlConversationData(
            ghlConversationId: (string) $raw['id'],
            ghlLocationId: $raw['locationId'] ?? null,
            contactId: $raw['contactId'] ?? null,
            contactName: $raw['contactName'] ?? $raw['fullName'] ?? null,
            contactEmail: $raw['email'] ?? null,
            contactPhone: $raw['phone'] ?? null,
            subject: $raw['lastMessageBody'] ?? null,
            channel: $raw['lastMessageType'] ?? $raw['type'] ?? null,
            unreadCount: (int) ($raw['unreadCount'] ?? 0),
            lastActivityAt: $this->parseDate($lastActivity),
        );
    }

    public function messageFromSearchApi(array $raw): ?ParsedGhlMessageData
    {
        if (!isset($raw['id'])) {
            return null;
        }

        return new ParsedGhlMessageData(
            ghlMessageId: (string) $raw['id'],
            direction: $raw['direction'] ?? 'inbound',
            body: $raw['body'] ?? '',
            sentAt: $this->parseDate($raw['dateAdded'] ?? null) ?? now(),
            attachments: $this->parseAttachments($raw['attachments'] ?? []),
        );
    }

    /**
     * Parses GHL's /contacts/{id} response for the Conversations Column 3
     * "Contact Details" panel. Every field is read defensively (`?? null`)
     * — a field missing from GHL's response stays null/empty here, never
     * faked, so the view can render "not available" instead of a made-up
     * value.
     */
    public function contactFromApi(array $raw): ParsedGhlContactData
    {
        return new ParsedGhlContactData(
            contactId: (string) ($raw['id'] ?? ''),
            firstName: $raw['firstName'] ?? null,
            lastName: $raw['lastName'] ?? null,
            email: $raw['email'] ?? null,
            phone: $raw['phone'] ?? null,
            dateOfBirth: $raw['dateOfBirth'] ?? null,
            tags: $this->parseTags($raw['tags'] ?? []),
            customFields: $this->parseCustomFields($raw['customFields'] ?? []),
            dnd: array_key_exists('dnd', $raw) ? (bool) $raw['dnd'] : null,
            companyName: $raw['companyName'] ?? null,
            address1: $raw['address1'] ?? null,
            city: $raw['city'] ?? null,
            state: $raw['state'] ?? null,
            postalCode: $raw['postalCode'] ?? null,
            country: $raw['country'] ?? null,
            website: $raw['website'] ?? null,
            timezone: $raw['timezone'] ?? null,
            source: $raw['source'] ?? null,
            assignedTo: $raw['assignedTo'] ?? null,
            dateAdded: $raw['dateAdded'] ?? null,
            dateUpdated: $raw['dateUpdated'] ?? null,
        );
    }

    /**
     * Safely parses GHL date values whether they come as a string, integer timestamp,
     * or a nested array (e.g. ['date' => '...']).
     */
    protected function parseDate(mixed $dateValue): ?Carbon
    {
        if (empty($dateValue)) {
            return null;
        }

        // Handle case when GHL returns date as an array
        if (is_array($dateValue)) {
            $dateValue = $dateValue['date']
                ?? $dateValue['value']
                ?? $dateValue['timestamp']
                ?? null;

            if (!$dateValue) {
                return null;
            }
        }

        try {
            // Handle unix timestamp (in milliseconds or seconds)
            if (is_numeric($dateValue)) {
                $timestamp = strlen((string) $dateValue) === 13
                    ? (int) ($dateValue / 1000)
                    : (int) $dateValue;

                return Carbon::createFromTimestamp($timestamp);
            }

            if (is_string($dateValue)) {
                return Carbon::parse($dateValue);
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $rawTags
     * @return array<int, string>
     */
    protected function parseTags(array $rawTags): array
    {
        return collect($rawTags)
            ->filter(fn ($tag) => is_string($tag) && $tag !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rawFields
     * @return array<int, array{id: ?string, key: ?string, value: mixed}>
     */
    protected function parseCustomFields(array $rawFields): array
    {
        return collect($rawFields)
            ->filter(fn ($field) => is_array($field))
            ->map(fn (array $field) => [
                'id' => $field['id'] ?? null,
                'key' => $field['key'] ?? ($field['name'] ?? null),
                'value' => $field['value'] ?? ($field['field_value'] ?? null),
            ])
            ->filter(fn (array $field) => filled($field['value']))
            ->values()
            ->all();
    }

    /**
     * GHL returns message attachments as a flat array of URLs (unlike
     * Gmail's blob-by-id model), so there's nothing to fetch on-demand —
     * just link to them directly (see inbox.components.attachment).
     *
     * @param  array<int, string>  $rawAttachments
     * @return array<int, array{url: string}>
     */
    protected function parseAttachments(array $rawAttachments): array
    {
        return collect($rawAttachments)
            ->filter(fn ($url) => is_string($url) && $url !== '')
            ->map(fn (string $url) => ['url' => $url])
            ->values()
            ->all();
    }
}

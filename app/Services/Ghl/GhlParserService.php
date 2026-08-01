<?php

namespace App\Services\Ghl;

use App\DataTransferObjects\ParsedGhlConversationData;
use App\DataTransferObjects\ParsedGhlMessageData;
use Carbon\Carbon;

/**
 * Normalizes the raw shape returned by GHL's /conversations/search and
 * /conversations/{id}/messages endpoints into DTOs, so downstream code
 * (ConversationRepository, GhlSyncService) doesn't need to know GHL's field
 * names directly.
 */
class GhlParserService
{
    public function conversationFromSearchApi(array $raw): ParsedGhlConversationData
    {
        return new ParsedGhlConversationData(
            ghlConversationId: (string) $raw['id'],
            ghlLocationId: $raw['locationId'] ?? null,
            contactId: $raw['contactId'] ?? null,
            contactName: $raw['contactName'] ?? $raw['fullName'] ?? null,
            contactEmail: $raw['email'] ?? null,
            contactPhone: $raw['phone'] ?? null,
            subject: $raw['lastMessageBody'] ?? null,
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
            sentAt: isset($raw['dateAdded']) ? Carbon::parse($raw['dateAdded']) : now(),
            attachments: $this->parseAttachments($raw['attachments'] ?? []),
        );
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

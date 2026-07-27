<?php

namespace App\Services;

use App\DataTransferObjects\ParsedConversationData;
use App\DataTransferObjects\ParsedMessageData;
use Carbon\Carbon;

/**
 * Normalizes the two different raw shapes GHL sends us — the
 * /conversations/search polling response and a conversation webhook event —
 * into the same DTOs, so downstream code (ConversationRepository, WebhookService,
 * GhlSyncService) doesn't need to know which source produced the data.
 */
class ParserService
{
    public function conversationFromSearchApi(array $raw): ParsedConversationData
    {
        return new ParsedConversationData(
            ghlConversationId: (string) $raw['id'],
            ghlLocationId: $raw['locationId'] ?? null,
            contactId: $raw['contactId'] ?? null,
            contactName: $raw['contactName'] ?? $raw['fullName'] ?? null,
            contactEmail: $raw['email'] ?? null,
            contactPhone: $raw['phone'] ?? null,
            subject: $raw['lastMessageBody'] ?? null,
        );
    }

    public function messageFromSearchApi(array $raw): ?ParsedMessageData
    {
        if (!isset($raw['id'])) {
            return null;
        }

        return new ParsedMessageData(
            ghlMessageId: (string) $raw['id'],
            direction: $raw['direction'] ?? 'inbound',
            body: $raw['body'] ?? '',
            sentAt: isset($raw['dateAdded']) ? Carbon::parse($raw['dateAdded']) : now(),
        );
    }

    public function conversationFromWebhookEvent(array $payload): ParsedConversationData
    {
        return new ParsedConversationData(
            ghlConversationId: (string) $payload['conversationId'],
            ghlLocationId: $payload['locationId'] ?? null,
            contactId: $payload['contactId'] ?? null,
            contactName: $payload['contactName'] ?? null,
            contactEmail: $payload['contactEmail'] ?? null,
            contactPhone: $payload['contactPhone'] ?? null,
            subject: $payload['subject'] ?? null,
        );
    }

    public function messageFromWebhookEvent(array $payload): ?ParsedMessageData
    {
        $ghlMessageId = $payload['messageId'] ?? $payload['id'] ?? null;

        if (!$ghlMessageId) {
            return null;
        }

        return new ParsedMessageData(
            ghlMessageId: (string) $ghlMessageId,
            direction: $payload['direction'] ?? 'inbound',
            body: $payload['body'] ?? $payload['message'] ?? '',
            sentAt: isset($payload['dateAdded']) ? Carbon::parse($payload['dateAdded']) : now(),
        );
    }
}

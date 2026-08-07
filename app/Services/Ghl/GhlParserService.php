<?php

namespace App\Services\Ghl;

use App\DataTransferObjects\ParsedGhlContactData;
use App\DataTransferObjects\ParsedGhlConversationData;
use App\DataTransferObjects\ParsedGhlMessageData;
use Carbon\Carbon;

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

    // GhlParserService.php

    public function messageFromSearchApi(array $raw): ?ParsedGhlMessageData
    {
        $ghlMessageId = $raw['id'] ?? null;
        if (! $ghlMessageId) {
            return null;
        }

<<<<<<< HEAD
        // Ambil isi HTML utuh (prioritas field: html -> body -> message)
        $body = $raw['html']
            ?? $raw['body']
            ?? $raw['message']
            ?? '';

        $attachments = $raw['attachments'] ?? [];

        // Filter pesan jika benar-benar hampa
        if (blank(trim(strip_tags($body))) && empty($attachments)) {
            return null;
        }

        $direction = strtolower($raw['direction'] ?? 'inbound');

        $rawDate = $raw['dateAdded'] ?? $raw['createdAt'] ?? $raw['date'] ?? null;
        $sentAt = $rawDate ? Carbon::parse($rawDate) : now();

        // Instantiate DTO ParsedGhlMessageData sesuai constructor di gambar Anda
        return new ParsedGhlMessageData(
            ghlMessageId: $ghlMessageId,
            direction: $direction,
            body: $body,
            sentAt: $sentAt,
            attachments: $attachments
=======
        // Ekstrak isi pesan (termasuk penanganan jika body di root kosong/null)
        $body = $this->extractMessageBody($raw);

        // Tentukan arah pesan (Inbound/Outbound)
        $direction = $this->determineDirection($raw);

        $rawDate = $raw['dateAdded']
            ?? $raw['dateUpdated']
            ?? $raw['date']
            ?? $raw['createdAt']
            ?? null;

        return new ParsedGhlMessageData(
            ghlMessageId: (string) $raw['id'],
            direction: $direction,
            body: $body,
            sentAt: $this->parseDate($rawDate) ?? now(),
            attachments: $this->parseAttachments($raw['attachments'] ?? []),
>>>>>>> acb3fd5 (note: untuk diperbaiki menggunakan claude)
        );
    }

    /**
     * Mengambil isi pesan agar pesan customer tidak pernah dianggap kosong.
     */
    protected function extractMessageBody(array $raw): string
    {
        if (filled($raw['body'] ?? null)) {
            return (string) $raw['body'];
        }

        if (filled($raw['html'] ?? null)) {
            return (string) $raw['html'];
        }

        if (filled($raw['text'] ?? null)) {
            return (string) $raw['text'];
        }

        // Ambil subject dari meta.email jika body root kosong
        $subject = data_get($raw, 'meta.email.subject');
        if (filled($subject)) {
            return "<strong>Subject: " . e($subject) . "</strong><br><span class='text-muted'>(Pesan masuk tanpa konten body teks)</span>";
        }

        return "<span class='text-muted'>(Tidak ada isi pesan)</span>";
    }

    /**
     * Memastikan pesan dari customer tetap masuk sebagai 'inbound'.
     */
    protected function determineDirection(array $raw): string
    {
        $direction = strtolower((string) ($raw['direction'] ?? ''));
        $source = strtolower((string) ($raw['source'] ?? ''));

        // Jika dikirim via workflow/sistem, anggap outbound
        if (in_array($source, ['workflow', 'app', 'system', 'api'], true) && $direction === 'inbound') {
            return 'outbound';
        }

        if (in_array($direction, ['inbound', 'in', 'incoming'], true)) {
            return 'inbound';
        }

        return 'outbound';
    }

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

    protected function parseDate(mixed $dateValue): ?Carbon
    {
        if (empty($dateValue)) {
            return null;
        }

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

    protected function parseTags(array $rawTags): array
    {
        return collect($rawTags)
            ->filter(fn($tag) => is_string($tag) && $tag !== '')
            ->values()
            ->all();
    }

    protected function parseCustomFields(array $rawFields): array
    {
        return collect($rawFields)
            ->filter(fn($field) => is_array($field))
            ->map(fn(array $field) => [
                'id' => $field['id'] ?? null,
                'key' => $field['key'] ?? ($field['name'] ?? null),
                'value' => $field['value'] ?? ($field['field_value'] ?? null),
            ])
            ->filter(fn(array $field) => filled($field['value']))
            ->values()
            ->all();
    }

    protected function parseAttachments(array $rawAttachments): array
    {
        return collect($rawAttachments)
            ->filter(fn($url) => is_string($url) && $url !== '')
            ->map(fn(string $url) => ['url' => $url])
            ->values()
            ->all();
    }
}
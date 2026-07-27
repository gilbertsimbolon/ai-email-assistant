<?php

namespace App\Services\Gmail;

use App\DataTransferObjects\ParsedConversationData;
use App\DataTransferObjects\ParsedMessageData;
use App\Models\GmailAccount;
use Carbon\Carbon;

/**
 * Normalizes a Gmail message resource (users.messages.get, format=full) into
 * ParsedConversationData/ParsedMessageData. Gmail has no explicit
 * "inbound/outbound" field like GHL did, so direction is derived by
 * comparing the message's From header against the connected account's own
 * address.
 */
class GmailParserService
{
    public function conversationFromMessage(GmailAccount $account, array $message): ParsedConversationData
    {
        $headers = $message['payload']['headers'] ?? [];
        $from = $this->parseAddress($this->header($headers, 'From') ?? '');
        $to = $this->parseAddress($this->header($headers, 'To') ?? '');

        $isFromAccount = $from['email'] && strcasecmp($from['email'], $account->email) === 0;
        $contact = $isFromAccount ? $to : $from;

        return new ParsedConversationData(
            gmailThreadId: (string) $message['threadId'],
            contactId: null,
            contactName: $contact['name'],
            contactEmail: $contact['email'],
            contactPhone: null,
            subject: $this->header($headers, 'Subject'),
        );
    }

    public function messageFromMessage(GmailAccount $account, array $message): ParsedMessageData
    {
        $headers = $message['payload']['headers'] ?? [];
        $from = $this->parseAddress($this->header($headers, 'From') ?? '');
        $direction = strcasecmp($from['email'] ?? '', $account->email) === 0 ? 'outbound' : 'inbound';
        $payload = $message['payload'] ?? [];

        return new ParsedMessageData(
            gmailMessageId: (string) $message['id'],
            direction: $direction,
            body: $this->extractBody($payload) ?: ($message['snippet'] ?? ''),
            sentAt: isset($message['internalDate'])
                ? Carbon::createFromTimestampMs((int) $message['internalDate'])
                : now(),
            attachments: $this->extractAttachments($payload),
            labelIds: $message['labelIds'] ?? [],
            messageIdHeader: $this->header($headers, 'Message-ID'),
        );
    }

    /**
     * @return array{name: ?string, email: ?string}
     */
    protected function parseAddress(string $raw): array
    {
        $raw = trim($raw);

        if ($raw === '') {
            return ['name' => null, 'email' => null];
        }

        if (preg_match('/^(.*)<(.+)>$/', $raw, $matches)) {
            $name = trim($matches[1], " \t\"'");

            return ['name' => $name !== '' ? $name : null, 'email' => trim($matches[2])];
        }

        return ['name' => null, 'email' => $raw];
    }

    protected function header(array $headers, string $name): ?string
    {
        foreach ($headers as $header) {
            if (strcasecmp($header['name'] ?? '', $name) === 0) {
                return $header['value'] ?? null;
            }
        }

        return null;
    }

    protected function extractBody(array $payload): string
    {
        if (!empty($payload['parts'])) {
            $plain = $this->findPartByMimeType($payload['parts'], 'text/plain');

            if ($plain) {
                return $this->decodeBody($plain['body']['data'] ?? '');
            }

            $html = $this->findPartByMimeType($payload['parts'], 'text/html');

            if ($html) {
                return trim(strip_tags($this->decodeBody($html['body']['data'] ?? '')));
            }

            return '';
        }

        $decoded = $this->decodeBody($payload['body']['data'] ?? '');

        return ($payload['mimeType'] ?? null) === 'text/html' ? trim(strip_tags($decoded)) : $decoded;
    }

    protected function findPartByMimeType(array $parts, string $mimeType): ?array
    {
        foreach ($parts as $part) {
            if (($part['mimeType'] ?? null) === $mimeType && !empty($part['body']['data'] ?? null)) {
                return $part;
            }

            if (!empty($part['parts'])) {
                $found = $this->findPartByMimeType($part['parts'], $mimeType);

                if ($found) {
                    return $found;
                }
            }
        }

        return null;
    }

    protected function decodeBody(string $data): string
    {
        if ($data === '') {
            return '';
        }

        return base64_decode(strtr($data, '-_', '+/')) ?: '';
    }

    /**
     * @return array<int, array{id: string, filename: string, mime_type: string, size: int}>
     */
    protected function extractAttachments(array $payload): array
    {
        $attachments = [];
        $this->collectAttachments($payload['parts'] ?? [], $attachments);

        return $attachments;
    }

    protected function collectAttachments(array $parts, array &$attachments): void
    {
        foreach ($parts as $part) {
            if (!empty($part['filename']) && !empty($part['body']['attachmentId'])) {
                $attachments[] = [
                    'id' => $part['body']['attachmentId'],
                    'filename' => $part['filename'],
                    'mime_type' => $part['mimeType'] ?? 'application/octet-stream',
                    'size' => $part['body']['size'] ?? 0,
                ];
            }

            if (!empty($part['parts'])) {
                $this->collectAttachments($part['parts'], $attachments);
            }
        }
    }
}

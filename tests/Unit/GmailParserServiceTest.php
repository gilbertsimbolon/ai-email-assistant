<?php

use App\Models\GmailAccount;
use App\Services\Gmail\GmailParserService;

function base64UrlEncode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

beforeEach(function () {
    $this->parser = new GmailParserService();
    $this->account = new GmailAccount(['email' => 'agent@example.com']);
});

test('parses an inbound message: contact is the From header, direction inbound', function () {
    $message = [
        'id' => 'msg-1',
        'threadId' => 'thread-1',
        'labelIds' => ['INBOX', 'UNREAD'],
        'snippet' => 'Hello there',
        'internalDate' => '1700000000000',
        'payload' => [
            'mimeType' => 'multipart/mixed',
            'headers' => [
                ['name' => 'From', 'value' => 'Jane Doe <jane@example.com>'],
                ['name' => 'To', 'value' => 'agent@example.com'],
                ['name' => 'Subject', 'value' => 'Refund question'],
                ['name' => 'Message-ID', 'value' => '<abc123@mail.gmail.com>'],
            ],
            'parts' => [
                [
                    'mimeType' => 'text/plain',
                    'body' => ['data' => base64UrlEncode('Hello, I need a refund.')],
                ],
                [
                    'filename' => 'invoice.pdf',
                    'mimeType' => 'application/pdf',
                    'body' => ['attachmentId' => 'att-1', 'size' => 12345],
                ],
            ],
        ],
    ];

    $conversation = $this->parser->conversationFromMessage($this->account, $message);
    expect($conversation->gmailThreadId)->toBe('thread-1');
    expect($conversation->contactEmail)->toBe('jane@example.com');
    expect($conversation->contactName)->toBe('Jane Doe');
    expect($conversation->subject)->toBe('Refund question');

    $parsed = $this->parser->messageFromMessage($this->account, $message);
    expect($parsed->gmailMessageId)->toBe('msg-1');
    expect($parsed->direction)->toBe('inbound');
    expect($parsed->isInbound())->toBeTrue();
    expect($parsed->body)->toBe('Hello, I need a refund.');
    expect($parsed->messageIdHeader)->toBe('<abc123@mail.gmail.com>');
    expect($parsed->labelIds)->toBe(['INBOX', 'UNREAD']);
    expect($parsed->attachments)->toHaveCount(1);
    expect($parsed->attachments[0])->toBe([
        'id' => 'att-1',
        'filename' => 'invoice.pdf',
        'mime_type' => 'application/pdf',
        'size' => 12345,
    ]);
});

test('parses an outbound message: contact is the To header, direction outbound', function () {
    $message = [
        'id' => 'msg-2',
        'threadId' => 'thread-1',
        'labelIds' => ['SENT'],
        'internalDate' => '1700000100000',
        'payload' => [
            'mimeType' => 'text/plain',
            'headers' => [
                ['name' => 'From', 'value' => 'agent@example.com'],
                ['name' => 'To', 'value' => 'jane@example.com'],
                ['name' => 'Subject', 'value' => 'Re: Refund question'],
            ],
            'body' => ['data' => base64UrlEncode('Sure, refund processed.')],
        ],
    ];

    $conversation = $this->parser->conversationFromMessage($this->account, $message);
    expect($conversation->contactEmail)->toBe('jane@example.com');

    $parsed = $this->parser->messageFromMessage($this->account, $message);
    expect($parsed->direction)->toBe('outbound');
    expect($parsed->isInbound())->toBeFalse();
    expect($parsed->body)->toBe('Sure, refund processed.');
});

test('falls back to text/html and strips tags when there is no text/plain part', function () {
    $message = [
        'id' => 'msg-3',
        'threadId' => 'thread-2',
        'internalDate' => '1700000200000',
        'payload' => [
            'mimeType' => 'multipart/alternative',
            'headers' => [
                ['name' => 'From', 'value' => 'jane@example.com'],
                ['name' => 'To', 'value' => 'agent@example.com'],
            ],
            'parts' => [
                [
                    'mimeType' => 'text/html',
                    'body' => ['data' => base64UrlEncode('<p>Hello <b>world</b></p>')],
                ],
            ],
        ],
    ];

    $parsed = $this->parser->messageFromMessage($this->account, $message);
    expect($parsed->body)->toBe('Hello world');
});

test('falls back to the snippet when the body cannot be extracted', function () {
    $message = [
        'id' => 'msg-4',
        'threadId' => 'thread-3',
        'snippet' => 'Preview text',
        'internalDate' => '1700000300000',
        'payload' => [
            'headers' => [
                ['name' => 'From', 'value' => 'jane@example.com'],
                ['name' => 'To', 'value' => 'agent@example.com'],
            ],
        ],
    ];

    $parsed = $this->parser->messageFromMessage($this->account, $message);
    expect($parsed->body)->toBe('Preview text');
});

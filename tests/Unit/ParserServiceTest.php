<?php

use App\Services\ParserService;

beforeEach(function () {
    $this->parser = new ParserService();
});

test('parses a conversation from the search API shape', function () {
    $data = $this->parser->conversationFromSearchApi([
        'id' => 'ghl-conv-1',
        'locationId' => 'loc-1',
        'contactId' => 'contact-1',
        'contactName' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => '+1234567890',
        'lastMessageBody' => 'Hello, I need help with my order',
    ]);

    expect($data->ghlConversationId)->toBe('ghl-conv-1');
    expect($data->ghlLocationId)->toBe('loc-1');
    expect($data->contactId)->toBe('contact-1');
    expect($data->contactName)->toBe('Jane Doe');
    expect($data->contactEmail)->toBe('jane@example.com');
    expect($data->contactPhone)->toBe('+1234567890');
    expect($data->subject)->toBe('Hello, I need help with my order');
});

test('parses a conversation from the webhook event shape', function () {
    $data = $this->parser->conversationFromWebhookEvent([
        'conversationId' => 'ghl-conv-2',
        'locationId' => 'loc-2',
        'contactId' => 'contact-2',
        'contactName' => 'John Smith',
        'contactEmail' => 'john@example.com',
        'contactPhone' => '+10987654321',
        'subject' => 'Billing question',
    ]);

    expect($data->ghlConversationId)->toBe('ghl-conv-2');
    expect($data->contactEmail)->toBe('john@example.com');
    expect($data->subject)->toBe('Billing question');
});

test('parses a message from the search API shape', function () {
    $data = $this->parser->messageFromSearchApi([
        'id' => 'ghl-msg-1',
        'direction' => 'inbound',
        'body' => 'I was charged twice',
        'dateAdded' => '2026-07-27T10:00:00Z',
    ]);

    expect($data->ghlMessageId)->toBe('ghl-msg-1');
    expect($data->isInbound())->toBeTrue();
    expect($data->body)->toBe('I was charged twice');
    expect($data->sentAt->toIso8601String())->toBe('2026-07-27T10:00:00+00:00');
});

test('returns null when a search API message has no id', function () {
    $data = $this->parser->messageFromSearchApi([
        'direction' => 'inbound',
        'body' => 'no id here',
    ]);

    expect($data)->toBeNull();
});

test('parses a message from the webhook event shape, falling back from messageId to id', function () {
    $data = $this->parser->messageFromWebhookEvent([
        'id' => 'ghl-msg-2',
        'direction' => 'outbound',
        'message' => 'Thanks for reaching out',
        'dateAdded' => '2026-07-27T11:00:00Z',
    ]);

    expect($data->ghlMessageId)->toBe('ghl-msg-2');
    expect($data->isInbound())->toBeFalse();
    expect($data->body)->toBe('Thanks for reaching out');
});

test('returns null when a webhook event has neither messageId nor id', function () {
    $data = $this->parser->messageFromWebhookEvent([
        'conversationId' => 'ghl-conv-3',
        'body' => 'no message id',
    ]);

    expect($data)->toBeNull();
});

<?php

use App\Services\Ghl\GhlParserService;
use App\Services\Ghl\GoHighLevelApiService;
use Mockery;
use Tests\TestCase;

uses(TestCase::class);

it('parses contact details and latest preview from conversation search payloads', function () {
    $api = Mockery::mock(GoHighLevelApiService::class);
    $parser = new GhlParserService($api);

    $conversation = $parser->conversationFromSearchApi([
        'id' => 'conv-1',
        'locationId' => 'loc-1',
        'contactId' => 'contact-1',
        'contact' => [
            'id' => 'contact-1',
            'name' => 'Velchev Velchev',
            'email' => 'velchev@example.com',
            'phone' => '+628123456789',
        ],
        'lastMessage' => [
            'body' => 'Halo, saya butuh bantuan',
            'dateAdded' => '2024-05-01T10:15:30.000Z',
        ],
        'unreadCount' => 2,
        'channel' => 'TYPE_EMAIL',
    ]);

    expect($conversation)->not->toBeNull()
        ->and($conversation->contactName)->toBe('Velchev Velchev')
        ->and($conversation->contactEmail)->toBe('velchev@example.com')
        ->and($conversation->contactPhone)->toBe('+628123456789')
        ->and($conversation->latestMessagePreview)->toBe('Halo, saya butuh bantuan')
        ->and($conversation->unreadCount)->toBe(2)
        ->and($conversation->lastActivityAt?->toIso8601String())->toContain('2024-05-01');
});

it('hydrates email messages from meta.email.messageIds when the initial message has no body', function () {
    $api = Mockery::mock(GoHighLevelApiService::class);
    $api->shouldReceive('getEmailById')->once()->with('email-msg-123')->andReturn([
        'emailMessage' => [
            'body' => '<p>Balasan email</p>',
            'subject' => 'Re: Permohonan',
            'direction' => 'outbound',
        ],
    ]);

    $parser = new GhlParserService($api);

    $message = $parser->messageFromSearchApi([
        'id' => 'conversation-message-1',
        'messageType' => 'TYPE_EMAIL',
        'meta' => [
            'email' => [
                'messageIds' => ['email-msg-123'],
                'direction' => 'inbound',
            ],
        ],
        'conversationId' => 'conv-1',
        'dateAdded' => '2024-05-01T10:15:30.000Z',
    ]);

    expect($message)->not->toBeNull()
        ->and($message->body)->toContain('Balasan email')
        ->and($message->subject)->toBe('Re: Permohonan')
        ->and($message->direction)->toBe('outbound');
});

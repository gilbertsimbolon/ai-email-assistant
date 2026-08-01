<?php

use App\Enums\ConversationStatus;
use App\Enums\DraftStatus;
use App\Models\Conversation;
use App\Models\Draft;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Covers the GHL-direct migration (claude.txt): the Inbox must read
 * conversations/messages live from GHL and never mirror them into MySQL.
 * All GHL calls are faked — these tests never hit the real API.
 */
function fakeGhl(): void
{
    config([
        'ghl.api_key' => 'test-private-integration-token',
        'ghl.location_id' => 'loc-1',
    ]);
}

test('conversation list renders live from a faked GHL response, not the database', function () {
    fakeGhl();
    $user = User::factory()->create();

    Http::fake([
        'services.leadconnectorhq.com/conversations/search*' => Http::response([
            'conversations' => [[
                'id' => 'ghl-conv-1',
                'locationId' => 'loc-1',
                'contactId' => 'contact-1',
                'contactName' => 'Budi Santoso',
                'email' => 'budi@example.com',
                'phone' => '+6281234567890',
                'lastMessageBody' => 'Halo, saya mau tanya soal tagihan.',
                'lastMessageType' => 'TYPE_EMAIL',
                'unreadCount' => 1,
                'dateUpdated' => now()->toIso8601String(),
            ]],
        ], 200),
    ]);

    $response = $this->actingAs($user)->get(route('inbox.index'));

    $response->assertOk();
    $response->assertSee('Budi Santoso');
    $response->assertSee('Halo, saya mau tanya soal tagihan.');

    expect(Conversation::count())->toBe(0);
});

test('opening a GHL conversation lazily creates exactly one anchor row and no message rows', function () {
    fakeGhl();
    $user = User::factory()->create();

    Http::fake([
        'services.leadconnectorhq.com/conversations/search*' => Http::response(['conversations' => []], 200),
        'services.leadconnectorhq.com/conversations/ghl-conv-1/messages*' => Http::response([
            'messages' => [
                'messages' => [
                    [
                        'id' => 'ghl-msg-1',
                        'direction' => 'inbound',
                        'body' => 'Halo, saya mau tanya soal tagihan.',
                        'dateAdded' => now()->subMinute()->toIso8601String(),
                    ],
                ],
            ],
        ], 200),
        'services.leadconnectorhq.com/conversations/ghl-conv-1' => Http::response([
            'id' => 'ghl-conv-1',
            'locationId' => 'loc-1',
            'contactId' => 'contact-1',
            'contactName' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'lastMessageType' => 'TYPE_EMAIL',
            'unreadCount' => 0,
            'dateUpdated' => now()->toIso8601String(),
        ], 200),
    ]);

    $response = $this->actingAs($user)->get(route('inbox.index', ['conversation' => 'ghl-conv-1']));

    $response->assertOk();
    $response->assertSee('Halo, saya mau tanya soal tagihan.');

    expect(Conversation::count())->toBe(1);
    expect(Message::count())->toBe(0);

    $anchor = Conversation::first();
    expect($anchor->ghl_conversation_id)->toBe('ghl-conv-1');
    expect($anchor->is_read)->toBeTrue();
});

test('sending a draft on a non-email GHL conversation sends it as that channel, not hardcoded Email', function () {
    fakeGhl();
    $user = User::factory()->create();

    $conversation = Conversation::create([
        'ghl_conversation_id' => 'ghl-conv-2',
        'ghl_location_id' => 'loc-1',
        'contact_id' => 'contact-2',
        'contact_name' => 'Siti',
        'channel' => 'sms',
        'status' => ConversationStatus::PendingReview,
    ]);

    $draft = Draft::create([
        'conversation_id' => $conversation->id,
        'type' => 'sms',
        'provider' => 'manual',
        'content' => ['subject' => null, 'body' => 'Terima kasih, akan kami proses.'],
        'status' => DraftStatus::Active,
    ]);

    Http::fake([
        'services.leadconnectorhq.com/conversations/ghl-conv-2' => Http::response([
            'id' => 'ghl-conv-2',
            'lastMessageType' => 'TYPE_SMS',
        ], 200),
        'services.leadconnectorhq.com/conversations/messages' => Http::response(['messageId' => 'sent-1'], 200),
    ]);

    $this->actingAs($user)->post(route('inbox.drafts.approve', $draft))->assertRedirect();

    expect($draft->fresh()->status)->toBe(DraftStatus::Sent);
    expect($conversation->fresh()->status)->toBe(ConversationStatus::Replied);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://services.leadconnectorhq.com/conversations/messages'
            && $request['type'] === 'SMS'
            && ! array_key_exists('subject', $request->data());
    });
});

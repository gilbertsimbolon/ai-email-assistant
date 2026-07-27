<?php

use App\Enums\ChannelType;
use App\Enums\ConversationStatus;
use App\Enums\DraftStatus;
use App\Enums\MessageType;
use App\Enums\SenderType;
use App\Models\Conversation;
use App\Models\Draft;
use App\Models\Message;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * @return array{0: Conversation, 1: Draft}
 */
function makeConversationWithDraft(): array
{
    $conversation = Conversation::create([
        'ghl_conversation_id' => 'ghl-conv-x',
        'ghl_location_id' => 'loc-x',
        'contact_id' => 'contact-x',
        'contact_name' => 'Budi',
        'contact_email' => 'budi@example.com',
        'channel' => ChannelType::Email,
        'subject' => 'Pertanyaan tagihan',
        'status' => ConversationStatus::PendingReview,
        'last_message_at' => now(),
    ]);

    Message::create([
        'conversation_id' => $conversation->id,
        'ghl_message_id' => 'ghl-msg-x',
        'sender_type' => SenderType::Customer,
        'message_type' => MessageType::Email,
        'body' => 'Halo, saya mau tanya soal tagihan.',
        'sent_at' => now(),
    ]);

    $draft = Draft::create([
        'conversation_id' => $conversation->id,
        'type' => MessageType::Email,
        'provider' => 'openai',
        'content' => [
            'subject' => 'Re: Pertanyaan tagihan',
            'body' => 'Halo, berikut informasi tagihan Anda.',
            'tone' => null,
            'confidence' => null,
        ],
        'status' => DraftStatus::Active,
    ]);

    return [$conversation, $draft];
}

test('inbox detail page renders without error and shows the active draft', function () {
    [$conversation] = makeConversationWithDraft();

    $response = $this->get(route('inbox.show', $conversation));

    $response->assertOk();
    $response->assertSee('Halo, berikut informasi tagihan Anda.');
});

test('updating conversation status persists', function () {
    [$conversation] = makeConversationWithDraft();

    $this->put(route('inbox.status.update', $conversation), ['status' => 'closed'])
        ->assertRedirect();

    expect($conversation->fresh()->status)->toBe(ConversationStatus::Closed);
});

test('updating a draft persists subject and body', function () {
    [, $draft] = makeConversationWithDraft();

    $this->put(route('inbox.drafts.update', $draft), [
        'subject' => 'Subjek baru',
        'body' => 'Isi balasan baru.',
    ])->assertRedirect();

    $fresh = $draft->fresh();
    expect($fresh->content['subject'])->toBe('Subjek baru');
    expect($fresh->content['body'])->toBe('Isi balasan baru.');
});

test('approving a draft sends it via GHL and updates statuses', function () {
    [$conversation, $draft] = makeConversationWithDraft();

    Http::fake([
        'services.leadconnectorhq.com/*' => Http::response(['success' => true], 200),
        '*' => Http::response([], 200),
    ]);

    $this->post(route('inbox.drafts.approve', $draft))->assertRedirect();

    expect($draft->fresh()->status)->toBe(DraftStatus::Sent);
    expect($conversation->fresh()->status)->toBe(ConversationStatus::Replied);
});

test('a failed GHL send does not crash and leaves the draft unsent', function () {
    Sleep::fake();

    [, $draft] = makeConversationWithDraft();

    Http::fake([
        'services.leadconnectorhq.com/*' => Http::response(['message' => 'Server Error'], 500),
    ]);

    $response = $this->post(route('inbox.drafts.approve', $draft));

    $response->assertSessionHasErrors('draft');
    expect($draft->fresh()->status)->toBe(DraftStatus::Active);
});

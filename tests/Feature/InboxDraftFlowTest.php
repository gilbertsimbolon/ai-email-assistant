<?php

use App\Enums\ChannelType;
use App\Enums\ConversationStatus;
use App\Enums\DraftStatus;
use App\Enums\MessageType;
use App\Enums\SenderType;
use App\Models\Conversation;
use App\Models\Draft;
use App\Models\GmailAccount;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * @return array{0: Conversation, 1: Draft, 2: User}
 */
function makeConversationWithDraft(): array
{
    $user = User::factory()->create();

    $gmailAccount = GmailAccount::create([
        'user_id' => $user->id,
        'email' => 'agent@example.com',
        'access_token' => 'test-access-token',
        'history_id' => '1',
    ]);

    $conversation = Conversation::create([
        'gmail_account_id' => $gmailAccount->id,
        'gmail_thread_id' => 'thread-x',
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
        'gmail_message_id' => 'msg-x',
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

    return [$conversation, $draft, $user];
}

test('inbox detail page renders without error and shows the active draft', function () {
    [$conversation, , $user] = makeConversationWithDraft();

    $response = $this->actingAs($user)->get(route('gmail-inbox.show', $conversation));

    $response->assertOk();
    $response->assertSee('Halo, berikut informasi tagihan Anda.');
});

test('updating conversation status persists', function () {
    [$conversation, , $user] = makeConversationWithDraft();

    $this->actingAs($user)
        ->put(route('inbox.status.update', $conversation), ['status' => 'closed'])
        ->assertRedirect();

    expect($conversation->fresh()->status)->toBe(ConversationStatus::Closed);
});

test('updating a draft persists subject and body', function () {
    [, $draft, $user] = makeConversationWithDraft();

    $this->actingAs($user)
        ->put(route('inbox.drafts.update', $draft), [
            'subject' => 'Subjek baru',
            'body' => 'Isi balasan baru.',
        ])->assertRedirect();

    $fresh = $draft->fresh();
    expect($fresh->content['subject'])->toBe('Subjek baru');
    expect($fresh->content['body'])->toBe('Isi balasan baru.');
});

test('approving a draft sends it via Gmail and updates statuses', function () {
    [$conversation, $draft, $user] = makeConversationWithDraft();

    Http::fake([
        'gmail.googleapis.com/*' => Http::response(['id' => 'sent-1', 'threadId' => 'thread-x'], 200),
    ]);

    $this->actingAs($user)->post(route('inbox.drafts.approve', $draft))->assertRedirect();

    expect($draft->fresh()->status)->toBe(DraftStatus::Sent);
    expect($conversation->fresh()->status)->toBe(ConversationStatus::Replied);
});

test('a failed Gmail send does not crash and leaves the draft unsent', function () {
    Sleep::fake();

    [, $draft, $user] = makeConversationWithDraft();

    Http::fake([
        'gmail.googleapis.com/*' => Http::response(['error' => ['message' => 'Server Error']], 500),
    ]);

    $response = $this->actingAs($user)->post(route('inbox.drafts.approve', $draft));

    $response->assertSessionHasErrors('draft');
    expect($draft->fresh()->status)->toBe(DraftStatus::Active);
});

test('rejecting a draft discards it', function () {
    [, $draft, $user] = makeConversationWithDraft();

    $this->actingAs($user)
        ->post(route('inbox.drafts.reject', $draft))
        ->assertRedirect();

    expect($draft->fresh()->status)->toBe(DraftStatus::Discarded);
});

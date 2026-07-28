<?php

use App\Enums\ChannelType;
use App\Enums\ConversationStatus;
use App\Enums\DraftStatus;
use App\Enums\MessageType;
use App\Enums\SenderType;
use App\Models\Conversation;
use App\Models\GmailAccount;
use App\Models\Message;
use App\Models\User;
use App\Services\AI\Contracts\AiClientInterface;

/**
 * Fake AI client so these tests never hit a real provider — they only need
 * to assert how DraftController/DraftService/AiGenerationService orchestrate
 * and persist results, not what a real model would say.
 */
function fakeAiClient(string $draftBody = 'Terima kasih, berikut jawabannya.'): void
{
    test()->app->instance(AiClientInterface::class, new class($draftBody) implements AiClientInterface
    {
        public function __construct(private string $draftBody)
        {
        }

        public function chat(array $messages): array
        {
            return ['content' => $this->draftBody, 'usage' => [], 'model' => 'fake'];
        }

        public function json(array $messages): array
        {
            return [
                'language' => 'Indonesian',
                'intent' => 'General Inquiry',
                'priority' => 'Medium',
                'sentiment' => 'Neutral',
                'customer_status' => 'unknown',
                'needs_escalation' => false,
                'refund_requested' => false,
                'summary' => 'Pelanggan bertanya soal tagihan.',
                'last_customer_request' => 'Info tagihan',
                'recommended_action' => 'Balas dengan info tagihan',
                'confidence_score' => 0.8,
            ];
        }
    });
}

/**
 * @return array{0: Conversation, 1: User}
 */
function makeEmailConversation(?string $channel = null): array
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
        'gmail_thread_id' => 'thread-gen',
        'contact_id' => 'contact-gen',
        'contact_name' => 'Budi',
        'contact_email' => 'budi@example.com',
        'channel' => $channel ?? ChannelType::Email,
        'subject' => 'Pertanyaan tagihan',
        'status' => ConversationStatus::PendingReview,
        'last_message_at' => now(),
    ]);

    Message::create([
        'conversation_id' => $conversation->id,
        'gmail_message_id' => 'msg-gen-1',
        'sender_type' => SenderType::Customer,
        'message_type' => MessageType::Email,
        'body' => 'Halo, saya mau tanya soal tagihan.',
        'sent_at' => now(),
    ]);

    return [$conversation, $user];
}

test('first generate creates version 1 active draft', function () {
    fakeAiClient('Halo, berikut info tagihan Anda.');
    [$conversation, $user] = makeEmailConversation();

    $response = $this->actingAs($user)
        ->postJson(route('inbox.drafts.generate', $conversation), []);

    $response->assertOk();
    $response->assertJsonPath('draft.version', 1);
    $response->assertJsonPath('draft.status', 'active');
    $response->assertJsonPath('draft.body', 'Halo, berikut info tagihan Anda.');

    expect($conversation->drafts()->count())->toBe(1);
    expect($conversation->fresh()->analysis)->not->toBeNull();
});

test('regenerate without as_new_version replaces the draft in place', function () {
    fakeAiClient('Draft pertama.');
    [$conversation, $user] = makeEmailConversation();

    $first = $this->actingAs($user)->postJson(route('inbox.drafts.generate', $conversation), [])->json('draft');

    fakeAiClient('Draft kedua, menggantikan yang pertama.');
    $second = $this->actingAs($user)
        ->postJson(route('inbox.drafts.generate', $conversation), ['as_new_version' => false])
        ->json('draft');

    expect($second['id'])->toBe($first['id']);
    expect($second['version'])->toBe(1);
    expect($conversation->drafts()->count())->toBe(1);
    expect($conversation->drafts()->first()->content['body'])->toBe('Draft kedua, menggantikan yang pertama.');
});

test('regenerate with as_new_version creates version 2 and supersedes version 1', function () {
    fakeAiClient('Draft pertama.');
    [$conversation, $user] = makeEmailConversation();

    $first = $this->actingAs($user)->postJson(route('inbox.drafts.generate', $conversation), [])->json('draft');

    fakeAiClient('Draft versi baru.');
    $second = $this->actingAs($user)
        ->postJson(route('inbox.drafts.generate', $conversation), ['as_new_version' => true])
        ->json('draft');

    expect($second['id'])->not->toBe($first['id']);
    expect($second['version'])->toBe(2);
    expect($conversation->drafts()->count())->toBe(2);

    $conversation->refresh();
    expect($conversation->drafts()->find($first['id'])->status)->toBe(DraftStatus::Regenerated);
    expect($conversation->drafts()->find($second['id'])->status)->toBe(DraftStatus::Active);
});

test('generate on a conversation with no messages returns an error', function () {
    fakeAiClient();
    $user = User::factory()->create();
    $gmailAccount = GmailAccount::create([
        'user_id' => $user->id,
        'email' => 'agent@example.com',
        'access_token' => 'token',
        'history_id' => '1',
    ]);
    $conversation = Conversation::create([
        'gmail_account_id' => $gmailAccount->id,
        'gmail_thread_id' => 'thread-empty',
        'contact_id' => 'contact-empty',
        'channel' => ChannelType::Email,
        'status' => ConversationStatus::PendingReview,
        'last_message_at' => now(),
    ]);

    $this->actingAs($user)
        ->postJson(route('inbox.drafts.generate', $conversation), [])
        ->assertStatus(500);
});

test('generate on a whatsapp conversation is not found', function () {
    fakeAiClient();
    [$conversation, $user] = makeEmailConversation(ChannelType::WhatsApp->value);

    $this->actingAs($user)
        ->postJson(route('inbox.drafts.generate', $conversation), [])
        ->assertNotFound();
});

test('generate on someone else conversation is forbidden', function () {
    fakeAiClient();
    [$conversation] = makeEmailConversation();
    $otherUser = User::factory()->create();

    $this->actingAs($otherUser)
        ->postJson(route('inbox.drafts.generate', $conversation), [])
        ->assertForbidden();
});

test('send without an existing draft creates a manual draft and sends it', function () {
    [$conversation, $user] = makeEmailConversation();

    Illuminate\Support\Facades\Http::fake([
        'gmail.googleapis.com/*' => Illuminate\Support\Facades\Http::response(['id' => 'sent-1', 'threadId' => 'thread-gen'], 200),
    ]);

    $response = $this->actingAs($user)->postJson(route('inbox.drafts.send', $conversation), [
        'subject' => 'Re: Pertanyaan tagihan',
        'body' => 'Balasan manual tanpa AI.',
    ]);

    $response->assertOk();
    $response->assertJsonPath('status', 'sent');

    $draft = $conversation->drafts()->first();
    expect($draft->provider)->toBe('manual');
    expect($draft->status)->toBe(DraftStatus::Sent);
    expect($conversation->fresh()->status)->toBe(ConversationStatus::Replied);
});

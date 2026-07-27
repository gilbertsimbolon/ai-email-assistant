<?php

use App\Events\MessageReceived;
use App\Models\Conversation;
use App\Models\GmailAccount;
use App\Models\Message;
use App\Models\User;
use App\Services\Gmail\GmailSyncService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

function fakeGmailMessage(string $id, string $threadId, string $from, string $to, string $body, string $subject = 'Halo'): array
{
    return [
        'id' => $id,
        'threadId' => $threadId,
        'labelIds' => ['INBOX'],
        'snippet' => $body,
        'internalDate' => '1700000000000',
        'payload' => [
            'mimeType' => 'text/plain',
            'headers' => [
                ['name' => 'From', 'value' => $from],
                ['name' => 'To', 'value' => $to],
                ['name' => 'Subject', 'value' => $subject],
                ['name' => 'Message-ID', 'value' => "<{$id}@mail.gmail.com>"],
            ],
            'body' => ['data' => rtrim(strtr(base64_encode($body), '+/', '-_'), '=')],
        ],
    ];
}

test('full sync (no stored historyId) backfills inbox messages and dispatches MessageReceived', function () {
    Event::fake([MessageReceived::class]);

    $account = GmailAccount::create([
        'user_id' => User::factory()->create()->id,
        'email' => 'agent@example.com',
        'access_token' => 'token',
        'history_id' => null,
    ]);

    Http::fake([
        'gmail.googleapis.com/gmail/v1/users/me/messages?*' => Http::response(['messages' => [['id' => 'msg-1']]], 200),
        'gmail.googleapis.com/gmail/v1/users/me/messages/msg-1*' => Http::response(
            fakeGmailMessage('msg-1', 'thread-1', 'jane@example.com', 'agent@example.com', 'Saya butuh bantuan.'),
            200
        ),
        'gmail.googleapis.com/gmail/v1/users/me/profile' => Http::response([
            'emailAddress' => 'agent@example.com',
            'historyId' => '500',
        ], 200),
    ]);

    app(GmailSyncService::class)->sync($account);

    expect(Conversation::count())->toBe(1);
    expect(Message::where('gmail_message_id', 'msg-1')->exists())->toBeTrue();
    expect($account->fresh()->history_id)->toBe('500');

    Event::assertDispatched(MessageReceived::class);
});

test('incremental sync only pulls what changed since the stored historyId', function () {
    Event::fake([MessageReceived::class]);

    $account = GmailAccount::create([
        'user_id' => User::factory()->create()->id,
        'email' => 'agent@example.com',
        'access_token' => 'token',
        'history_id' => '400',
    ]);

    Http::fake([
        'gmail.googleapis.com/gmail/v1/users/me/history*' => Http::response([
            'history' => [
                ['messagesAdded' => [['message' => ['id' => 'msg-2']]]],
            ],
            'historyId' => '600',
        ], 200),
        'gmail.googleapis.com/gmail/v1/users/me/messages/msg-2*' => Http::response(
            fakeGmailMessage('msg-2', 'thread-2', 'jane@example.com', 'agent@example.com', 'Follow up pertanyaan.'),
            200
        ),
    ]);

    app(GmailSyncService::class)->sync($account);

    expect(Message::where('gmail_message_id', 'msg-2')->exists())->toBeTrue();
    expect($account->fresh()->history_id)->toBe('600');
});

test('falls back to a full sync when the stored historyId has expired', function () {
    Event::fake([MessageReceived::class]);

    $account = GmailAccount::create([
        'user_id' => User::factory()->create()->id,
        'email' => 'agent@example.com',
        'access_token' => 'token',
        'history_id' => 'stale-id',
    ]);

    Http::fake([
        'gmail.googleapis.com/gmail/v1/users/me/history*' => Http::response(['error' => ['message' => 'Not found']], 404),
        'gmail.googleapis.com/gmail/v1/users/me/messages?*' => Http::response(['messages' => [['id' => 'msg-3']]], 200),
        'gmail.googleapis.com/gmail/v1/users/me/messages/msg-3*' => Http::response(
            fakeGmailMessage('msg-3', 'thread-3', 'jane@example.com', 'agent@example.com', 'Pesan baru.'),
            200
        ),
        'gmail.googleapis.com/gmail/v1/users/me/profile' => Http::response([
            'emailAddress' => 'agent@example.com',
            'historyId' => '700',
        ], 200),
    ]);

    app(GmailSyncService::class)->sync($account);

    expect(Message::where('gmail_message_id', 'msg-3')->exists())->toBeTrue();
    expect($account->fresh()->history_id)->toBe('700');
});

<?php

namespace App\Services;

use App\Enums\ChannelType;
use App\Enums\ConversationStatus;
use App\Enums\MessageType;
use App\Enums\SenderType;
use App\Models\Conversation;
use App\Models\Message;
use Carbon\Carbon;
use Illuminate\Support\Str;

class GhlSyncService
{
    public function __construct(
        protected GoHighLevelService $ghl,
        protected ConversationService $conversationService,
    ) {
    }

    /**
     * Tarik conversations email dari GHL, simpan/perbarui secara lokal,
     * lalu trigger draft AI untuk conversation yang punya pesan customer baru.
     */
    public function sync(): void
    {
        $result = $this->ghl->getConversations(['limit' => 100]);

        foreach ($result['conversations'] ?? [] as $raw) {
            if (!Str::contains(strtolower($raw['type'] ?? $raw['lastMessageType'] ?? ''), 'email')) {
                continue;
            }

            $conversation = $this->upsertConversation($raw);

            $remoteUpdatedAt = isset($raw['dateUpdated'])
                ? Carbon::parse($raw['dateUpdated'])
                : now();

            if ($conversation->synced_at && $conversation->synced_at->gte($remoteUpdatedAt)) {
                continue;
            }

            $hasNewCustomerMessage = $this->syncMessages($conversation, $raw['id']);

            $conversation->update(['synced_at' => now()]);

            if ($hasNewCustomerMessage) {
                $this->conversationService->triggerAiReply($conversation->fresh());
            }
        }
    }

    protected function upsertConversation(array $raw): Conversation
    {
        $existing = Conversation::where('ghl_conversation_id', $raw['id'])->first();

        return Conversation::updateOrCreate(
            ['ghl_conversation_id' => $raw['id']],
            [
                'ghl_location_id' => $raw['locationId'] ?? config('ghl.location_id'),
                'contact_id' => $raw['contactId'] ?? null,
                'contact_name' => $raw['contactName'] ?? $raw['fullName'] ?? null,
                'contact_email' => $raw['email'] ?? $existing?->contact_email,
                'contact_phone' => $raw['phone'] ?? $existing?->contact_phone,
                'channel' => ChannelType::Email,
                'subject' => $existing?->subject ?? ($raw['lastMessageBody'] ?? null ? Str::limit($raw['lastMessageBody'], 100) : null),
                'status' => $existing?->status ?? ConversationStatus::PendingReview,
            ]
        );
    }

    /**
     * Ambil dan simpan pesan baru untuk satu conversation.
     *
     * @return bool true jika ada pesan baru dari customer (inbound).
     */
    protected function syncMessages(Conversation $conversation, string $ghlConversationId): bool
    {
        $result = $this->ghl->getConversationMessages($ghlConversationId);
        $messages = data_get($result, 'messages.messages', $result['messages'] ?? []);

        $hasNewCustomerMessage = false;
        $latestSentAt = $conversation->last_message_at;

        foreach ($messages as $msg) {
            if (!isset($msg['id']) || Message::where('ghl_message_id', $msg['id'])->exists()) {
                continue;
            }

            $direction = $msg['direction'] ?? 'inbound';
            $sentAt = isset($msg['dateAdded']) ? Carbon::parse($msg['dateAdded']) : now();

            $conversation->messages()->create([
                'ghl_message_id' => $msg['id'],
                'sender_type' => $direction === 'inbound' ? SenderType::Customer : SenderType::Agent,
                'message_type' => MessageType::Email,
                'body' => $msg['body'] ?? '',
                'sent_at' => $sentAt,
            ]);

            if ($direction === 'inbound') {
                $hasNewCustomerMessage = true;
            }

            if (!$latestSentAt || $sentAt->gt($latestSentAt)) {
                $latestSentAt = $sentAt;
            }
        }

        if ($latestSentAt && $latestSentAt !== $conversation->last_message_at) {
            $conversation->update(['last_message_at' => $latestSentAt]);
        }

        return $hasNewCustomerMessage;
    }
}

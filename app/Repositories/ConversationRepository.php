<?php

namespace App\Repositories;

use App\DataTransferObjects\ParsedConversationData;
use App\DataTransferObjects\ParsedMessageData;
use App\Enums\ChannelType;
use App\Enums\ConversationStatus;
use App\Enums\MessageType;
use App\Enums\SenderType;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Str;

/**
 * Centralizes the conversation/message upsert rules that both GhlSyncService
 * (polling) and WebhookService (realtime) need identically — most importantly,
 * never clobbering the status an agent has already set on an existing conversation.
 */
class ConversationRepository
{
    public function upsertConversation(ParsedConversationData $data): Conversation
    {
        $existing = Conversation::where('ghl_conversation_id', $data->ghlConversationId)->first();

        return Conversation::updateOrCreate(
            ['ghl_conversation_id' => $data->ghlConversationId],
            [
                'ghl_location_id' => $data->ghlLocationId ?? $existing?->ghl_location_id ?? config('ghl.location_id'),
                'contact_id' => $data->contactId ?? $existing?->contact_id,
                'contact_name' => $data->contactName ?? $existing?->contact_name,
                'contact_email' => $data->contactEmail ?? $existing?->contact_email,
                'contact_phone' => $data->contactPhone ?? $existing?->contact_phone,
                'channel' => ChannelType::Email,
                'subject' => $existing?->subject ?? ($data->subject ? Str::limit($data->subject, 100) : null),
                // Only applies to a brand-new conversation — never resets the
                // status an agent has already set (replied/closed) on an existing one.
                'status' => $existing?->status ?? ConversationStatus::PendingReview,
            ]
        );
    }

    /**
     * @return Message|null null if this message was already recorded (dedup by ghl_message_id).
     */
    public function recordMessage(Conversation $conversation, ParsedMessageData $data): ?Message
    {
        if (Message::where('ghl_message_id', $data->ghlMessageId)->exists()) {
            return null;
        }

        $message = $conversation->messages()->create([
            'ghl_message_id' => $data->ghlMessageId,
            'sender_type' => $data->isInbound() ? SenderType::Customer : SenderType::Agent,
            'message_type' => MessageType::Email,
            'body' => $data->body,
            'sent_at' => $data->sentAt,
        ]);

        if (!$conversation->last_message_at || $data->sentAt->gt($conversation->last_message_at)) {
            $conversation->update(['last_message_at' => $data->sentAt]);
        }

        return $message;
    }
}

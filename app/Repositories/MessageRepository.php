<?php

namespace App\Repositories;

use App\DataTransferObjects\ParsedMessageData;
use App\Enums\MessageType;
use App\Enums\SenderType;
use App\Models\Conversation;
use App\Models\Message;

/**
 * Centralizes message persistence rules GmailSyncService relies on —
 * dedup by gmail_message_id and keeping conversation.last_message_at accurate.
 */
class MessageRepository
{
    /**
     * @return Message|null null if this message was already recorded (dedup by gmail_message_id).
     */
    public function recordMessage(Conversation $conversation, ParsedMessageData $data): ?Message
    {
        if (Message::where('gmail_message_id', $data->gmailMessageId)->exists()) {
            return null;
        }

        $message = $conversation->messages()->create([
            'gmail_message_id' => $data->gmailMessageId,
            'message_id_header' => $data->messageIdHeader,
            'sender_type' => $data->isInbound() ? SenderType::Customer : SenderType::Agent,
            'message_type' => MessageType::Email,
            'body' => $data->body,
            'attachments' => $data->attachments,
            'label_ids' => $data->labelIds,
            'sent_at' => $data->sentAt,
        ]);

        if (!$conversation->last_message_at || $data->sentAt->gt($conversation->last_message_at)) {
            $conversation->update(['last_message_at' => $data->sentAt]);
        }

        return $message;
    }
}

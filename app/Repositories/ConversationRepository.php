<?php

namespace App\Repositories;

use App\DataTransferObjects\ParsedConversationData;
use App\DataTransferObjects\ParsedGhlConversationData;
use App\Enums\ChannelType;
use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Models\GmailAccount;
use Illuminate\Support\Str;

/**
 * Centralizes the conversation upsert rules GmailSyncService relies on —
 * most importantly, never clobbering the status an agent has already set on
 * an existing conversation.
 */
class ConversationRepository
{
    public function upsertConversation(GmailAccount $account, ParsedConversationData $data): Conversation
    {
        $existing = Conversation::where('gmail_account_id', $account->id)
            ->where('gmail_thread_id', $data->gmailThreadId)
            ->first();

        return Conversation::updateOrCreate(
            [
                'gmail_account_id' => $account->id,
                'gmail_thread_id' => $data->gmailThreadId,
            ],
            [
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
     * GHL-sourced conversations are shared across every agent (one Private
     * Integration per location, no per-user OAuth account), unlike
     * upsertConversation() above which scopes by Gmail account ownership.
     */
    public function upsertGhlConversation(ParsedGhlConversationData $data): Conversation
    {
        $existing = Conversation::where('ghl_conversation_id', $data->ghlConversationId)->first();

        return Conversation::updateOrCreate(
            ['ghl_conversation_id' => $data->ghlConversationId],
            [
                'ghl_location_id' => $data->ghlLocationId,
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
}

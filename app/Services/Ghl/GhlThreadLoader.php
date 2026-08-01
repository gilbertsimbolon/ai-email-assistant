<?php

namespace App\Services\Ghl;

use App\Enums\SenderType;
use App\Models\Message;
use Illuminate\Support\Collection;

/**
 * Builds a conversation's message thread live from GHL on every call — never
 * persisted (claude.txt section 3, "GHL adalah source of truth"). Messages
 * are wrapped in ephemeral, unsaved Message model instances purely so the
 * existing ConversationThreadFormatter::format(Collection<Message>) and the
 * thread Blade partials don't need a second code path — the same trick
 * AiCenterPlaygroundController already uses for its unsaved
 * Conversation/Analysis.
 */
class GhlThreadLoader
{
    public function __construct(
        protected GoHighLevelApiService $api,
        protected GhlParserService $parser,
    ) {
    }

    /**
     * @return Collection<int, Message> oldest message first
     */
    public function messages(string $ghlConversationId): Collection
    {
        $result = $this->api->getConversationMessages($ghlConversationId, ['limit' => 100]);
        $rawMessages = data_get($result, 'messages.messages', $result['messages'] ?? []);

        return collect($rawMessages)
            ->map(fn (array $raw) => $this->parser->messageFromSearchApi($raw))
            ->filter()
            ->map(fn ($data) => new Message([
                'ghl_message_id' => $data->ghlMessageId,
                'sender_type' => $data->isInbound() ? SenderType::Customer : SenderType::Agent,
                'body' => $data->body,
                'attachments' => $data->attachments,
                'sent_at' => $data->sentAt,
            ]))
            ->sortBy('sent_at')
            ->values();
    }
}

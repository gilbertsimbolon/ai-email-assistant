<?php

namespace App\Services\Ghl;

use App\Enums\SenderType;
use App\Models\Message;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class GhlThreadLoader
{
    /**
     * Cukup panggil 1 page pesan terbaru dari GHL (100 pesan terakhir).
     */
    protected const PAGE_SIZE = 100;

    public function __construct(
        protected GoHighLevelApiService $api,
        protected GhlParserService $parser,
    ) {}

    /**
     * Load messages returned by GHL for this conversation.
     *
     * @return Collection<int, Message>
     */
    public function messages(string $ghlConversationId): Collection
    {
        try {
            $result = $this->api->getConversationMessages(
                $ghlConversationId,
                ['limit' => self::PAGE_SIZE]
            );
        } catch (Throwable $e) {
            Log::error('Failed to load GHL conversation messages', [
                'conversation_id' => $ghlConversationId,
                'error' => $e->getMessage(),
            ]);

            return collect();
        }

        $rawMessages = $this->extractMessages($result);

        if ($rawMessages->isEmpty()) {
            return collect();
        }

        $messages = collect();
        $seenMessageIds = [];

        foreach ($rawMessages as $raw) {
            if (!is_array($raw)) {
                continue;
            }

            $messageId = $raw['id'] ?? null;

            if (blank($messageId) || isset($seenMessageIds[$messageId])) {
                continue;
            }

            $seenMessageIds[$messageId] = true;

            try {
                $data = $this->parser->messageFromSearchApi($raw);
            } catch (Throwable $e) {
                Log::error('GHL MESSAGE PARSE FAILED', [
                    'conversation_id' => $ghlConversationId,
                    'message_id' => $messageId,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            if (!$data) {
                continue;
            }

            $messages->push(
                new Message([
                    'ghl_message_id' => $data->ghlMessageId,
                    'sender_type' => $data->isInbound()
                        ? SenderType::Customer
                        : SenderType::Agent,
                    'body' => $data->body,
                    'attachments' => $data->attachments,
                    'sent_at' => $data->sentAt,
                ])
            );
        }

        // Urutkan dari pesan terlama ke pesan terbaru
        return $messages
            ->sortBy(fn (Message $message) => $message->sent_at?->timestamp ?? 0)
            ->values();
    }

    /**
     * Ekstrak list pesan secara fleksibel dari berbagai bentuk JSON GHL.
     */
    protected function extractMessages(array $result): Collection
    {
        if (array_is_list($result)) {
            return collect($result);
        }

        // Struktur bersarang GHL: {"messages": {"messages": [...]}}
        $nestedMessages = data_get($result, 'messages.messages');
        if (is_array($nestedMessages)) {
            return collect($nestedMessages);
        }

        $messages = data_get($result, 'messages');
        if (is_array($messages) && array_is_list($messages)) {
            return collect($messages);
        }

        $data = data_get($result, 'data');
        if (is_array($data) && array_is_list($data)) {
            return collect($data);
        }

        return collect();
    }
}
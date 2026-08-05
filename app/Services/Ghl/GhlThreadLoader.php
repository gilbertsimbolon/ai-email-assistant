<?php

namespace App\Services\Ghl;

use App\Enums\SenderType;
use App\Models\Message;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Builds a conversation's message thread live from GHL.
 *
 * GHL remains the source of truth. Messages are never persisted locally.
 *
 * The loader walks through GHL's message pagination using lastMessageId
 * until every available message has been fetched.
 */
class GhlThreadLoader
{
    /**
     * Number of messages requested per API call.
     */
    protected const PAGE_SIZE = 100;

    /**
     * Safety limit so a broken pagination cursor can never cause an
     * infinite loop.
     */
    protected const MAX_PAGES = 100;

    public function __construct(
        protected GoHighLevelApiService $api,
        protected GhlParserService $parser,
    ) {}

    /**
     * Load ALL messages belonging to a GHL conversation.
     *
     * @return Collection<int, Message> oldest message first
     */
    // GhlThreadLoader.php

    public function messages(string $ghlConversationId): Collection
    {
        $allMessages = collect();
        $lastMessageId = null;
        $seenMessageIds = [];

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $params = ['limit' => self::PAGE_SIZE];

            if ($lastMessageId !== null) {
                $params['lastMessageId'] = $lastMessageId;
            }

            try {
                $result = $this->api->getConversationMessages($ghlConversationId, $params);
            } catch (Throwable $e) {
                Log::error('Failed to load GHL conversation message page', [
                    'ghl_conversation_id' => $ghlConversationId,
                    'page' => $page,
                    'error' => $e->getMessage(),
                ]);
                break;
            }

            $rawMessages = $this->extractMessages($result);

            if ($rawMessages->isEmpty()) {
                break; // Benar-benar habis
            }

            foreach ($rawMessages as $raw) {
                if (! is_array($raw)) {
                    continue;
                }

                $messageId = $raw['id'] ?? null;
                if (blank($messageId) || isset($seenMessageIds[$messageId])) {
                    continue;
                }

                $seenMessageIds[$messageId] = true;
                $data = $this->parser->messageFromSearchApi($raw);

                if (! $data) {
                    // Jangan hentikan loop utama jika parser mengabaikan item ini
                    continue;
                }

                $allMessages->push(
                    new Message([
                        'ghl_message_id' => $data->ghlMessageId,
                        'sender_type'    => $data->isInbound() ? SenderType::Customer : SenderType::Agent,
                        'body'           => $data->body,
                        'attachments'    => $data->attachments,
                        'sent_at'        => $data->sentAt,
                    ])
                );
            }

            // Ambil ID pesan terakhir pada page ini untuk cursor berikutnya
            $lastRawMessage = $rawMessages->last();
            $nextMessageId = is_array($lastRawMessage) ? ($lastRawMessage['id'] ?? null) : null;

            // Berhenti jika tidak ada cursor baru atau cursor berulang
            if (blank($nextMessageId) || $nextMessageId === $lastMessageId) {
                break;
            }

            $lastMessageId = $nextMessageId;

            // Jika jumlah pesan yang diterima dari API kurang dari limit, berarti ini page terakhir
            if ($rawMessages->count() < self::PAGE_SIZE) {
                break;
            }
        }

        // Sorting berdasarkan tanggal (gunakan fallback timestamp dari ID / now() jika sent_at null)
        return $allMessages
            ->sortBy(function (Message $message) {
                return $message->sent_at?->getTimestamp() ?? PHP_INT_MAX;
            })
            ->values();
    }

    /**
     * Normalize the various possible GHL message response wrappers.
     *
     * Supported shapes:
     *
     * {
     *     "messages": [...]
     * }
     *
     * {
     *     "messages": {
     *         "messages": [...]
     *     }
     * }
     *
     * {
     *     "messages": {
     *         "messages": {
     *             ...
     *         }
     *     }
     * }
     */
    protected function extractMessages(array $result): Collection
    {
        $messages = data_get($result, 'messages.messages');

        if (is_array($messages)) {
            return collect($messages);
        }

        $messages = $result['messages'] ?? null;

        if (is_array($messages)) {
            /*
             * Sometimes "messages" itself can be an associative wrapper.
             */
            if (array_is_list($messages)) {
                return collect($messages);
            }

            if (isset($messages['messages']) && is_array($messages['messages'])) {
                return collect($messages['messages']);
            }
        }

        /*
         * Defensive fallback in case the API returns the message array
         * directly.
         */
        if (array_is_list($result)) {
            return collect($result);
        }

        return collect();
    }
}

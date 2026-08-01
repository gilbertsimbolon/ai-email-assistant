<?php

namespace App\Services\Ghl;

use App\Events\MessageReceived;
use App\Models\Conversation;
use App\Repositories\ConversationRepository;
use App\Repositories\MessageRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Polls GHL for conversations/messages and mirrors them into the local
 * Conversation/Message tables the rest of Inbox (and AI Center) already
 * reads from. Incremental: conversations come back newest-updated-first, so
 * a normal run stops paginating as soon as it hits one that's already synced.
 *
 * AI processing is never triggered here — per claude.txt, "AI Process" stays
 * strictly on-demand (button click), never automatic on sync.
 */
class GhlSyncService
{
    /**
     * Safety cap on how many pages of conversations a single sync run will walk,
     * so a GHL API/pagination bug can't turn into an infinite loop.
     */
    protected const MAX_PAGES = 20;

    protected const PAGE_SIZE = 100;

    public function __construct(
        protected GoHighLevelApiService $api,
        protected GhlParserService $parser,
        protected ConversationRepository $conversations,
        protected MessageRepository $messages,
    ) {
    }

    public function sync(): void
    {
        Log::info('GHL sync started');

        $processed = 0;
        $created = 0;
        $updated = 0;
        $page = 0;
        $cursor = [];

        do {
            $result = $this->api->getConversations(array_merge(['limit' => self::PAGE_SIZE], $cursor));
            $rawConversations = $result['conversations'] ?? [];

            if (empty($rawConversations)) {
                break;
            }

            $reachedSynced = false;

            foreach ($rawConversations as $raw) {
                if (!isset($raw['id']) || !Str::contains(strtolower($raw['type'] ?? $raw['lastMessageType'] ?? ''), 'email')) {
                    continue;
                }

                $wasExisting = Conversation::where('ghl_conversation_id', $raw['id'])->exists();

                $conversation = $this->conversations->upsertGhlConversation(
                    $this->parser->conversationFromSearchApi($raw)
                );

                $remoteUpdatedAt = isset($raw['dateUpdated'])
                    ? Carbon::parse($raw['dateUpdated'])
                    : now();

                if ($conversation->synced_at && $conversation->synced_at->gte($remoteUpdatedAt)) {
                    // Results are newest-updated-first: once we hit an already-synced
                    // conversation, everything after it on this page is stale too.
                    $reachedSynced = true;

                    continue;
                }

                $this->syncMessages($conversation, $raw['id']);

                $conversation->update(['synced_at' => now()]);

                $wasExisting ? $updated++ : $created++;
                $processed++;

                Log::info($wasExisting ? 'GHL conversation updated' : 'GHL conversation created', [
                    'conversation_id' => $conversation->id,
                    'ghl_conversation_id' => $conversation->ghl_conversation_id,
                ]);
            }

            if ($reachedSynced || count($rawConversations) < self::PAGE_SIZE) {
                break;
            }

            // NOTE: `startAfterDate`/`startAfter` follow GHL API v2's documented
            // cursor-pagination convention for /conversations/search.
            $last = end($rawConversations);
            $cursor = [
                'startAfterDate' => $last['dateUpdated'] ?? null,
                'startAfter' => $last['id'] ?? null,
            ];

            $page++;
        } while ($page < self::MAX_PAGES);

        Log::info('GHL sync finished', [
            'processed' => $processed,
            'created' => $created,
            'updated' => $updated,
            'pages_fetched' => $page + 1,
        ]);
    }

    protected function syncMessages(Conversation $conversation, string $ghlConversationId): void
    {
        $result = $this->api->getConversationMessages($ghlConversationId, ['limit' => 100]);
        $rawMessages = data_get($result, 'messages.messages', $result['messages'] ?? []);

        foreach ($rawMessages as $raw) {
            $messageData = $this->parser->messageFromSearchApi($raw);

            if (!$messageData) {
                continue;
            }

            $message = $this->messages->recordGhlMessage($conversation, $messageData);

            if (!$message) {
                // Already recorded (dedup by ghl_message_id).
                continue;
            }

            Log::info('GHL message created', [
                'conversation_id' => $conversation->id,
                'direction' => $messageData->direction,
            ]);

            if ($messageData->isInbound()) {
                // A new customer message makes the conversation unread again,
                // even if an agent had already read/replied to earlier messages.
                $conversation->update(['is_read' => false]);

                MessageReceived::dispatch($conversation->fresh());
            }
        }
    }
}

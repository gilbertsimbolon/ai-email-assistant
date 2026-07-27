<?php

namespace App\Services;

use App\Models\Conversation;
use App\Repositories\ConversationRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GhlSyncService
{
    /**
     * Safety cap on how many pages of conversations a single sync run will walk,
     * so a GHL API/pagination bug can't turn into an infinite loop.
     */
    protected const MAX_PAGES = 20;

    protected const PAGE_SIZE = 100;

    public function __construct(
        protected GoHighLevelService $ghl,
        protected ParserService $parser,
        protected ConversationRepository $conversations,
        protected ConversationService $conversationService,
    ) {
    }

    /**
     * Tarik conversations email dari GHL, simpan/perbarui secara lokal,
     * lalu trigger draft AI untuk conversation yang punya pesan customer baru.
     *
     * Incremental: conversations are requested newest-updated-first, and we stop
     * paginating as soon as we hit one that's already synced locally, so a normal
     * run only touches conversations that actually changed since the last sync.
     */
    public function sync(): void
    {
        Log::info('GHL sync started');

        $processed = 0;
        $created = 0;
        $updated = 0;
        $page = 0;
        $cursor = [];

        do {
            $result = $this->ghl->getConversations(array_merge(['limit' => self::PAGE_SIZE], $cursor));
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

                $conversation = $this->conversations->upsertConversation(
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

                $hasNewCustomerMessage = $this->syncMessages($conversation, $raw['id']);

                $conversation->update(['synced_at' => now()]);

                $wasExisting ? $updated++ : $created++;
                $processed++;

                Log::info($wasExisting ? 'GHL conversation updated' : 'GHL conversation created', [
                    'conversation_id' => $conversation->id,
                    'ghl_conversation_id' => $conversation->ghl_conversation_id,
                ]);

                if ($hasNewCustomerMessage) {
                    $this->conversationService->triggerAiReply($conversation->fresh());
                }
            }

            if ($reachedSynced || count($rawConversations) < self::PAGE_SIZE) {
                break;
            }

            // NOTE: `startAfterDate`/`startAfter` follow GHL API v2's documented
            // cursor-pagination convention. Verify the exact param names against
            // your account's live /conversations/search docs before relying on
            // multi-page syncs in production.
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

    /**
     * Ambil dan simpan pesan baru untuk satu conversation.
     *
     * @return bool true jika ada pesan baru dari customer (inbound).
     */
    protected function syncMessages(Conversation $conversation, string $ghlConversationId): bool
    {
        $result = $this->ghl->getConversationMessages($ghlConversationId, ['limit' => 100]);
        $rawMessages = data_get($result, 'messages.messages', $result['messages'] ?? []);

        $hasNewCustomerMessage = false;

        foreach ($rawMessages as $raw) {
            $messageData = $this->parser->messageFromSearchApi($raw);

            if (!$messageData) {
                continue;
            }

            $message = $this->conversations->recordMessage($conversation, $messageData);

            if (!$message) {
                // Already recorded (dedup by ghl_message_id).
                continue;
            }

            Log::info('GHL message created', [
                'conversation_id' => $conversation->id,
                'direction' => $messageData->direction,
            ]);

            if ($messageData->isInbound()) {
                $hasNewCustomerMessage = true;
            }
        }

        return $hasNewCustomerMessage;
    }
}

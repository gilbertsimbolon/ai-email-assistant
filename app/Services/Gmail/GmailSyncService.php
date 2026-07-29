<?php

namespace App\Services\Gmail;

use App\Events\MessageReceived;
use App\Models\GmailAccount;
use App\Repositories\ConversationRepository;
use App\Repositories\MessageRepository;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orchestrates incremental sync of one GmailAccount's inbox: full backfill
 * the first time (no history_id yet), then Gmail History API from then on
 * so a normal run only touches messages that actually changed.
 */
class GmailSyncService
{
    public function __construct(
        protected GmailApiService $api,
        protected GmailAuthService $auth,
        protected GmailParserService $parser,
        protected ConversationRepository $conversations,
        protected MessageRepository $messages,
    ) {
    }

    public function sync(GmailAccount $account): void
    {
        Log::info('Gmail sync started', ['gmail_account_id' => $account->id]);

        try {
            $account = $this->auth->ensureFreshToken($account);

            if (blank($account->history_id)) {
                $this->fullSync($account);
            } else {
                $this->incrementalSync($account);
            }

            $account->update([
                'last_synced_at' => now(),
                'status' => 'connected',
                'last_error' => null,
            ]);
        } catch (Throwable $e) {
            $account->update([
                'status' => 'error',
                'last_error' => $e->getMessage(),
            ]);

            Log::error('Gmail sync failed', [
                'gmail_account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        Log::info('Gmail sync finished', ['gmail_account_id' => $account->id]);
    }

    /**
     * First-time backfill: pull the most recent inbox messages and record
     * the account's current historyId as the cursor for future incremental
     * syncs.
     */
    protected function fullSync(GmailAccount $account): void
    {
        $result = $this->api->listMessages($account->access_token, [
            'maxResults' => config('gmail.history_max_results', 100),
            'labelIds' => ['INBOX'],
        ]);

        foreach ($result['messages'] ?? [] as $ref) {
            $this->processMessage($account, $ref['id']);
        }

        $profile = $this->api->getProfile($account->access_token);
        $account->update(['history_id' => $profile['historyId'] ?? $account->history_id]);
    }

    /**
     * Pull only what changed since the account's stored historyId.
     */
    protected function incrementalSync(GmailAccount $account): void
    {
        try {
            $result = $this->api->listHistory($account->access_token, [
                'startHistoryId' => $account->history_id,
                'historyTypes' => 'messageAdded',
            ]);
        } catch (RequestException $e) {
            if ($e->response->status() === 404) {
                // startHistoryId too old/expired — Gmail can no longer diff
                // from it, so fall back to a full resync.
                Log::warning('Gmail history id expired, falling back to full sync', [
                    'gmail_account_id' => $account->id,
                ]);

                $account->update(['history_id' => null]);
                $this->fullSync($account->fresh());

                return;
            }

            throw $e;
        }

        foreach ($result['history'] ?? [] as $record) {
            foreach ($record['messagesAdded'] ?? [] as $added) {
                $this->processMessage($account, $added['message']['id']);
            }
        }

        if (isset($result['historyId'])) {
            $account->update(['history_id' => $result['historyId']]);
        }
    }

    protected function processMessage(GmailAccount $account, string $messageId): void
    {
        $message = $this->api->getMessage($account->access_token, $messageId);

        $conversation = $this->conversations->upsertConversation(
            $account,
            $this->parser->conversationFromMessage($account, $message)
        );

        $messageData = $this->parser->messageFromMessage($account, $message);
        $recorded = $this->messages->recordMessage($conversation, $messageData);

        if (!$recorded) {
            // Already synced — e.g. re-processing the same history record.
            return;
        }

        Log::info('Gmail message created', [
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

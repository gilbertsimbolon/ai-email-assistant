<?php

namespace App\Services\Ghl;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin wrapper around GHL's Conversations API (Private Integration token).
 * This is the only class that talks to GHL directly — Laravel is the agent
 * workspace, GHL is purely the data source/message API (see claude.txt).
 */
class GoHighLevelApiService
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $version;
    protected string $locationId;
    protected int $timeout;
    protected int $retryTimes;
    protected int $retryDelayMs;
    protected bool $verifySsl;

    public function __construct()
    {
        $this->baseUrl = config('ghl.base_url');
        $this->apiKey = config('ghl.api_key');
        $this->version = config('ghl.version');
        $this->locationId = config('ghl.location_id');
        $this->timeout = config('ghl.timeout', 30);
        $this->retryTimes = config('ghl.retry_times', 3);
        $this->retryDelayMs = config('ghl.retry_delay_ms', 1000);
        $this->verifySsl = (bool) config('ghl.verify_ssl', true);

        // claude.txt Step 8/9: lets you confirm — from Laravel's own log,
        // without ever printing the key — that a given request actually
        // used the NEW Private Integration token after rotating it. The
        // fingerprint is a truncated SHA-256 hash, not a key substring, so
        // it reveals nothing about the key itself; it only changes when the
        // underlying key changes. Compare this value across two log lines
        // (before/after updating .env + restarting PHP) to prove the app
        // picked up the rotation instead of silently reusing the old token.
        Log::debug('GoHighLevelApiService initialized', [
            'base_url' => $this->baseUrl,
            'location_id' => $this->locationId,
            'api_key_present' => $this->apiKey !== '' && $this->apiKey !== null,
            'api_key_fingerprint' => $this->credentialFingerprint((string) $this->apiKey),
        ]);
    }

    /**
     * Truncated SHA-256 of the credential — enough to tell "same key as
     * last time" apart from "different key", never enough to reconstruct
     * or brute-force the original value. This is the only form the API key
     * is allowed to appear in logs (claude.txt Step 8: never log the full key).
     */
    protected function credentialFingerprint(string $key): string
    {
        if ($key === '') {
            return '(empty)';
        }

        return substr(hash('sha256', $key), 0, 12).' (len:'.strlen($key).')';
    }

    protected function client(): PendingRequest
    {
        $request = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey,
            'Version' => $this->version,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])
            ->timeout($this->timeout)
            ->retry($this->retryTimes, $this->retryDelayMs, function (Throwable $exception) {
                if (!$exception instanceof RequestException) {
                    // Connection/timeout errors: worth retrying.
                    return true;
                }

                $status = $exception->response->status();

                if ($status === 429) {
                    $retryAfter = (int) $exception->response->header('Retry-After', 1);
                    Log::warning('GHL API rate limited, backing off', [
                        'retry_after_seconds' => $retryAfter,
                    ]);
                    sleep(max(1, min($retryAfter, 10)));

                    return true;
                }

                // Don't retry client errors that a retry can't fix.
                if (in_array($status, [400, 401, 403, 404, 422], true)) {
                    return false;
                }

                // Retry on 5xx.
                return $status >= 500;
            }, throw: false);

        if (!$this->verifySsl) {
            $request = $request->withoutVerifying();
        }

        return $request;
    }

    /**
     * @param  array<string, mixed>  $params  Extra query params, e.g. pagination
     *                                         cursor (`startAfterDate`, `startAfter`).
     */
    public function getConversations(array $params = []): array
    {
        return $this->request('getConversations', fn () => $this->client()->get(
            $this->baseUrl.'/conversations/search',
            array_merge([
                'locationId' => $this->locationId,
                'limit' => 20,
            ], $params)
        ));
    }

    /**
     * Fetch a single conversation's current state directly (not via
     * /conversations/search). Used for a live per-conversation refresh — e.g.
     * after sending a message, or when a local-only filter (starred/status)
     * needs to know a specific conversation's current GHL data without
     * paging through the whole search endpoint.
     */
    public function getConversation(string $conversationId): array
    {
        return $this->request(
            'getConversation',
            fn () => $this->client()->get($this->baseUrl."/conversations/{$conversationId}"),
            ['conversation_id' => $conversationId]
        );
    }

    /**
     * @param  array<string, mixed>  $params  Extra query params, e.g. pagination
     *                                         cursor (`lastMessageId`) or `limit`.
     */
    public function getConversationMessages(string $conversationId, array $params = []): array
    {
        return $this->request(
            'getConversationMessages',
            fn () => $this->client()->get(
                $this->baseUrl."/conversations/{$conversationId}/messages",
                $params
            ),
            ['conversation_id' => $conversationId]
        );
    }

    /**
     * Fetch a single contact's full details (tags, custom fields, DND,
     * address, etc.) for the Conversations "Contact Details" panel. Not
     * used during sync — called on-demand when an agent opens a
     * conversation, per claude.txt's on-demand-only principle.
     */
    public function getContact(string $contactId): array
    {
        return $this->request(
            'getContact',
            fn () => $this->client()->get($this->baseUrl."/contacts/{$contactId}"),
            ['contact_id' => $contactId]
        );
    }

    public function sendMessage(array $payload): array
    {
        return $this->request(
            'sendMessage',
            fn () => $this->client()->post($this->baseUrl.'/conversations/messages', $payload),
            ['conversation_id' => $payload['conversationId'] ?? null]
        );
    }

    /**
     * Fetch GHL Payments "Orders" for a contact — claude.txt Step 2/6: the
     * Contact API has no product/purchase fields (customFields = [] for
     * contacts that never had a location-level custom field set up for it),
     * so Extract Info's Product/Purchase Date/Purchase Price have to be
     * traced to wherever GHL actually stores a completed purchase. Orders is
     * that place: each order has amount/currency, createdAt, and an items[]
     * array carrying product.name — the closest thing GHL has to "what did
     * this contact buy". Requires the payments/orders.readonly scope.
     */
    public function getOrders(string $contactId, array $params = []): array
    {
        return $this->request(
            'getOrders',
            fn () => $this->client()->get($this->baseUrl.'/payments/orders', array_merge([
                'altId' => $this->locationId,
                'altType' => 'location',
                'contactId' => $contactId,
                'limit' => 10,
            ], $params)),
            ['contact_id' => $contactId]
        );
    }

    /**
     * Fetch GHL Payments "Transactions" for a contact — probed alongside
     * getOrders() (claude.txt Step 2: "jangan mengasumsikan salah satu
     * endpoint") in case a location's payment gateway attaches a
     * receipt/invoice-style reference here that the Order record itself
     * doesn't carry. Requires the payments/transactions.readonly scope.
     */
    public function getTransactions(string $contactId, array $params = []): array
    {
        return $this->request(
            'getTransactions',
            fn () => $this->client()->get($this->baseUrl.'/payments/transactions', array_merge([
                'altId' => $this->locationId,
                'altType' => 'location',
                'contactId' => $contactId,
                'limit' => 10,
            ], $params)),
            ['contact_id' => $contactId]
        );
    }

    /**
     * Execute a GHL API call, logging and normalizing failures consistently.
     *
     * @param  callable(): Response  $call
     * @param  array<string, mixed>  $context
     */
    protected function request(string $operation, callable $call, array $context = []): array
    {
        try {
            $response = $call()->throw();
            $body = $response->json() ?? [];

            // claude.txt Step 3: for every GHL call, log enough to answer
            // "which endpoint actually has the data" — status and the
            // response's top-level keys — without dumping the customer
            // payload itself (names/emails/amounts) into the log.
            Log::debug('GHL API request succeeded', array_merge($context, [
                'operation' => $operation,
                'status' => $response->status(),
                'response_keys' => array_is_list($body) ? ['(list)', 'count:'.count($body)] : array_keys($body),
            ]));

            return $body;
        } catch (RequestException $e) {
            $status = $e->response->status();

            Log::error('GHL API request failed', array_merge($context, [
                'operation' => $operation,
                'status' => $status,
                'body' => $e->response->body(),
            ]));

            throw $e;
        } catch (ConnectionException $e) {
            Log::error('GHL API connection failed', array_merge($context, [
                'operation' => $operation,
                'error' => $e->getMessage(),
            ]));

            throw $e;
        }
    }
}

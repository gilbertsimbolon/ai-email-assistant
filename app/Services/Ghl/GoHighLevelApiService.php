<?php

namespace App\Services\Ghl;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

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

        Log::debug('GoHighLevelApiService initialized', [
            'base_url' => $this->baseUrl,
            'location_id' => $this->locationId,
            'api_key_present' => $this->apiKey !== '' && $this->apiKey !== null,
            'api_key_fingerprint' => $this->credentialFingerprint((string) $this->apiKey),
        ]);
    }

    protected function credentialFingerprint(string $key): string
    {
        if ($key === '') {
            return '(empty)';
        }

        return substr(hash('sha256', $key), 0, 12) . ' (len:' . strlen($key) . ')';
    }

    protected function client(): PendingRequest
    {
        $request = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Version' => $this->version,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])
            ->timeout($this->timeout)
            ->retry($this->retryTimes, $this->retryDelayMs, function (Throwable $exception) {
                if (!$exception instanceof RequestException) {
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

                if (in_array($status, [400, 401, 403, 404, 422], true)) {
                    return false;
                }

                return $status >= 500;
            }, throw: false);

        if (!$this->verifySsl) {
            $request = $request->withoutVerifying();
        }

        return $request;
    }

    public function getConversations(array $params = []): array
    {
        return $this->request('getConversations', fn() => $this->client()->get(
            $this->baseUrl . '/conversations/search',
            array_merge([
                'locationId' => $this->locationId,
                'limit' => 20,
            ], $params)
        ));
    }

    public function getConversation(string $conversationId): array
    {
        return $this->request(
            'getConversation',
            fn() => $this->client()->get($this->baseUrl . "/conversations/{$conversationId}"),
            ['conversation_id' => $conversationId]
        );
    }

    public function getConversationMessages(
        string $conversationId,
        array $params = []
    ): array {
        return $this->request(
            'getConversationMessages',
            fn() => $this->client()->get(
                $this->baseUrl
                    . "/conversations/{$conversationId}/messages",
                $params
            ),
            [
                'conversation_id' => $conversationId,
                'params' => $params,
            ]
        );
    }

    public function getContact(string $contactId): array
    {
        return $this->request(
            'getContact',
            fn() => $this->client()->get($this->baseUrl . "/contacts/{$contactId}"),
            ['contact_id' => $contactId]
        );
    }

    public function sendMessage(array $payload): array
    {
        return $this->request(
            'sendMessage',
            fn() => $this->client()->post($this->baseUrl . '/conversations/messages', $payload),
            ['conversation_id' => $payload['conversationId'] ?? null]
        );
    }

    public function getOrders(string $contactId, array $params = []): array
    {
        return $this->request(
            'getOrders',
            fn() => $this->client()->get($this->baseUrl . '/payments/orders', array_merge([
                'altId' => $this->locationId,
                'altType' => 'location',
                'contactId' => $contactId,
                'limit' => 10,
            ], $params)),
            ['contact_id' => $contactId]
        );
    }

    public function getTransactions(string $contactId, array $params = []): array
    {
        return $this->request(
            'getTransactions',
            fn() => $this->client()->get($this->baseUrl . '/payments/transactions', array_merge([
                'altId' => $this->locationId,
                'altType' => 'location',
                'contactId' => $contactId,
                'limit' => 10,
            ], $params)),
            ['contact_id' => $contactId]
        );
    }

    protected function request(string $operation, callable $call, array $context = []): array
    {
        try {
            $response = $call()->throw();
            $body = $response->json() ?? [];

            Log::debug('GHL API request succeeded', array_merge($context, [
                'operation' => $operation,
                'status' => $response->status(),
                'response_keys' => array_is_list($body) ? ['(list)', 'count:' . count($body)] : array_keys($body),
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
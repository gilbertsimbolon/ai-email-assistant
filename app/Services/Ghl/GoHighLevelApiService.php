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

    public function sendMessage(array $payload): array
    {
        return $this->request(
            'sendMessage',
            fn () => $this->client()->post($this->baseUrl.'/conversations/messages', $payload),
            ['conversation_id' => $payload['conversationId'] ?? null]
        );
    }

    public function sendEmailMessage(string $conversationId, ?string $contactId, string $subject, string $html, string $text): array
    {
        return $this->sendMessage([
            'type' => 'Email',
            'conversationId' => $conversationId,
            'contactId' => $contactId,
            'subject' => $subject,
            'html' => $html,
            'message' => $text,
        ]);
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

            return $response->json() ?? [];
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

<?php

namespace App\Services\Gmail;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin HTTP client for Google OAuth2 and the Gmail REST API. Stateless by
 * design — callers always pass a bare access token, they never touch
 * GmailAccount persistence (that's GmailAuthService's job).
 */
class GmailApiService
{
    public function buildAuthorizationUrl(string $state): string
    {
        $params = [
            'client_id' => config('gmail.client_id'),
            'redirect_uri' => config('gmail.redirect_uri'),
            'response_type' => 'code',
            'scope' => implode(' ', config('gmail.scopes')),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ];

        return config('gmail.auth_url').'?'.http_build_query($params);
    }

    /**
     * @return array{access_token: string, refresh_token?: string, expires_in: int, scope: string}
     */
    public function exchangeCodeForToken(string $code): array
    {
        return $this->request('exchangeCodeForToken', fn () => $this->httpClient()->asForm()->post(config('gmail.token_url'), [
            'code' => $code,
            'client_id' => config('gmail.client_id'),
            'client_secret' => config('gmail.client_secret'),
            'redirect_uri' => config('gmail.redirect_uri'),
            'grant_type' => 'authorization_code',
        ]));
    }

    /**
     * @return array{access_token: string, expires_in: int, scope: string}
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        return $this->request('refreshAccessToken', fn () => $this->httpClient()->asForm()->post(config('gmail.token_url'), [
            'refresh_token' => $refreshToken,
            'client_id' => config('gmail.client_id'),
            'client_secret' => config('gmail.client_secret'),
            'grant_type' => 'refresh_token',
        ]));
    }

    public function revokeToken(string $token): void
    {
        try {
            $this->httpClient()->asForm()->post(config('gmail.revoke_url'), ['token' => $token]);
        } catch (Throwable $e) {
            Log::warning('Failed to revoke Gmail token', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @return array{email: string}
     */
    public function getUserInfo(string $accessToken): array
    {
        return $this->request(
            'getUserInfo',
            fn () => $this->authorizedClient($accessToken)->get(config('gmail.userinfo_url'))
        );
    }

    /**
     * @return array{emailAddress: string, historyId: string}
     */
    public function getProfile(string $accessToken): array
    {
        return $this->request(
            'getProfile',
            fn () => $this->authorizedClient($accessToken)->get($this->endpoint('/users/me/profile'))
        );
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function listMessages(string $accessToken, array $params = []): array
    {
        return $this->request(
            'listMessages',
            fn () => $this->authorizedClient($accessToken)->get($this->endpoint('/users/me/messages'), $params)
        );
    }

    public function getMessage(string $accessToken, string $messageId): array
    {
        return $this->request(
            'getMessage',
            fn () => $this->authorizedClient($accessToken)->get(
                $this->endpoint("/users/me/messages/{$messageId}"),
                ['format' => 'full']
            ),
            ['message_id' => $messageId]
        );
    }

    public function getAttachment(string $accessToken, string $messageId, string $attachmentId): array
    {
        return $this->request(
            'getAttachment',
            fn () => $this->authorizedClient($accessToken)->get(
                $this->endpoint("/users/me/messages/{$messageId}/attachments/{$attachmentId}")
            ),
            ['message_id' => $messageId, 'attachment_id' => $attachmentId]
        );
    }

    /**
     * @param  array<string, mixed>  $params  e.g. startHistoryId, historyTypes[]
     */
    public function listHistory(string $accessToken, array $params = []): array
    {
        return $this->request(
            'listHistory',
            fn () => $this->authorizedClient($accessToken)->get($this->endpoint('/users/me/history'), $params)
        );
    }

    /**
     * @param  string  $rawMessage  RFC 2822 message, base64url-encoded.
     */
    public function sendRawMessage(string $accessToken, string $rawMessage, ?string $threadId = null): array
    {
        $payload = array_filter([
            'raw' => $rawMessage,
            'threadId' => $threadId,
        ]);

        return $this->request(
            'sendMessage',
            fn () => $this->authorizedClient($accessToken)->post($this->endpoint('/users/me/messages/send'), $payload)
        );
    }

    protected function endpoint(string $path): string
    {
        return config('gmail.api_base_url').$path;
    }

    protected function authorizedClient(string $accessToken): PendingRequest
    {
        return $this->httpClient()->withToken($accessToken);
    }

    protected function httpClient(): PendingRequest
    {
        return Http::timeout((int) config('gmail.timeout', 30))
            ->retry(3, 1000, function (Throwable $exception) {
                if (!$exception instanceof RequestException) {
                    return true;
                }

                $status = $exception->response->status();

                if ($status === 429) {
                    $retryAfter = (int) $exception->response->header('Retry-After', 1);
                    Log::warning('Gmail API rate limited, backing off', ['retry_after_seconds' => $retryAfter]);
                    sleep(max(1, min($retryAfter, 10)));

                    return true;
                }

                if (in_array($status, [400, 401, 403, 404, 422], true)) {
                    return false;
                }

                return $status >= 500;
            }, throw: false);
    }

    /**
     * Execute a Gmail/Google API call, logging and normalizing failures consistently.
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
            Log::error('Gmail API request failed', array_merge($context, [
                'operation' => $operation,
                'status' => $e->response->status(),
                'body' => $e->response->body(),
            ]));

            throw $e;
        } catch (ConnectionException $e) {
            Log::error('Gmail API connection failed', array_merge($context, [
                'operation' => $operation,
                'error' => $e->getMessage(),
            ]));

            throw $e;
        }
    }
}

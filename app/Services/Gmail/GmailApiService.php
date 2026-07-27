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
 *
 * OAuth client credentials (client id/secret/redirect uri) come from
 * GmailConfigurationService, not env()/config() directly, so they follow
 * whatever an administrator has configured on the Settings page.
 */
class GmailApiService
{
    public function __construct(
        protected GmailConfigurationService $config,
    ) {
    }

    public function buildAuthorizationUrl(string $state): string
    {
        $params = [
            'client_id' => $this->config->getClientId(),
            'redirect_uri' => $this->config->getRedirectUri(),
            'response_type' => 'code',
            'scope' => implode(' ', $this->config->getScopes()),
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
            'client_id' => $this->config->getClientId(),
            'client_secret' => $this->config->getClientSecret(),
            'redirect_uri' => $this->config->getRedirectUri(),
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
            'client_id' => $this->config->getClientId(),
            'client_secret' => $this->config->getClientSecret(),
            'grant_type' => 'refresh_token',
        ]));
    }

    /**
     * Validate a (possibly unsaved) set of OAuth credentials without
     * performing a real login and without persisting anything: Google has no
     * "check my client id/secret" endpoint, so this sends a refresh_token
     * grant with a token that cannot possibly be valid. Google still
     * validates the client id/secret *first* — an `invalid_client` error
     * means the credentials themselves are wrong, while `invalid_grant`
     * means they were accepted and only the (intentionally fake) token
     * failed, which is what we treat as success.
     */
    public function testCredentials(string $clientId, string $clientSecret, string $redirectUri): array
    {
        try {
            $response = $this->httpClient()->asForm()->post(config('gmail.token_url'), [
                'refresh_token' => 'test-connection-probe',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'refresh_token',
            ]);
        } catch (ConnectionException $e) {
            return [
                'success' => false,
                'message' => 'Tidak dapat menghubungi server Google: '.$e->getMessage(),
            ];
        }

        $error = $response->json('error');

        if ($error === 'invalid_grant') {
            return [
                'success' => true,
                'message' => 'Client ID dan Client Secret valid.',
            ];
        }

        if ($error === 'invalid_client') {
            return [
                'success' => false,
                'message' => 'Client ID atau Client Secret tidak valid.',
            ];
        }

        Log::warning('Unexpected response while testing Gmail OAuth credentials', [
            'status' => $response->status(),
            'error' => $error,
        ]);

        return [
            'success' => false,
            'message' => 'Respon tidak terduga dari Google: '.($error ?? $response->status()),
        ];
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

<?php

namespace App\Services\Gmail;

use App\Models\GmailAccount;
use App\Models\User;
use Carbon\Carbon;

/**
 * Owns the Gmail OAuth token lifecycle: building the consent URL, persisting
 * a GmailAccount after the OAuth callback, and keeping its access token
 * fresh. GmailApiService stays stateless (bare token in, array out); this is
 * the only place that reads/writes GmailAccount token columns.
 */
class GmailAuthService
{
    public function __construct(
        protected GmailApiService $api,
    ) {
    }

    public function buildAuthorizationUrl(string $state): string
    {
        return $this->api->buildAuthorizationUrl($state);
    }

    /**
     * Exchange an OAuth callback `code` for tokens and persist/refresh the
     * GmailAccount for the given user.
     */
    public function connectAccount(User $user, string $code): GmailAccount
    {
        $token = $this->api->exchangeCodeForToken($code);
        $userInfo = $this->api->getUserInfo($token['access_token']);
        $profile = $this->api->getProfile($token['access_token']);

        return GmailAccount::updateOrCreate(
            ['user_id' => $user->id, 'email' => $userInfo['email']],
            [
                'access_token' => $token['access_token'],
                'refresh_token' => $token['refresh_token'] ?? null,
                'token_expires_at' => Carbon::now()->addSeconds((int) ($token['expires_in'] ?? 3600)),
                'scope' => $token['scope'] ?? null,
                'history_id' => $profile['historyId'] ?? null,
            ]
        );
    }

    /**
     * Return an account guaranteed to have a valid (non-expired) access
     * token, refreshing and persisting it first if necessary.
     */
    public function ensureFreshToken(GmailAccount $account): GmailAccount
    {
        if (!$account->isTokenExpired()) {
            return $account;
        }

        $token = $this->api->refreshAccessToken($account->refresh_token);

        $account->update([
            'access_token' => $token['access_token'],
            'token_expires_at' => Carbon::now()->addSeconds((int) ($token['expires_in'] ?? 3600)),
            'scope' => $token['scope'] ?? $account->scope,
        ]);

        return $account->fresh();
    }

    public function disconnect(GmailAccount $account): void
    {
        if ($account->refresh_token) {
            $this->api->revokeToken($account->refresh_token);
        }

        $account->delete();
    }
}

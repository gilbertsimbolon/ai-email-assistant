<?php

namespace App\Services\Gmail;

use App\Models\GmailSetting;

/**
 * Single source of truth for Gmail OAuth credentials (client id/secret/
 * redirect uri). Reads the admin-managed GmailSetting row first; falls back
 * to the legacy config('gmail.*') / .env values only while no row has been
 * saved yet, so existing deployments keep working until an admin saves the
 * Settings page once — after that, the database always wins.
 *
 * Bound as a singleton (see AppServiceProvider) so the settings row is read
 * from the database at most once per request/job, not once per Gmail API
 * call, while still staying fresh across requests without needing an
 * explicit cache to invalidate.
 */
class GmailConfigurationService
{
    protected bool $loaded = false;

    protected ?GmailSetting $setting = null;

    public function getClientId(): ?string
    {
        return $this->setting()?->client_id ?: config('gmail.client_id');
    }

    public function getClientSecret(): ?string
    {
        return $this->setting()?->client_secret ?: config('gmail.client_secret');
    }

    public function getRedirectUri(): ?string
    {
        return $this->setting()?->redirect_uri ?: config('gmail.redirect_uri');
    }

    /**
     * @return list<string>
     */
    public function getScopes(): array
    {
        return config('gmail.scopes', []);
    }

    /**
     * Whether an administrator has explicitly enabled Gmail integration. A
     * missing settings row (fresh install still relying on .env) is treated
     * as enabled, so behavior is unchanged until someone visits the Settings
     * page and saves a configuration.
     */
    public function isEnabled(): bool
    {
        $setting = $this->setting();

        return $setting ? $setting->enabled : true;
    }

    /**
     * Whether there is enough credential data (from either source) to
     * actually start an OAuth flow.
     */
    public function isConfigured(): bool
    {
        return filled($this->getClientId())
            && filled($this->getClientSecret())
            && filled($this->getRedirectUri());
    }

    /**
     * Source of the currently effective configuration, for display on the
     * Settings page.
     */
    public function source(): string
    {
        return $this->setting() ? 'database' : 'env';
    }

    /**
     * @param  array{client_id: string, client_secret: ?string, redirect_uri: string, enabled: bool}  $data
     */
    public function save(array $data): GmailSetting
    {
        $setting = GmailSetting::current() ?? new GmailSetting();

        $setting->client_id = $data['client_id'];
        $setting->redirect_uri = $data['redirect_uri'];
        $setting->enabled = $data['enabled'];

        // A blank client_secret in the form means "keep the existing one" —
        // the form never round-trips the real secret back to the browser,
        // so an empty submission must not wipe out a previously saved value.
        if (filled($data['client_secret'] ?? null)) {
            $setting->client_secret = $data['client_secret'];
        }

        $setting->save();

        $this->setting = $setting;
        $this->loaded = true;

        return $setting;
    }

    protected function setting(): ?GmailSetting
    {
        if (!$this->loaded) {
            $this->setting = GmailSetting::current();
            $this->loaded = true;
        }

        return $this->setting;
    }
}

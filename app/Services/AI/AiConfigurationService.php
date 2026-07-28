<?php

namespace App\Services\AI;

use App\DataTransferObjects\AiSettingsData;
use App\Enums\AiProvider;
use App\Models\AiSetting;

/**
 * Single source of truth for AI provider configuration (provider, api key,
 * base url, model, temperature, max tokens, timeout, enabled). Reads the
 * admin-managed AiSetting row only — unlike GmailConfigurationService this
 * never falls back to config()/env(), per product requirement that AI
 * configuration lives exclusively in the database.
 *
 * Bound as a singleton (see AppServiceProvider) so the settings row is read
 * from the database at most once per request/job.
 */
class AiConfigurationService
{
    protected bool $loaded = false;

    protected ?AiSetting $setting = null;

    public function getProvider(): AiProvider
    {
        return $this->setting()?->provider ?? AiProvider::OpenAi;
    }

    public function getApiKey(): ?string
    {
        return $this->setting()?->api_key;
    }

    public function getBaseUrl(): string
    {
        return $this->setting()?->base_url ?: $this->getProvider()->defaultBaseUrl();
    }

    public function getModel(): string
    {
        return $this->setting()?->model ?: $this->getProvider()->defaultModel();
    }

    public function getTemperature(): float
    {
        return $this->setting()?->temperature ?? 0.3;
    }

    public function getMaxTokens(): int
    {
        return $this->setting()?->max_tokens ?? 1200;
    }

    public function getTimeout(): int
    {
        return $this->setting()?->timeout ?? 60;
    }

    /**
     * Whether an administrator has explicitly enabled AI features. A
     * missing settings row (fresh install, nobody has visited the Settings
     * page yet) is treated as disabled.
     */
    public function isEnabled(): bool
    {
        return $this->setting()?->enabled ?? false;
    }

    /**
     * Whether there is enough data to actually call the provider.
     */
    public function isConfigured(): bool
    {
        return filled($this->getApiKey());
    }

    /**
     * @param  array{provider: AiProvider, api_key: ?string, base_url: ?string, model: ?string, temperature: float, max_tokens: int, timeout: int, enabled: bool}  $data
     */
    public function save(array $data): AiSetting
    {
        $setting = AiSetting::current() ?? new AiSetting();

        $setting->provider = $data['provider'];
        $setting->base_url = $data['base_url'];
        $setting->model = $data['model'];
        $setting->temperature = $data['temperature'];
        $setting->max_tokens = $data['max_tokens'];
        $setting->timeout = $data['timeout'];
        $setting->enabled = $data['enabled'];

        // A blank api_key in the form means "keep the existing one" — the
        // form never round-trips the real key back to the browser, so an
        // empty submission must not wipe out a previously saved value.
        if (filled($data['api_key'] ?? null)) {
            $setting->api_key = $data['api_key'];
        }

        $setting->save();

        $this->setting = $setting;
        $this->loaded = true;

        return $setting;
    }

    public function toSettingsData(): AiSettingsData
    {
        return new AiSettingsData(
            provider: $this->getProvider(),
            apiKey: $this->getApiKey(),
            baseUrl: $this->getBaseUrl(),
            model: $this->getModel(),
            temperature: $this->getTemperature(),
            maxTokens: $this->getMaxTokens(),
            timeout: $this->getTimeout(),
            enabled: $this->isEnabled(),
        );
    }

    protected function setting(): ?AiSetting
    {
        if (!$this->loaded) {
            $this->setting = AiSetting::current();
            $this->loaded = true;
        }

        return $this->setting;
    }
}

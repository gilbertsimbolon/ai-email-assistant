<?php

namespace App\Services\AI;

use App\DataTransferObjects\AiConnectionTestResult;
use App\DataTransferObjects\AiSettingsData;
use App\Exceptions\AiNotConfiguredException;
use App\Services\AI\Contracts\AiClientInterface;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Facade every AI-consuming service (analysis, drafting, ...) depends on.
 * Reads the active configuration from AiConfigurationService, picks the
 * right provider Strategy/Adapter via AiProviderFactory, and normalizes
 * logging/error handling the same way OpenAIService used to.
 */
class AiClientService implements AiClientInterface
{
    public function __construct(
        protected AiConfigurationService $config,
        protected AiProviderFactory $factory,
    ) {
    }

    public function chat(array $messages): array
    {
        if (!$this->config->isEnabled()) {
            throw AiNotConfiguredException::disabled();
        }

        if (!$this->config->isConfigured()) {
            throw AiNotConfiguredException::missingApiKey();
        }

        $settings = $this->config->toSettingsData();

        try {
            $response = $this->factory
                ->make($settings->provider)
                ->chat($messages, $settings);

            return $response->toArray();
        } catch (Throwable $e) {
            Log::error('AI provider request failed', [
                'provider' => $settings->provider->value,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function json(array $messages): array
    {
        $response = $this->chat($messages);

        $content = $this->cleanJson($response['content']);

        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Invalid JSON returned by AI provider', [
                'error' => json_last_error_msg(),
                'content' => $content,
            ]);

            throw new RuntimeException('AI provider returned invalid JSON: ' . json_last_error_msg());
        }

        return $decoded;
    }

    public function testConnection(AiSettingsData $settings): AiConnectionTestResult
    {
        return $this->factory
            ->make($settings->provider)
            ->testConnection($settings);
    }

    /**
     * Remove Markdown code fence wrapper some providers add around JSON.
     */
    protected function cleanJson(string $content): string
    {
        $content = trim($content);

        $content = preg_replace('/^```json\s*/i', '', $content);
        $content = preg_replace('/^```\s*/', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);

        return trim($content);
    }
}

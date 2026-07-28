<?php

namespace App\Services\AI\Adapters;

use App\DataTransferObjects\AiConnectionTestResult;
use App\DataTransferObjects\AiSettingsData;

/**
 * Local Ollama server exposing an OpenAI-compatible /v1 chat completions API
 * (no API key required, but the Bearer header is harmless to send).
 */
class OllamaAdapter extends OpenAiCompatibleAdapter
{
    public function testConnection(AiSettingsData $settings): AiConnectionTestResult
    {
        return $this->probe(
            fn () => $this->client($settings)
                ->withToken((string) $settings->apiKey)
                ->get('/models'),
        );
    }
}

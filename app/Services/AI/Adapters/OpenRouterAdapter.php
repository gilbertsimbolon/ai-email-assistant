<?php

namespace App\Services\AI\Adapters;

use App\DataTransferObjects\AiConnectionTestResult;
use App\DataTransferObjects\AiSettingsData;

class OpenRouterAdapter extends OpenAiCompatibleAdapter
{
    public function testConnection(AiSettingsData $settings): AiConnectionTestResult
    {
        // OpenRouter's /models endpoint is public and doesn't require a key
        // (so it can't validate one). /auth/key is authenticated and
        // returns the key's own metadata, which is what we want to probe.
        return $this->probe(
            fn () => $this->client($settings)
                ->withToken((string) $settings->apiKey)
                ->get('/auth/key'),
        );
    }
}

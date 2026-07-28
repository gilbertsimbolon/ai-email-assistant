<?php

namespace App\Services\AI\Adapters;

use App\DataTransferObjects\AiConnectionTestResult;
use App\DataTransferObjects\AiSettingsData;

class OpenAiAdapter extends OpenAiCompatibleAdapter
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

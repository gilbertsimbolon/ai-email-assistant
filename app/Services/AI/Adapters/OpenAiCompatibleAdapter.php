<?php

namespace App\Services\AI\Adapters;

use App\DataTransferObjects\AiChatResponse;
use App\DataTransferObjects\AiSettingsData;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Base for providers exposing an OpenAI-shaped Chat Completions API
 * (POST {base_url}/chat/completions, Bearer auth). OpenAI and OpenRouter
 * both speak this dialect; only their testConnection() probe differs.
 */
abstract class OpenAiCompatibleAdapter extends AbstractAiProviderAdapter
{
    private const CHAT_ENDPOINT = '/chat/completions';

    public function chat(array $messages, AiSettingsData $settings): AiChatResponse
    {
        Log::info('AI provider request', [
            'provider' => $settings->provider->value,
            'model' => $settings->model,
        ]);

        $response = $this->client($settings)
            ->withToken((string) $settings->apiKey)
            ->retry(3, 1000)
            ->post(self::CHAT_ENDPOINT, [
                'model' => $settings->model,
                'messages' => $messages,
                'temperature' => $settings->temperature,
                'max_tokens' => $settings->maxTokens,
            ])
            ->throw()
            ->json();

        $content = $response['choices'][0]['message']['content'] ?? null;

        if (!$content) {
            throw new RuntimeException(sprintf('%s returned an empty response.', $settings->provider->label()));
        }

        Log::info('AI provider response', [
            'provider' => $settings->provider->value,
            'model' => $response['model'] ?? null,
            'usage' => $response['usage'] ?? [],
        ]);

        return new AiChatResponse(
            content: trim($content),
            usage: $response['usage'] ?? [],
            model: $response['model'] ?? null,
        );
    }
}

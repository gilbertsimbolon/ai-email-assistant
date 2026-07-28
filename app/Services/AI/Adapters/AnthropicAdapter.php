<?php

namespace App\Services\AI\Adapters;

use App\DataTransferObjects\AiChatResponse;
use App\DataTransferObjects\AiConnectionTestResult;
use App\DataTransferObjects\AiSettingsData;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Adapter for Anthropic's Messages API. Unlike the OpenAI-shaped providers,
 * Anthropic does not accept a `role: system` message inside `messages` — the
 * system prompt is a separate top-level field, so incoming messages are
 * split accordingly before the request is built.
 */
class AnthropicAdapter extends AbstractAiProviderAdapter
{
    private const MESSAGES_ENDPOINT = '/v1/messages';

    private const MODELS_ENDPOINT = '/v1/models';

    private const API_VERSION = '2023-06-01';

    public function chat(array $messages, AiSettingsData $settings): AiChatResponse
    {
        [$system, $conversation] = $this->splitSystemPrompt($messages);

        Log::info('AI provider request', [
            'provider' => $settings->provider->value,
            'model' => $settings->model,
        ]);

        $payload = array_filter([
            'model' => $settings->model,
            'messages' => $conversation,
            'system' => $system,
            'temperature' => $settings->temperature,
            'max_tokens' => $settings->maxTokens,
        ], fn ($value) => $value !== null);

        $response = $this->authorizedClient($settings)
            ->retry(3, 1000)
            ->post(self::MESSAGES_ENDPOINT, $payload)
            ->throw()
            ->json();

        $content = collect($response['content'] ?? [])
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');

        if (!$content) {
            throw new RuntimeException('Anthropic Claude returned an empty response.');
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

    public function testConnection(AiSettingsData $settings): AiConnectionTestResult
    {
        return $this->probe(
            fn () => $this->authorizedClient($settings)->get(self::MODELS_ENDPOINT),
        );
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{0: ?string, 1: array<int, array{role: string, content: string}>}
     */
    protected function splitSystemPrompt(array $messages): array
    {
        $system = collect($messages)
            ->where('role', 'system')
            ->pluck('content')
            ->implode("\n\n");

        $conversation = collect($messages)
            ->reject(fn ($message) => $message['role'] === 'system')
            ->values()
            ->all();

        return [$system !== '' ? $system : null, $conversation];
    }

    protected function authorizedClient(AiSettingsData $settings): PendingRequest
    {
        return $this->client($settings)
            ->withHeaders([
                'x-api-key' => (string) $settings->apiKey,
                'anthropic-version' => self::API_VERSION,
            ]);
    }
}

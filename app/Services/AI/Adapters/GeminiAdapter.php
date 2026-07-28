<?php

namespace App\Services\AI\Adapters;

use App\DataTransferObjects\AiChatResponse;
use App\DataTransferObjects\AiConnectionTestResult;
use App\DataTransferObjects\AiSettingsData;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Adapter for Google's Gemini generateContent API. Gemini has no "system"
 * or "assistant" roles — the system prompt is a separate systemInstruction
 * field and assistant turns use role "model" — so incoming generic messages
 * are translated accordingly.
 */
class GeminiAdapter extends AbstractAiProviderAdapter
{
    private const MODELS_ENDPOINT = '/models';

    public function chat(array $messages, AiSettingsData $settings): AiChatResponse
    {
        [$systemInstruction, $contents] = $this->toGeminiContents($messages);

        Log::info('AI provider request', [
            'provider' => $settings->provider->value,
            'model' => $settings->model,
        ]);

        $payload = array_filter([
            'contents' => $contents,
            'systemInstruction' => $systemInstruction,
            'generationConfig' => [
                'temperature' => $settings->temperature,
                'maxOutputTokens' => $settings->maxTokens,
            ],
        ], fn ($value) => $value !== null);

        $response = $this->authorizedClient($settings)
            ->retry(3, 1000)
            ->post(sprintf('/models/%s:generateContent', $settings->model), $payload)
            ->throw()
            ->json();

        $content = collect($response['candidates'][0]['content']['parts'] ?? [])
            ->pluck('text')
            ->implode('');

        if (!$content) {
            throw new RuntimeException('Google Gemini returned an empty response.');
        }

        Log::info('AI provider response', [
            'provider' => $settings->provider->value,
            'model' => $response['modelVersion'] ?? null,
            'usage' => $response['usageMetadata'] ?? [],
        ]);

        return new AiChatResponse(
            content: trim($content),
            usage: $response['usageMetadata'] ?? [],
            model: $response['modelVersion'] ?? null,
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
     * @return array{0: ?array{parts: array<int, array{text: string}>}, 1: array<int, array{role: string, parts: array<int, array{text: string}>}>}
     */
    protected function toGeminiContents(array $messages): array
    {
        $system = collect($messages)
            ->where('role', 'system')
            ->pluck('content')
            ->implode("\n\n");

        $contents = collect($messages)
            ->reject(fn ($message) => $message['role'] === 'system')
            ->map(fn ($message) => [
                'role' => $message['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $message['content']]],
            ])
            ->values()
            ->all();

        $systemInstruction = $system !== '' ? ['parts' => [['text' => $system]]] : null;

        return [$systemInstruction, $contents];
    }

    protected function authorizedClient(AiSettingsData $settings): PendingRequest
    {
        return $this->client($settings)
            ->withHeaders([
                'x-goog-api-key' => (string) $settings->apiKey,
            ]);
    }
}

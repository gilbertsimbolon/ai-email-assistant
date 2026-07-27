<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class OpenAIService
{
    private const CHAT_ENDPOINT = '/chat/completions';

    protected readonly string $apiKey;
    protected readonly string $baseUrl;
    protected readonly string $model;
    protected readonly float $temperature;
    protected readonly int $maxTokens;

    public function __construct()
    {
        $this->apiKey = config('openai.api_key');
        $this->baseUrl = rtrim(config('openai.base_url'), '/');
        $this->model = config('openai.model');
        $this->temperature = config('openai.temperature');
        $this->maxTokens = config('openai.max_tokens');

        if (blank($this->apiKey)) {
            throw new RuntimeException('OPENAI_API_KEY is not configured.');
        }
    }

    protected function client()
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken($this->apiKey)
            ->acceptJson()
            ->contentType('application/json')
            ->timeout(60)
            ->retry(3, 1000)
            ->withoutVerifying();
    }

    /**
     * Send Chat Completion request.
     *
     * @param array<int, array<string, mixed>> $messages
     * @return array{
     *     content:string,
     *     usage:array,
     *     model:?string
     * }
     */
    public function chat(array $messages): array
    {
        try {

            Log::info('OpenAI request', [
                'model' => $this->model,
            ]);

            $response = $this->client()
                ->post(self::CHAT_ENDPOINT, [
                    'model' => $this->model,
                    'messages' => $messages,
                    'temperature' => $this->temperature,
                    'max_tokens' => $this->maxTokens,
                ])
                ->throw()
                ->json();

            $content = $response['choices'][0]['message']['content'] ?? null;

            if (!$content) {
                throw new RuntimeException('OpenAI returned an empty response.');
            }

            Log::info('OpenAI response', [
                'model' => $response['model'] ?? null,
                'prompt_tokens' => $response['usage']['prompt_tokens'] ?? 0,
                'completion_tokens' => $response['usage']['completion_tokens'] ?? 0,
                'total_tokens' => $response['usage']['total_tokens'] ?? 0,
            ]);

            return [
                'content' => trim($content),
                'usage' => $response['usage'] ?? [],
                'model' => $response['model'] ?? null,
            ];
        } catch (Throwable $e) {

            Log::error('OpenAI request failed', [
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Return decoded JSON.
     *
     * @param array<int, array<string, mixed>> $messages
     */
    public function json(array $messages): array
    {
        $response = $this->chat($messages);

        $content = $this->cleanJson($response['content']);

        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {

            Log::error('Invalid JSON returned by OpenAI', [
                'error' => json_last_error_msg(),
                'content' => $content,
            ]);

            throw new RuntimeException(
                'OpenAI returned invalid JSON: ' . json_last_error_msg()
            );
        }

        return $decoded;
    }

    /**
     * Return plain text.
     *
     * @param array<int, array<string, mixed>> $messages
     */
    public function text(string $prompt)
    {
        return Http::withHeaders([
                'Authorization' => 'Bearer ' . config('openai.api_key'),
                'Content-Type' => 'application/json',
            ])
            ->withoutVerifying() // <-- Tambahkan baris ini untuk melewati error SSL ke OpenAI
            ->post(config('openai.base_url') . '/chat/completions', [
                'model' => config('openai.model', 'gpt-4o'),
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => (float) config('openai.temperature', 0.3),
                'max_tokens' => (int) config('openai.max_tokens', 1200),
            ])
            ->throw()
            ->json('choices.0.message.content');
    }

    /**
     * Remove Markdown wrapper.
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

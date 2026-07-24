<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIService
{
    protected readonly string $apiKey;
    protected readonly string $model;
    protected readonly float $temperature;
    protected readonly int $maxTokens;
    protected readonly string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('openai.api_key');
        $this->model = config('openai.model');
        $this->temperature = config('openai.temperature');
        $this->maxTokens = config('openai.max_tokens');
        $this->baseUrl = config('openai.base_url');

        if (empty($this->apiKey)) {
            throw new \RuntimeException('OPENAI_API_KEY is not configured.');
        }
    }

    protected function client()
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken($this->apiKey)
            ->retry(3, 1000)
            ->timeout(60)
            ->acceptJson()
            ->contentType('application/json');
    }

    /**
     * Send a chat completion request to OpenAI.
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
                ->post('/chat/completions', [
                    'model' => $this->model,
                    'messages' => $messages,
                    'temperature' => $this->temperature,
                    'max_tokens' => $this->maxTokens,
                ])
                ->throw()
                ->json();

            Log::info('OpenAI response', [
                'model' => $response['model'] ?? null,
                'usage' => $response['usage'] ?? [],
            ]);

            return [
                'content' => $response['choices'][0]['message']['content'] ?? '',
                'usage' => $response['usage'] ?? [],
                'model' => $response['model'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('OpenAI request failed', [
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
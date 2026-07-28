<?php

use App\Exceptions\AiNotConfiguredException;
use App\Models\AiSetting;
use App\Services\AI\Contracts\AiClientInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

function createAiSetting(array $overrides = []): AiSetting
{
    return AiSetting::create(array_merge([
        'provider' => 'openai',
        'api_key' => 'sk-test-key',
        'base_url' => 'https://api.openai.com/v1',
        'model' => 'gpt-4o',
        'temperature' => 0.3,
        'max_tokens' => 1200,
        'timeout' => 60,
        'enabled' => true,
    ], $overrides));
}

test('chat() throws when no AI configuration has been saved yet', function () {
    app(AiClientInterface::class)->chat([['role' => 'user', 'content' => 'hi']]);
})->throws(AiNotConfiguredException::class);

test('chat() throws when AI is disabled', function () {
    createAiSetting(['enabled' => false]);

    app(AiClientInterface::class)->chat([['role' => 'user', 'content' => 'hi']]);
})->throws(AiNotConfiguredException::class);

test('chat() throws when no api key is configured', function () {
    createAiSetting(['api_key' => null]);

    app(AiClientInterface::class)->chat([['role' => 'user', 'content' => 'hi']]);
})->throws(AiNotConfiguredException::class);

test('chat() sends an OpenAI-shaped request and returns the trimmed content', function () {
    createAiSetting();

    Http::fake([
        'api.openai.com/v1/chat/completions' => Http::response([
            'model' => 'gpt-4o',
            'choices' => [['message' => ['content' => "  Hello there  \n"]]],
            'usage' => ['total_tokens' => 42],
        ]),
    ]);

    $response = app(AiClientInterface::class)->chat([
        ['role' => 'user', 'content' => 'hi'],
    ]);

    expect($response['content'])->toBe('Hello there');
    expect($response['usage']['total_tokens'])->toBe(42);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.openai.com/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer sk-test-key')
            && $request['model'] === 'gpt-4o';
    });
});

test('json() strips markdown code fences before decoding', function () {
    createAiSetting();

    Http::fake([
        'api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => "```json\n{\"foo\":\"bar\"}\n```"]]],
        ]),
    ]);

    $decoded = app(AiClientInterface::class)->json([['role' => 'user', 'content' => 'hi']]);

    expect($decoded)->toBe(['foo' => 'bar']);
});

test('Anthropic adapter splits the system message out of the messages array', function () {
    createAiSetting(['provider' => 'anthropic', 'base_url' => 'https://api.anthropic.com', 'model' => 'claude-sonnet-4-5']);

    Http::fake([
        'api.anthropic.com/v1/messages' => Http::response([
            'model' => 'claude-sonnet-4-5',
            'content' => [['type' => 'text', 'text' => 'Hi from Claude']],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ]),
    ]);

    $response = app(AiClientInterface::class)->chat([
        ['role' => 'system', 'content' => 'You are helpful.'],
        ['role' => 'user', 'content' => 'hi'],
    ]);

    expect($response['content'])->toBe('Hi from Claude');

    Http::assertSent(function ($request) {
        return $request->hasHeader('x-api-key', 'sk-test-key')
            && $request['system'] === 'You are helpful.'
            && $request['messages'] === [['role' => 'user', 'content' => 'hi']];
    });
});

test('Gemini adapter maps assistant turns to the "model" role and extracts systemInstruction', function () {
    createAiSetting([
        'provider' => 'gemini',
        'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
        'model' => 'gemini-2.0-flash',
    ]);

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'modelVersion' => 'gemini-2.0-flash',
            'candidates' => [['content' => ['parts' => [['text' => 'Hi from Gemini']]]]],
            'usageMetadata' => ['totalTokenCount' => 7],
        ]),
    ]);

    $response = app(AiClientInterface::class)->chat([
        ['role' => 'system', 'content' => 'Be concise.'],
        ['role' => 'user', 'content' => 'hi'],
        ['role' => 'assistant', 'content' => 'previous reply'],
    ]);

    expect($response['content'])->toBe('Hi from Gemini');

    Http::assertSent(function ($request) {
        return $request->hasHeader('x-goog-api-key', 'sk-test-key')
            && $request['systemInstruction']['parts'][0]['text'] === 'Be concise.'
            && $request['contents'][1]['role'] === 'model';
    });
});

test('testConnection() classifies a 401 response as InvalidApiKey', function () {
    createAiSetting();

    Http::fake([
        'api.openai.com/v1/models' => Http::response(['error' => 'invalid api key'], 401),
    ]);

    $result = app(\App\Services\AI\AiClientService::class)
        ->testConnection((app(\App\Services\AI\AiConfigurationService::class))->toSettingsData());

    expect($result->status)->toBe(\App\Enums\AiConnectionStatus::InvalidApiKey);
    expect($result->isSuccess())->toBeFalse();
});

test('testConnection() classifies a connection timeout as Timeout', function () {
    createAiSetting();

    Http::fake(function () {
        throw new ConnectionException('cURL error 28: Operation timed out after 60000 milliseconds');
    });

    $result = app(\App\Services\AI\AiClientService::class)
        ->testConnection((app(\App\Services\AI\AiConfigurationService::class))->toSettingsData());

    expect($result->status)->toBe(\App\Enums\AiConnectionStatus::Timeout);
});

test('testConnection() reports Connected on a successful probe', function () {
    createAiSetting();

    Http::fake([
        'api.openai.com/v1/models' => Http::response(['data' => []], 200),
    ]);

    $result = app(\App\Services\AI\AiClientService::class)
        ->testConnection((app(\App\Services\AI\AiConfigurationService::class))->toSettingsData());

    expect($result->isSuccess())->toBeTrue();
    expect($result->status)->toBe(\App\Enums\AiConnectionStatus::Connected);
});

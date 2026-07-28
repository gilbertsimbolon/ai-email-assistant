<?php

namespace App\Services\AI;

use App\Enums\AiProvider;
use App\Services\AI\Adapters\AnthropicAdapter;
use App\Services\AI\Adapters\GeminiAdapter;
use App\Services\AI\Adapters\OpenAiAdapter;
use App\Services\AI\Adapters\OpenRouterAdapter;
use App\Services\AI\Contracts\AiProviderAdapterInterface;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves the provider Strategy/Adapter for a given AiProvider. Adding a
 * new provider means adding an enum case, an adapter class, and a branch
 * here — nothing else in the application needs to change.
 */
class AiProviderFactory
{
    public function __construct(
        protected Container $container,
    ) {
    }

    public function make(AiProvider $provider): AiProviderAdapterInterface
    {
        return $this->container->make(match ($provider) {
            AiProvider::OpenAi => OpenAiAdapter::class,
            AiProvider::OpenRouter => OpenRouterAdapter::class,
            AiProvider::Anthropic => AnthropicAdapter::class,
            AiProvider::Gemini => GeminiAdapter::class,
        });
    }
}

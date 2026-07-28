<?php

namespace App\DataTransferObjects;

use App\Enums\AiProvider;

/**
 * Effective AI provider configuration for a single request. Handed to
 * provider adapters so they never touch the AiSetting Eloquent model or
 * env()/config() directly.
 */
final class AiSettingsData
{
    public function __construct(
        public readonly AiProvider $provider,
        public readonly ?string $apiKey,
        public readonly string $baseUrl,
        public readonly string $model,
        public readonly float $temperature,
        public readonly int $maxTokens,
        public readonly int $timeout,
        public readonly bool $enabled,
    ) {
    }
}

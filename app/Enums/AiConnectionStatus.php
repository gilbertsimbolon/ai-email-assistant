<?php

namespace App\Enums;

enum AiConnectionStatus: string
{
    case Connected = 'connected';
    case AuthenticationFailed = 'authentication_failed';
    case InvalidApiKey = 'invalid_api_key';
    case InvalidBaseUrl = 'invalid_base_url';
    case Timeout = 'timeout';
    case ProviderUnreachable = 'provider_unreachable';
    case Error = 'error';

    public function isSuccess(): bool
    {
        return $this === self::Connected;
    }

    public function defaultMessage(): string
    {
        return match ($this) {
            self::Connected => 'Connected.',
            self::AuthenticationFailed => 'Authentication Failed.',
            self::InvalidApiKey => 'Invalid API Key.',
            self::InvalidBaseUrl => 'Invalid Base URL.',
            self::Timeout => 'Timeout.',
            self::ProviderUnreachable => 'Provider Unreachable.',
            self::Error => 'An unexpected error occurred.',
        };
    }
}

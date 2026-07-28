<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when AI features are used before an administrator has configured
 * and enabled a provider in Settings -> AI Configuration.
 */
class AiNotConfiguredException extends RuntimeException
{
    public static function disabled(): self
    {
        return new self('AI is not enabled. An administrator must enable it in Settings > AI Configuration.');
    }

    public static function missingApiKey(): self
    {
        return new self('AI provider API key is not configured. An administrator must set it in Settings > AI Configuration.');
    }
}

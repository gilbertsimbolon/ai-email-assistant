<?php

namespace App\DataTransferObjects;

use App\Enums\AiConnectionStatus;

/**
 * Result of a "Test Connection" probe against an AI provider.
 */
final class AiConnectionTestResult
{
    public function __construct(
        public readonly AiConnectionStatus $status,
        public readonly string $message,
    ) {
    }

    public static function make(AiConnectionStatus $status, ?string $message = null): self
    {
        return new self($status, $message ?? $status->defaultMessage());
    }

    public function isSuccess(): bool
    {
        return $this->status->isSuccess();
    }

    /**
     * @return array{success: bool, status: string, message: string}
     */
    public function toArray(): array
    {
        return [
            'success' => $this->isSuccess(),
            'status' => $this->status->value,
            'message' => $this->message,
        ];
    }
}

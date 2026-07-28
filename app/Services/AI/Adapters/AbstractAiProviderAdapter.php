<?php

namespace App\Services\AI\Adapters;

use App\DataTransferObjects\AiConnectionTestResult;
use App\DataTransferObjects\AiSettingsData;
use App\Enums\AiConnectionStatus;
use App\Services\AI\Contracts\AiProviderAdapterInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Shared HTTP plumbing and failure classification for provider adapters, so
 * each concrete adapter only has to describe its request/response shape.
 */
abstract class AbstractAiProviderAdapter implements AiProviderAdapterInterface
{
    protected function client(AiSettingsData $settings): PendingRequest
    {
        return Http::baseUrl(rtrim($settings->baseUrl, '/'))
            ->timeout($settings->timeout)
            ->acceptJson()
            ->contentType('application/json');
    }

    /**
     * Run a "test connection" probe, translating transport/HTTP failures
     * into an AiConnectionTestResult instead of throwing.
     *
     * @param  callable(): Response  $probe
     */
    protected function probe(callable $probe): AiConnectionTestResult
    {
        try {
            $response = $probe();
        } catch (ConnectionException $e) {
            return AiConnectionTestResult::make(
                $this->classifyConnectionException($e),
            );
        }

        if ($response->successful()) {
            return AiConnectionTestResult::make(AiConnectionStatus::Connected);
        }

        return $this->resultFromFailedResponse($response);
    }

    protected function resultFromFailedResponse(Response $response): AiConnectionTestResult
    {
        $status = $this->classifyHttpStatus($response->status());

        return AiConnectionTestResult::make(
            $status,
            sprintf('%s (HTTP %d)', $status->defaultMessage(), $response->status()),
        );
    }

    protected function classifyHttpStatus(int $status): AiConnectionStatus
    {
        return match (true) {
            $status === 401 => AiConnectionStatus::InvalidApiKey,
            $status === 403 => AiConnectionStatus::AuthenticationFailed,
            $status === 404 => AiConnectionStatus::InvalidBaseUrl,
            default => AiConnectionStatus::Error,
        };
    }

    protected function classifyConnectionException(ConnectionException $e): AiConnectionStatus
    {
        $message = strtolower($e->getMessage());

        if (str_contains($message, 'timed out') || str_contains($message, 'timeout')) {
            return AiConnectionStatus::Timeout;
        }

        return AiConnectionStatus::ProviderUnreachable;
    }
}

<?php

namespace App\Services\AI\Contracts;

/**
 * Facade used by every AI-consuming service (analysis, drafting, etc). It is
 * the only thing callers are allowed to depend on — never a provider
 * adapter or provider SDK directly.
 */
interface AiClientInterface
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{content: string, usage: array<string, mixed>, model: ?string}
     */
    public function chat(array $messages): array;

    /**
     * Send a chat request and decode its content as JSON.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array<string, mixed>
     */
    public function json(array $messages): array;
}

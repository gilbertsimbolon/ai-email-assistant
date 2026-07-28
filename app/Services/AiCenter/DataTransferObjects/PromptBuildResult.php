<?php

namespace App\Services\AiCenter\DataTransferObjects;

final class PromptBuildResult
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, string>  $sections
     */
    public function __construct(
        public readonly array $messages,
        public readonly array $sections,
    ) {
    }
}

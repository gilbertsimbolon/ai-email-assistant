<?php

namespace App\DataTransferObjects;

/**
 * Normalized chat completion result, regardless of which provider produced it.
 */
final class AiChatResponse
{
    /**
     * @param  array<string, mixed>  $usage
     */
    public function __construct(
        public readonly string $content,
        public readonly array $usage = [],
        public readonly ?string $model = null,
    ) {
    }

    /**
     * @return array{content: string, usage: array<string, mixed>, model: ?string}
     */
    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'usage' => $this->usage,
            'model' => $this->model,
        ];
    }
}

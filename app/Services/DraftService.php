<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Draft;
use App\Models\Analysis;

class DraftService
{
    public function __construct(
        protected PromptService $promptService,
        protected OpenAIService $openAIService
    ) {
    }

    /**
     * Generate reply draft using AI.
     */
    public function generate(Conversation $conversation, Analysis $analysis): string
    {
        $prompt = $this->promptService
            ->buildDraftPrompt($conversation, $analysis);

        return $this->openAIService->text($prompt);
    }

    /**
     * Save draft result.
     */
    public function save(
        Conversation $conversation,
        string $content
    ): Draft {
        return Draft::updateOrCreate(
            ['conversation_id' => $conversation->id],
            [
                'content' => $content,
                'type' => $conversation->channel, // Menggunakan channel conversation (email/whatsapp)
                'status' => 'active', // Sesuai default migration ('active')
                'provider' => 'openai',
            ]
        );
    }
}
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

        // Ubah dari ->text($prompt) menjadi ->chat($prompt)['content']
        $response = $this->openAIService->chat($prompt);

        return $response['content'];
    }

    /**
     * Save draft result.
     */
    public function save(
        Conversation $conversation,
        string $content
    ): Draft {
        // Ambil nilai string dari channel conversation dengan aman
        $channelValue = $conversation->channel instanceof \BackedEnum 
            ? $conversation->channel->value 
            : (string) $conversation->channel;

        return Draft::updateOrCreate(
            ['conversation_id' => $conversation->id],
            [
                'content' => $content,
                'type' => strtolower($channelValue), // Pastikan nilai berupa string backing value
                'status' => 'active', 
                'provider' => 'openai',
            ]
        );
    }
}

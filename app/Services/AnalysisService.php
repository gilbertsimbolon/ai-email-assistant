<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Analysis;

class AnalysisService
{
    public function __construct(
        protected PromptService $promptService,
        protected OpenAIService $openAIService
    ) {
    }

    /**
     * Analyze conversation using AI.
     */
    public function analyze(string $thread): array
    {
        $prompt = $this->promptService
            ->buildAnalysisPrompt($thread);

        // Menggunakan method json() dari OpenAIService agar lebih bersih & aman
        return $this->openAIService->json($prompt);
    }

    /**
     * Save analysis result.
     */
    public function save(
        Conversation $conversation,
        array $analysis
    ): Analysis {

        return Analysis::updateOrCreate(
            ['conversation_id' => $conversation->id],
            [
                'language' => $analysis['language'] ?? null,
                'intent' => $analysis['intent'] ?? null,
                'priority' => $analysis['priority'] ?? null,
                'sentiment' => $analysis['sentiment'] ?? null,
                'needs_escalation' => $analysis['needs_escalation'] ?? false,
                'summary' => $analysis['summary'] ?? null,
                'recommended_action' => $analysis['recommended_action'] ?? null,
            ]
        );
    }
}
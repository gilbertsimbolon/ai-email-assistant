<?php

namespace App\Services;

use App\Models\Analysis;
use App\Models\Conversation;

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

    /**
     * Analyze the thread and persist the result in a single call.
     */
    public function analyzeAndSave(Conversation $conversation, string $thread): Analysis
    {
        return $this->save($conversation, $this->analyze($thread));
    }

    /**
     * Normalize AI-provided enum values (e.g. "High") to their snake_case
     * backing values (e.g. "high") so enum casts don't throw on save.
     */
    protected function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return str_replace(' ', '_', strtolower(trim($value)));
    }
}

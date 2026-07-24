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

        $response = $this->openAIService
            ->chat($prompt);

        return json_decode(
            $response['content'],
            true
        );
    }

    /**
     * Save analysis result.
     */
    public function save(
        Conversation $conversation,
        array $analysis
    ): Analysis {

        return Analysis::create([
            'conversation_id' => $conversation->id,
            'language' => $analysis['language'] ?? null,
            'intent' => $analysis['intent'] ?? null,
            'priority' => $analysis['priority'] ?? null,
            'sentiment' => $analysis['sentiment'] ?? null,
            'needs_escalation' => $analysis['needs_escalation'] ?? false,
            'summary' => $analysis['summary'] ?? null,
            'recommended_action' => $analysis['recommended_action'] ?? null,
        ]);
    }
}
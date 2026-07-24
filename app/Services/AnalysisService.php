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

        return $this->openAIService->json($prompt);
    }

    /**
     * Save analysis result.
     */
    public function save(
        Conversation $conversation,
        array $analysis
    ): Analysis {

        return $conversation->analysis()->updateOrCreate(
            ['conversation_id' => $conversation->id],
            [
                'language' => $analysis['language'] ?? null,
                'customer_intent' => $analysis['intent'] ?? null,
                'priority' => $this->normalize($analysis['priority'] ?? null),
                'sentiment' => $this->normalize($analysis['sentiment'] ?? null),
                'customer_status' => $this->normalize($analysis['customer_status'] ?? null),
                'escalation_required' => $analysis['needs_escalation'] ?? false,
                'refund_requested' => $analysis['refund_requested'] ?? false,
                'summary' => $analysis['summary'] ?? null,
                'last_customer_request' => $analysis['last_customer_request'] ?? null,
                'recommended_action' => $analysis['recommended_action'] ?? null,
                'confidence_score' => $analysis['confidence_score'] ?? null,
                'raw_json' => $analysis,
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

<?php

namespace App\Services;

use App\Enums\CustomerStatus;
use App\Enums\Priority;
use App\Enums\Sentiment;
use App\Models\Analysis;
use App\Models\Conversation;
use App\Services\AI\Contracts\AiClientInterface;

class AnalysisService
{
    public function __construct(
        protected PromptService $promptService,
        protected AiClientInterface $aiClient
    ) {
    }

    /**
     * Analyze conversation using AI.
     */
    public function analyze(string $thread): array
    {
        $prompt = $this->promptService
            ->buildAnalysisPrompt($thread);

        return $this->aiClient->json($prompt);
    }

    /**
     * Save analysis result.
     */
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
                'summary' => $analysis['summary'] ?? '',
                'customer_intent' => $analysis['intent'] ?? null,
                'sentiment' => $this->normalize($analysis['sentiment'] ?? null) ?? Sentiment::Neutral->value,
                'customer_status' => $this->normalize($analysis['customer_status'] ?? null) ?? CustomerStatus::Unknown->value,
                'priority' => $this->normalize($analysis['priority'] ?? null) ?? Priority::Medium->value,
                'last_customer_request' => $analysis['last_customer_request'] ?? null,
                'recommended_action' => $analysis['recommended_action'] ?? null,
                'refund_requested' => (bool) ($analysis['refund_requested'] ?? false),
                'escalation_required' => (bool) ($analysis['needs_escalation'] ?? false),
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

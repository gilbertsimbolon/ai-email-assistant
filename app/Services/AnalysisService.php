<?php

namespace App\Services;

use App\Enums\CustomerStatus;
use App\Enums\Priority;
use App\Enums\Sentiment;
use App\Models\Analysis;
use App\Models\Conversation;
use App\Services\AI\Contracts\AiClientInterface;
use App\Services\AiCenter\Engines\IntentDetectionEngine;
use App\Services\AiCenter\Support\AnalysisPromptFactory;

class AnalysisService
{
    public function __construct(
        protected AnalysisPromptFactory $promptFactory,
        protected AiClientInterface $aiClient,
        protected IntentDetectionEngine $intentDetectionEngine,
    ) {
    }

    /**
     * Analyze conversation using AI. Hints the classification prompt with
     * the shortlist of known Intent names so IntentDetectionEngine::resolve
     * has an easier time matching the AI's free-text answer back to a
     * configured Intent — no extra AI call is made for this.
     */
    public function analyze(string $thread): array
    {
        $shortlist = $this->intentDetectionEngine->shortlist($thread)->pluck('name')->all();

        $prompt = $this->promptFactory->build($thread, $shortlist);

        return $this->aiClient->json($prompt);
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
                'summary' => $analysis['summary'] ?? '',
                'customer_intent' => $analysis['intent'] ?? null,
                'intent_id' => $analysis['intent_id'] ?? null,
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
     * Analyze the thread and persist the result in a single call. Resolves
     * the AI Center Intent (App\Models\AiCenter\Intent) from the same JSON
     * response used for the rest of the analysis — see
     * IntentDetectionEngine::resolve for the matching/fallback strategy.
     */
    public function analyzeAndSave(Conversation $conversation, string $thread): Analysis
    {
        $analysis = $this->analyze($thread);

        $intent = $this->intentDetectionEngine->resolve($thread, $analysis);
        $analysis['intent_id'] = $intent?->id;

        return $this->save($conversation, $analysis);
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

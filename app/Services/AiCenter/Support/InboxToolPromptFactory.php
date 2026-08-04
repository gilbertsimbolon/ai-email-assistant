<?php

namespace App\Services\AiCenter\Support;

/**
 * Builds the fixed JSON-extraction prompts for the manual Inbox toolbar
 * actions that are genuinely AI (Summarize/Translate/Detect Intent/
 * Sentiment), same "system establishes the task and JSON shape, user
 * supplies the thread" pattern as AnalysisPromptFactory. Extract Info is
 * NOT here — claude.txt Task 1 moved it to a direct GHL read
 * (InboxToolsService::extractInformation), no prompt needed.
 */
class InboxToolPromptFactory
{
    /**
     * @return array<int, array{role: string, content: string}>
     */
    public function summarize(string $thread): array
    {
        return $this->build(<<<PROMPT
You are a senior customer support analyst. Read the ENTIRE email thread below and summarize it.

Return ONLY valid JSON with this exact shape:

{
    "conversation_summary": "...",
    "customer_problem": "...",
    "current_status": "...",
    "pending_questions": "...",
    "suggested_action": "...",
    "risk_level": "Low | Medium | High",
    "timeline": "...",
    "important_notes": "...",
    "estimated_intent": "...",
    "recommended_reply": "..."
}

Do not return markdown. Do not wrap the JSON inside code blocks.
PROMPT, $thread);
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    public function translate(string $thread, string $languageLabel): array
    {
        return $this->build(<<<PROMPT
You are a professional translator. Translate the ENTIRE email thread below into {$languageLabel}.
Preserve the meaning, tone, and paragraph breaks. Never change or invent content — translate only.

Return ONLY valid JSON with this exact shape:

{
    "translated_text": "..."
}

Do not return markdown. Do not wrap the JSON inside code blocks.
PROMPT, $thread);
    }

    /**
     * @param  array<int, string>  $knownIntentNames
     * @return array<int, array{role: string, content: string}>
     */
    public function detectIntent(string $thread, array $knownIntentNames = []): array
    {
        $hint = $knownIntentNames === []
            ? ''
            : "\n\nIf applicable, prefer one of these known intent labels: ".implode(', ', $knownIntentNames).'.';

        return $this->build(<<<PROMPT
You are a senior customer support analyst. Read the ENTIRE email thread below and predict the customer's intent.{$hint}

Return ONLY valid JSON with this exact shape:

{
    "intent": "...",
    "confidence_score": 0.0,
    "reasoning": "..."
}

Do not return markdown. Do not wrap the JSON inside code blocks.
PROMPT, $thread);
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    public function sentiment(string $thread): array
    {
        return $this->build(<<<PROMPT
You are a senior customer support analyst. Read the ENTIRE email thread below and assess the customer's emotional state.

Return ONLY valid JSON with this exact shape:

{
    "emotion": "...",
    "frustration_level": "Low | Medium | High",
    "urgency": "Low | Medium | High",
    "customer_satisfaction": "Low | Medium | High",
    "risk_score": 0.0,
    "priority": "Low | Medium | High | Critical"
}

Do not return markdown. Do not wrap the JSON inside code blocks.
PROMPT, $thread);
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    protected function build(string $systemPrompt, string $thread): array
    {
        return [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $thread],
        ];
    }
}

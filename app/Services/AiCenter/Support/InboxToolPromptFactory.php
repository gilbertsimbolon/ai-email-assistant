<?php

namespace App\Services\AiCenter\Support;

/**
 * Builds the fixed JSON-extraction prompts for the 5 manual Inbox toolbar
 * actions (Summarize/Translate/Detect Intent/Extract Info/Sentiment), same
 * "system establishes the task and JSON shape, user supplies the thread"
 * pattern as AnalysisPromptFactory.
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
    public function extractInformation(string $thread): array
    {
        return $this->build(<<<PROMPT
You are a data extraction assistant. Read the ENTIRE email thread below and extract any customer/order information mentioned.
Use an empty string for any field that is not mentioned anywhere in the thread — never guess.

Return ONLY valid JSON with this exact shape:

{
    "customer_name": "...",
    "email": "...",
    "order_number": "...",
    "invoice_number": "...",
    "subscription": "...",
    "product": "...",
    "platform": "...",
    "purchase_date": "...",
    "refund_eligibility": "...",
    "important_dates": "..."
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

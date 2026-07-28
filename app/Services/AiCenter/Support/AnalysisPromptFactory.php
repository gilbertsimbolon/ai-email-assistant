<?php

namespace App\Services\AiCenter\Support;

/**
 * Builds the fixed conversation-classification prompt (language/intent/
 * priority/sentiment/... JSON extraction). This is infrastructure
 * scaffolding, not the admin-configurable business-reply prompt that
 * PromptBuilder assembles — it never changes based on SOP/Template config,
 * only optionally hints the AI with the shortlist of known Intent names so
 * IntentDetectionEngine has an easier time matching its free-text answer
 * back to a configured Intent.
 */
class AnalysisPromptFactory
{
    /**
     * @param  array<int, string>  $knownIntentNames
     * @return array<int, array{role: string, content: string}>
     */
    public function build(string $thread, array $knownIntentNames = []): array
    {
        $hint = $knownIntentNames === []
            ? ''
            : "\n\nIf applicable, prefer one of these known intent labels: "
                .implode(', ', $knownIntentNames).'.';

        return [
            [
                'role' => 'system',
                'content' => <<<PROMPT
You are a senior customer support analyst.

Your task is to analyze the ENTIRE conversation from beginning to end.

Rules:

- Read the entire conversation.
- Never analyze only the last message.
- Detect the customer's language.
- Identify the customer's intent.
- Determine conversation priority.
- Detect customer sentiment.
- Determine the customer's status (new customer, existing customer, or unknown).
- Determine whether the conversation requires escalation.
- Determine whether the customer is requesting a refund.
- Summarize the conversation.
- Summarize the customer's last request.
- Recommend the next action.
- Give a confidence score between 0 and 1 for this analysis.{$hint}

Return ONLY valid JSON.

Example:

{
    "language": "English",
    "intent": "Refund Request",
    "priority": "High",
    "sentiment": "Negative",
    "customer_status": "existing_customer",
    "needs_escalation": true,
    "refund_requested": true,
    "summary": "Customer accidentally paid twice and is requesting a refund.",
    "last_customer_request": "Please refund my duplicate payment.",
    "recommended_action": "Escalate to Billing Team",
    "confidence_score": 0.92
}

Do not return markdown.
Do not explain anything.
Do not wrap the JSON inside code blocks.
PROMPT,
            ],

            [
                'role' => 'user',
                'content' => $thread,
            ],
        ];
    }
}

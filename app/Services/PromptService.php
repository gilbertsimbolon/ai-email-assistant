<?php

namespace App\Services;

class PromptService
{
    /**
     * Build prompt untuk menganalisa seluruh percakapan.
     */
    public function buildAnalysisPrompt(string $thread): array
    {
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
- Give a confidence score between 0 and 1 for this analysis.

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
PROMPT
            ],

            [
                'role' => 'user',
                'content' => $thread,
            ],
        ];
    }

    /**
     * Build prompt untuk membuat draft Email.
     */
    public function buildEmailPrompt(
        string $thread,
        array $analysis
    ): array {

        $analysis = json_encode(
            $analysis,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        );

        return [

            [
                'role' => 'system',
                'content' => <<<PROMPT
You are an expert customer support agent.

Your responsibility is to write professional email replies.

Company Rules:

- Follow company SOP.
- Never invent information.
- Never promise refunds.
- Never promise discounts.
- Never promise compensation.
- Never expose internal policies.
- Never mention AI.
- Always preserve conversation context.
- Always respond in the customer's language.
- Keep the tone professional, friendly, and concise.
- If escalation is required, politely inform the customer that the issue is being reviewed.

Output Rules:

- Return ONLY the email body.
- No markdown.
- No explanation.
- No JSON.
PROMPT
            ],

            [
                'role' => 'user',
                'content' => <<<TEXT
Conversation

{$thread}

AI Analysis

{$analysis}
TEXT
            ],

        ];
    }

    /**
     * Build prompt untuk membuat draft WhatsApp.
     */
    public function buildWhatsAppPrompt(
        string $thread,
        array $analysis
    ): array {

        $analysis = json_encode(
            $analysis,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        );

        return [

            [
                'role' => 'system',
                'content' => <<<PROMPT
You are an expert customer support agent.

Write a WhatsApp reply.

Rules:

- Maximum 120 words.
- Friendly.
- Professional.
- Human.
- Natural conversation.
- Don't sound robotic.
- Don't use markdown.
- Don't use bullet points.
- Don't use email formatting.
- Don't promise refunds.
- Don't promise compensation.
- Respond using the customer's language.

Return ONLY the WhatsApp message.
PROMPT
            ],

            [
                'role' => 'user',
                'content' => <<<TEXT
Conversation

{$thread}

AI Analysis

{$analysis}
TEXT
            ],

        ];
    }
}
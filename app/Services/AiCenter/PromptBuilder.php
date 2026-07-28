<?php

namespace App\Services\AiCenter;

use App\Enums\ChannelType;
use App\Models\AiCenter\AiCenterSetting;
use App\Services\AiCenter\DataTransferObjects\PromptBuildResult;
use App\Services\AiCenter\DataTransferObjects\PromptContext;

/**
 * Assembles the final AI prompt deterministically from Intent/SOP/Rule/
 * Workflow/Knowledge Base/Template/Conversation — no admin ever writes raw
 * prompt text. Section order matches claude.txt's Prompt Preview example:
 * Role / Business Rules / SOP / Knowledge / Template / Customer Conversation
 * / Generate Draft.
 *
 * When no SOP matches (fresh install / nothing configured yet), the SOP/
 * Knowledge/Template sections are simply omitted and Business Rules falls
 * back to a sensible generic instruction set — this keeps unconfigured
 * installs behaving exactly like the old hardcoded PromptService prompts.
 */
class PromptBuilder
{
    public function build(PromptContext $context): PromptBuildResult
    {
        $sections = [
            'role' => $this->buildRoleSection($context),
            'business_rules' => $this->buildBusinessRulesSection($context),
            'sop' => $this->buildSopSection($context),
            'knowledge' => $this->buildKnowledgeSection($context),
            'template' => $this->buildTemplateSection($context),
            'customer_conversation' => $this->buildCustomerConversationSection($context),
            'generate_draft' => $this->buildGenerateDraftSection($context),
        ];

        $systemContent = collect([
            $sections['role'],
            $sections['business_rules'],
            $sections['sop'],
            $sections['knowledge'],
            $sections['template'],
            $sections['generate_draft'],
        ])->filter(fn ($section) => trim($section) !== '')->implode("\n\n");

        $messages = [
            ['role' => 'system', 'content' => $systemContent],
            ['role' => 'user', 'content' => $sections['customer_conversation']],
        ];

        return new PromptBuildResult($messages, $sections);
    }

    protected function buildRoleSection(PromptContext $context): string
    {
        $isWhatsApp = $context->conversation->channel === ChannelType::WhatsApp;

        return $isWhatsApp
            ? "Role\n\nYou are an expert customer support agent writing WhatsApp replies."
            : "Role\n\nYou are an expert customer support agent writing professional email replies.";
    }

    protected function buildBusinessRulesSection(PromptContext $context): string
    {
        $tone = $context->rule?->tone?->label()
            ?? AiCenterSetting::current()?->default_fallback_tone
            ?? 'professional';

        $lines = [
            'Business Rules',
            '',
            '- Never invent information.',
            '- Never promise refunds, discounts, or compensation unless explicitly instructed below.',
            '- Never expose internal policies.',
            '- Never mention that you are an AI.',
            '- Always preserve conversation context.',
            '- Always respond in the customer\'s language.',
            "- Keep the tone {$tone}.",
        ];

        if ($context->rule?->escalation_target) {
            $lines[] = "- If escalation is required, politely inform the customer the issue is being routed to {$context->rule->escalation_target->label()}.";
        } else {
            $lines[] = '- If escalation is required, politely inform the customer that the issue is being reviewed.';
        }

        if ($context->forbiddenActions->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'The AI must NEVER, under any circumstance:';

            foreach ($context->forbiddenActions as $forbidden) {
                $lines[] = "- {$forbidden->label}";
            }
        }

        return implode("\n", $lines);
    }

    protected function buildSopSection(PromptContext $context): string
    {
        if (! $context->sop) {
            return '';
        }

        $lines = ['SOP', '', "Name: {$context->sop->name}"];

        if ($context->sop->description) {
            $lines[] = "Description: {$context->sop->description}";
        }

        if ($context->rule?->name) {
            $lines[] = "Matched Rule: {$context->rule->name}";
        }

        return implode("\n", $lines);
    }

    protected function buildKnowledgeSection(PromptContext $context): string
    {
        if ($context->knowledgeBases->isEmpty()) {
            return '';
        }

        $lines = ['Knowledge', ''];

        foreach ($context->knowledgeBases as $kb) {
            $lines[] = "## {$kb->title}";
            $lines[] = $kb->content;
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    protected function buildTemplateSection(PromptContext $context): string
    {
        if (! $context->replyTemplate) {
            return '';
        }

        $body = $this->substituteVariables($context->replyTemplate->body, $context->templateVariables);

        return "Template\n\nUse the following template as the format guide for your reply, adapting it naturally to the conversation:\n\n{$body}";
    }

    protected function buildCustomerConversationSection(PromptContext $context): string
    {
        return "Customer Conversation\n\n{$context->thread}";
    }

    protected function buildGenerateDraftSection(PromptContext $context): string
    {
        $isWhatsApp = $context->conversation->channel === ChannelType::WhatsApp;

        if ($isWhatsApp) {
            return <<<PROMPT
Generate Draft

Output Rules:

- Maximum 120 words.
- Natural, human, conversational.
- No markdown, no bullet points, no email formatting.
- Return ONLY the WhatsApp message.
PROMPT;
        }

        return <<<PROMPT
Generate Draft

Output Rules:

- Return ONLY the email body.
- No markdown.
- No explanation.
- No JSON.
PROMPT;
    }

    /**
     * @param  array<string, string>  $variables
     */
    protected function substituteVariables(string $body, array $variables): string
    {
        $replacements = [];

        foreach ($variables as $key => $value) {
            $replacements['{{'.$key.'}}'] = $value;
        }

        return strtr($body, $replacements);
    }
}

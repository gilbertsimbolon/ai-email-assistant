<?php

namespace App\Services\AiCenter;

use App\Enums\AiCenter\AiCenterLogSource;
use App\Enums\AiCenter\AiCenterLogStatus;
use App\Models\AiCenter\AiLog;
use App\Models\AiCenter\AiModel;
use App\Models\Analysis;
use App\Models\Conversation;
use App\Services\AI\Contracts\AiClientInterface;
use App\Services\AiCenter\DataTransferObjects\PipelineResult;
use App\Services\AiCenter\DataTransferObjects\PromptContext;
use App\Services\AiCenter\DataTransferObjects\WorkflowRunResult;
use App\Services\AiCenter\Engines\RuleEngine;
use App\Services\AiCenter\Engines\SopMatchingEngine;
use App\Services\AiCenter\Engines\WorkflowEngine;
use Throwable;

/**
 * Orchestrates the full AI Center pipeline: SOP Matching -> Rule Engine ->
 * Workflow Engine -> Knowledge Base Retrieval -> Reply Template Selection ->
 * Prompt Builder -> AI Generate Draft, persisting one AiLog row per run.
 * Intent Detection has already run by the time this is called (it's folded
 * into AnalysisService, see Analysis::intent_id) so both production and
 * Playground share this exact same code path — the only difference is
 * whether $conversation/$analysis are persisted Eloquent models or
 * ephemeral, unsaved ones.
 */
class AiCenterPipeline
{
    public function __construct(
        protected SopMatchingEngine $sopMatcher,
        protected RuleEngine $ruleEngine,
        protected WorkflowEngine $workflowEngine,
        protected KnowledgeResolver $knowledgeResolver,
        protected ReplyTemplateResolver $templateResolver,
        protected TemplateVariableResolver $variableResolver,
        protected PromptBuilder $promptBuilder,
        protected AiClientInterface $aiClient,
    ) {
    }

    /**
     * Runs every stage up to and including the Prompt Builder (SOP Matching
     * -> Rule Engine -> Workflow Engine -> Knowledge Base Retrieval -> Reply
     * Template Selection -> Prompt Builder) without calling the AI provider
     * or persisting an AiLog. Shared by run() and the read-only Prompt
     * Preview page (AiCenterPromptPreviewController), which must never
     * spend tokens or write logs just to show an admin what the prompt
     * would look like.
     */
    public function preview(Conversation $conversation, ?Analysis $analysis, string $thread): PromptContext
    {
        return $this->assemble($conversation, $analysis, $thread)[0];
    }

    /**
     * @return array{0: PromptContext, 1: WorkflowRunResult}
     */
    protected function assemble(Conversation $conversation, ?Analysis $analysis, string $thread): array
    {
        $intent = $analysis?->intent;

        $sopMatch = $this->sopMatcher->match($conversation, $intent, $thread);
        $sop = $sopMatch->sop;

        $ruleResult = $this->ruleEngine->evaluate($sop, $conversation, $analysis, $intent);
        $workflowResult = $this->workflowEngine->run($sop, $conversation, $analysis, $intent);

        $knowledgeBases = $this->knowledgeResolver->resolve($sop);
        $replyTemplate = $this->templateResolver->resolve($sop, $ruleResult->actions);
        $templateVariables = $this->variableResolver->resolve($conversation, $analysis);

        $context = new PromptContext(
            conversation: $conversation,
            analysis: $analysis,
            intent: $intent,
            sop: $sop,
            rule: $ruleResult->rule,
            ruleActions: $ruleResult->actions,
            forbiddenActions: $sop?->forbiddenActions ?? collect(),
            knowledgeBases: $knowledgeBases,
            replyTemplate: $replyTemplate,
            templateVariables: $templateVariables,
            thread: $thread,
        );

        return [$context, $workflowResult];
    }

    public function run(
        Conversation $conversation,
        ?Analysis $analysis,
        string $thread,
        AiCenterLogSource $source,
        ?int $triggeredByUserId,
    ): PipelineResult {
        [$context, $workflowResult] = $this->assemble($conversation, $analysis, $thread);
        $intent = $context->intent;
        $sop = $context->sop;
        $knowledgeBases = $context->knowledgeBases;
        $replyTemplate = $context->replyTemplate;
        $rule = $context->rule;
        $ruleActions = $context->ruleActions;

        $promptResult = $this->promptBuilder->build($context);

        $status = AiCenterLogStatus::Success;
        $error = null;
        $exception = null;
        $response = ['content' => '', 'usage' => []];

        $startedAt = microtime(true);

        try {
            $response = $this->aiClient->chat($promptResult->messages);
        } catch (Throwable $e) {
            $status = AiCenterLogStatus::Failed;
            $error = $e->getMessage();
            $exception = $e;
        }

        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        $aiModel = AiModel::default();

        $log = AiLog::create([
            'source' => $source,
            'conversation_id' => $conversation->exists ? $conversation->id : null,
            'intent_id' => $intent?->id,
            'sop_id' => $sop?->id,
            'workflow_id' => $sop?->workflow_id,
            'reply_template_id' => $replyTemplate?->id,
            'ai_model_id' => $aiModel?->id,
            'triggered_by' => $triggeredByUserId,
            'matched_rule_ids' => $rule ? [$rule->id] : [],
            'matched_action_types' => $ruleActions->pluck('action_type')->map(fn ($a) => $a->value)->values()->all(),
            'matched_knowledge_base_ids' => $knowledgeBases->pluck('id')->values()->all(),
            'prompt' => $this->flattenMessages($promptResult->messages),
            'response' => $response['content'] ?? null,
            'prompt_tokens' => $response['usage']['prompt_tokens'] ?? null,
            'completion_tokens' => $response['usage']['completion_tokens'] ?? null,
            'total_tokens' => $response['usage']['total_tokens'] ?? null,
            'latency_ms' => $latencyMs,
            'confidence_score' => $analysis?->confidence_score,
            'status' => $status,
            'error' => $error,
        ]);

        if ($exception) {
            throw $exception;
        }

        return new PipelineResult(
            intent: $intent,
            sop: $sop,
            rule: $rule,
            ruleActions: $ruleActions,
            workflowActions: $workflowResult->actions,
            knowledgeBases: $knowledgeBases,
            replyTemplate: $replyTemplate,
            prompt: $promptResult,
            draftContent: $response['content'],
            usage: $response['usage'] ?? [],
            latencyMs: $latencyMs,
            confidenceScore: $analysis?->confidence_score !== null ? (float) $analysis->confidence_score : null,
            log: $log,
        );
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    protected function flattenMessages(array $messages): string
    {
        return collect($messages)
            ->map(fn (array $m) => strtoupper($m['role']).":\n".$m['content'])
            ->implode("\n\n---\n\n");
    }
}

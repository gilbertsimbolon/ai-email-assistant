<?php

namespace App\Services\AiCenter\DataTransferObjects;

use App\Models\AiCenter\AiLog;
use App\Models\AiCenter\Intent;
use App\Models\AiCenter\ReplyTemplate;
use App\Models\AiCenter\Sop;
use App\Models\AiCenter\SopRule;
use Illuminate\Support\Collection;

final class PipelineResult
{
    /**
     * @param  Collection<int, \App\Models\AiCenter\SopAction>  $ruleActions
     * @param  Collection<int, \App\Models\AiCenter\WorkflowNode>  $workflowActions
     * @param  Collection<int, \App\Models\AiCenter\KnowledgeBase>  $knowledgeBases
     * @param  array<string, mixed>  $usage
     */
    public function __construct(
        public readonly ?Intent $intent,
        public readonly ?Sop $sop,
        public readonly ?SopRule $rule,
        public readonly Collection $ruleActions,
        public readonly Collection $workflowActions,
        public readonly Collection $knowledgeBases,
        public readonly ?ReplyTemplate $replyTemplate,
        public readonly PromptBuildResult $prompt,
        public readonly string $draftContent,
        public readonly array $usage,
        public readonly int $latencyMs,
        public readonly ?float $confidenceScore,
        public readonly AiLog $log,
    ) {
    }
}

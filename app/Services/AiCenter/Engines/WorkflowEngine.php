<?php

namespace App\Services\AiCenter\Engines;

use App\Enums\AiCenter\ConditionField;
use App\Enums\AiCenter\ConditionOperator;
use App\Enums\AiCenter\EdgeBranch;
use App\Enums\AiCenter\WorkflowNodeType;
use App\Models\AiCenter\Intent;
use App\Models\AiCenter\Sop;
use App\Models\AiCenter\WorkflowNode;
use App\Models\Analysis;
use App\Models\Conversation;
use App\Services\AiCenter\DataTransferObjects\WorkflowRunResult;
use App\Services\AiCenter\Support\ConditionEvaluator;

/**
 * Walks a SOP's linked Workflow's node/edge graph from its single `start`
 * node, using ConditionEvaluator for `condition` nodes' yes/no branches, and
 * collecting every `action` node visited. Capped at 50 hops as a guard
 * against a misconfigured cyclic graph (the admin UI also validates
 * acyclic-ness at save time).
 */
class WorkflowEngine
{
    protected const MAX_HOPS = 50;

    public function __construct(
        protected ConditionEvaluator $evaluator,
    ) {
    }

    public function run(?Sop $sop, Conversation $conversation, ?Analysis $analysis, ?Intent $intent): WorkflowRunResult
    {
        if (! $sop || ! $sop->workflow_id || ! $sop->workflow) {
            return new WorkflowRunResult(collect());
        }

        $current = $sop->workflow->startNode();
        $actions = collect();
        $visited = [];
        $hops = 0;

        while ($current && $hops < self::MAX_HOPS) {
            if (isset($visited[$current->id])) {
                break;
            }

            $visited[$current->id] = true;
            $hops++;

            if ($current->type === WorkflowNodeType::Action) {
                $actions->push($current);
            }

            $current = $this->nextNode($current, $conversation, $analysis, $intent);
        }

        return new WorkflowRunResult($actions);
    }

    protected function nextNode(WorkflowNode $node, Conversation $conversation, ?Analysis $analysis, ?Intent $intent): ?WorkflowNode
    {
        $edges = $node->outgoingEdges;

        if ($node->type === WorkflowNodeType::Condition) {
            $config = $node->config ?? [];

            if (! isset($config['field'], $config['operator'])) {
                return null;
            }

            $matches = $this->evaluator->evaluate(
                ConditionField::from($config['field']),
                ConditionOperator::from($config['operator']),
                $config['value'] ?? null,
                $conversation,
                $analysis,
                $intent,
            );

            $branch = $matches ? EdgeBranch::Yes : EdgeBranch::No;

            $edge = $edges->first(fn ($e) => $e->branch === $branch);

            return $edge?->toNode;
        }

        $edge = $edges->first(fn ($e) => $e->branch === EdgeBranch::Default) ?? $edges->first();

        return $edge?->toNode;
    }
}

<?php

namespace App\Services\AiCenter\Engines;

use App\Enums\AiCenter\BooleanOperator;
use App\Models\AiCenter\Intent;
use App\Models\AiCenter\Sop;
use App\Models\AiCenter\SopRule;
use App\Models\Analysis;
use App\Models\Conversation;
use App\Services\AiCenter\DataTransferObjects\RuleEvaluationResult;
use App\Services\AiCenter\Support\ConditionEvaluator;
use Illuminate\Support\Collection;

/**
 * Iterates a SOP's rules in order; the first rule whose conditions all
 * match (combined left-to-right by each condition's boolean_operator)
 * wins — this mirrors the spec's literal if/else-if example rather than
 * picking the "best scoring" rule.
 */
class RuleEngine
{
    public function __construct(
        protected ConditionEvaluator $evaluator,
    ) {
    }

    public function evaluate(?Sop $sop, Conversation $conversation, ?Analysis $analysis, ?Intent $intent): RuleEvaluationResult
    {
        if (! $sop) {
            return new RuleEvaluationResult(null, collect());
        }

        foreach ($sop->rules as $rule) {
            if ($this->ruleMatches($rule, $conversation, $analysis, $intent)) {
                return new RuleEvaluationResult($rule, $rule->actions);
            }
        }

        return new RuleEvaluationResult(null, collect());
    }

    protected function ruleMatches(SopRule $rule, Conversation $conversation, ?Analysis $analysis, ?Intent $intent): bool
    {
        /** @var Collection $conditions */
        $conditions = $rule->conditions;

        if ($conditions->isEmpty()) {
            // A rule with no conditions is an unconditional catch-all.
            return true;
        }

        $result = null;

        foreach ($conditions as $condition) {
            $value = $this->evaluator->evaluate(
                $condition->field,
                $condition->operator,
                $condition->value,
                $conversation,
                $analysis,
                $intent,
            );

            $result = $result === null
                ? $value
                : ($condition->boolean_operator === BooleanOperator::Or ? ($result || $value) : ($result && $value));
        }

        return (bool) $result;
    }
}

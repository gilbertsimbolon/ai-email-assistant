<?php

namespace App\Services\AiCenter\Support;

use App\Enums\AiCenter\ConditionField;
use App\Enums\AiCenter\ConditionOperator;
use App\Models\AiCenter\Intent;
use App\Models\Analysis;
use App\Models\Conversation;

/**
 * Single source of truth for resolving a ConditionField to a live value and
 * comparing it per ConditionOperator — shared verbatim by RuleEngine (SOP
 * rule conditions) and WorkflowEngine (workflow condition nodes) so the two
 * engines can never silently disagree on what a condition means.
 */
class ConditionEvaluator
{
    public function evaluate(
        ConditionField $field,
        ConditionOperator $operator,
        ?string $expected,
        Conversation $conversation,
        ?Analysis $analysis,
        ?Intent $intent,
    ): bool {
        $actual = $this->resolveValue($field, $conversation, $analysis, $intent);

        return $this->compare($actual, $operator, $expected);
    }

    protected function resolveValue(
        ConditionField $field,
        Conversation $conversation,
        ?Analysis $analysis,
        ?Intent $intent,
    ): mixed {
        if ($field->isMetadataBacked()) {
            return ($conversation->metadata ?? [])[$field->value] ?? null;
        }

        return match ($field) {
            ConditionField::Intent => $intent?->name,
            ConditionField::Priority => $analysis?->priority?->value,
            ConditionField::Sentiment => $analysis?->sentiment?->value,
            ConditionField::CustomerStatus => $analysis?->customer_status?->value,
            ConditionField::RefundRequested => $analysis?->refund_requested,
            ConditionField::EscalationRequired => $analysis?->escalation_required,
            ConditionField::Channel => $conversation->channelValue(),
            ConditionField::ConfidenceScore => $analysis?->confidence_score,
            default => null,
        };
    }

    protected function compare(mixed $actual, ConditionOperator $operator, ?string $expected): bool
    {
        return match ($operator) {
            ConditionOperator::Exists => $actual !== null && $actual !== '',
            ConditionOperator::IsTrue => (bool) $actual === true,
            ConditionOperator::IsFalse => ! $actual,
            ConditionOperator::Equals => $this->looseEquals($actual, $expected),
            ConditionOperator::NotEquals => ! $this->looseEquals($actual, $expected),
            ConditionOperator::Contains => $actual !== null && $expected !== null
                && str_contains(strtolower((string) $actual), strtolower($expected)),
            ConditionOperator::GreaterThan => $this->numericCompare($actual, $expected, fn ($a, $b) => $a > $b),
            ConditionOperator::LessThan => $this->numericCompare($actual, $expected, fn ($a, $b) => $a < $b),
            ConditionOperator::GreaterOrEqual => $this->numericCompare($actual, $expected, fn ($a, $b) => $a >= $b),
            ConditionOperator::LessOrEqual => $this->numericCompare($actual, $expected, fn ($a, $b) => $a <= $b),
        };
    }

    protected function looseEquals(mixed $actual, ?string $expected): bool
    {
        if ($actual === null || $expected === null) {
            return false;
        }

        if (is_bool($actual)) {
            return $actual === filter_var($expected, FILTER_VALIDATE_BOOLEAN);
        }

        return strtolower((string) $actual) === strtolower(trim($expected));
    }

    protected function numericCompare(mixed $actual, ?string $expected, callable $compare): bool
    {
        if ($actual === null || $expected === null || ! is_numeric($actual) || ! is_numeric($expected)) {
            return false;
        }

        return $compare((float) $actual, (float) $expected);
    }
}

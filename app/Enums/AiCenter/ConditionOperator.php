<?php

namespace App\Enums\AiCenter;

enum ConditionOperator: string
{
    case Equals = 'equals';
    case NotEquals = 'not_equals';
    case GreaterThan = 'greater_than';
    case LessThan = 'less_than';
    case GreaterOrEqual = 'greater_or_equal';
    case LessOrEqual = 'less_or_equal';
    case Contains = 'contains';
    case Exists = 'exists';
    case IsTrue = 'is_true';
    case IsFalse = 'is_false';

    public function label(): string
    {
        return match ($this) {
            self::Equals => '=',
            self::NotEquals => '!=',
            self::GreaterThan => '>',
            self::LessThan => '<',
            self::GreaterOrEqual => '>=',
            self::LessOrEqual => '<=',
            self::Contains => 'contains',
            self::Exists => 'exists',
            self::IsTrue => 'is true',
            self::IsFalse => 'is false',
        };
    }

    /**
     * Whether this operator needs a comparison value (Exists/IsTrue/IsFalse
     * are self-contained and ignore SopCondition::value).
     */
    public function requiresValue(): bool
    {
        return ! in_array($this, [self::Exists, self::IsTrue, self::IsFalse], true);
    }
}

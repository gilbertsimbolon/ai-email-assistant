<?php

namespace App\Enums\AiCenter;

enum WorkflowNodeType: string
{
    case Start = 'start';
    case IntentDetection = 'intent_detection';
    case Condition = 'condition';
    case Action = 'action';
    case End = 'end';

    public function label(): string
    {
        return match ($this) {
            self::Start => 'Start',
            self::IntentDetection => 'Intent Detection',
            self::Condition => 'Condition',
            self::Action => 'Action',
            self::End => 'End',
        };
    }
}

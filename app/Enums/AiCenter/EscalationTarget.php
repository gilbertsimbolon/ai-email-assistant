<?php

namespace App\Enums\AiCenter;

enum EscalationTarget: string
{
    case Supervisor = 'supervisor';
    case BillingTeam = 'billing_team';
    case SupportTeam = 'support_team';
    case TechnicalTeam = 'technical_team';
    case HumanAgent = 'human_agent';
    case PriorityQueue = 'priority_queue';

    public function label(): string
    {
        return match ($this) {
            self::Supervisor => 'Supervisor',
            self::BillingTeam => 'Billing Team',
            self::SupportTeam => 'Support Team',
            self::TechnicalTeam => 'Technical Team',
            self::HumanAgent => 'Human Agent',
            self::PriorityQueue => 'Priority Queue',
        };
    }
}

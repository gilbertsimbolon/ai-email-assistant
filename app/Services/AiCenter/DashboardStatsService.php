<?php

namespace App\Services\AiCenter;

use App\Enums\DraftStatus;
use App\Models\AiCenter\AiLog;
use App\Models\AiCenter\AiModel;
use App\Models\AiCenter\Intent;
use App\Models\AiCenter\KnowledgeBase;
use App\Models\AiCenter\ReplyTemplate;
use App\Models\AiCenter\Sop;
use App\Models\AiCenter\SopRule;
use App\Models\AiCenter\Workflow;
use App\Models\Draft;

class DashboardStatsService
{
    /**
     * @return array<string, mixed>
     */
    public function compute(): array
    {
        $defaultModel = AiModel::default();

        $todayLogs = AiLog::query()->whereDate('created_at', today());

        $approvedOrSent = Draft::query()->whereIn('status', [DraftStatus::Approved, DraftStatus::Sent])->count();
        $totalDrafts = Draft::query()->count();

        return [
            'ai_provider' => $defaultModel?->provider?->label(),
            'active_model' => $defaultModel?->model,
            'total_sops' => Sop::query()->count(),
            'total_rules' => SopRule::query()->count(),
            'total_workflows' => Workflow::query()->count(),
            'total_intents' => Intent::query()->count(),
            'total_templates' => ReplyTemplate::query()->count(),
            'total_knowledge_bases' => KnowledgeBase::query()->count(),
            'requests_today' => (clone $todayLogs)->count(),
            'tokens_today' => (clone $todayLogs)->sum('total_tokens'),
            'avg_response_time_ms' => (int) round((clone $todayLogs)->avg('latency_ms') ?? 0),
            'estimated_cost_today' => (float) (clone $todayLogs)->sum('cost'),
            'drafts_generated' => $totalDrafts,
            'human_approval_rate' => $totalDrafts > 0
                ? round(($approvedOrSent / $totalDrafts) * 100, 1)
                : 0.0,
        ];
    }
}

<?php

namespace App\Services\Reports;

use App\Enums\AiCenter\AiCenterLogStatus;
use App\Enums\DraftStatus;
use App\Models\AiCenter\AiLog;
use App\Models\AiCenter\KnowledgeBase;
use App\Models\AiCenter\ReplyTemplate;
use App\Models\AiCenter\Sop;
use App\Models\AiCenter\Workflow;
use App\Models\Draft;
use Carbon\Carbon;

/**
 * Knowledge / SOP / Reply Template / Workflow Analytics — all four are
 * config-entity tables (small row counts), aggregated against `ai_logs`
 * (usage/success) and, for Reply Templates, against the drafts those logs
 * produced (edit rate).
 */
class ContentAnalyticsService
{
    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function compute(Carbon $start, Carbon $end): array
    {
        return [
            'knowledge' => $this->knowledgeAnalytics($start, $end),
            'sops' => $this->sopAnalytics($start, $end),
            'reply_templates' => $this->replyTemplateAnalytics($start, $end),
            'workflows' => $this->workflowAnalytics($start, $end),
        ];
    }

    /**
     * Single-query tally over `ai_logs.matched_knowledge_base_ids` (a JSON
     * array) instead of one whereJsonContains() query per knowledge base.
     */
    protected function knowledgeAnalytics(Carbon $start, Carbon $end): array
    {
        $knowledgeBases = KnowledgeBase::query()->get(['id', 'title', 'type']);

        $usage = [];
        $lastUsed = [];

        AiLog::query()
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('matched_knowledge_base_ids')
            ->get(['matched_knowledge_base_ids', 'created_at'])
            ->each(function (AiLog $log) use (&$usage, &$lastUsed) {
                foreach ($log->matched_knowledge_base_ids ?? [] as $kbId) {
                    $usage[$kbId] = ($usage[$kbId] ?? 0) + 1;

                    if (!isset($lastUsed[$kbId]) || $log->created_at->greaterThan($lastUsed[$kbId])) {
                        $lastUsed[$kbId] = $log->created_at;
                    }
                }
            });

        return $knowledgeBases
            ->map(fn (KnowledgeBase $kb) => [
                'title' => $kb->title,
                'type' => $kb->type?->value,
                'usage_count' => $usage[$kb->id] ?? 0,
                'last_used' => $lastUsed[$kb->id] ?? null,
            ])
            ->filter(fn ($row) => $row['usage_count'] > 0)
            ->sortByDesc('usage_count')
            ->values()
            ->all();
    }

    protected function sopAnalytics(Carbon $start, Carbon $end): array
    {
        return Sop::query()
            ->withCount(['aiLogs as usage_count' => fn ($q) => $q->whereBetween('created_at', [$start, $end])])
            ->withCount(['aiLogs as success_count' => fn ($q) => $q->whereBetween('created_at', [$start, $end])->where('status', AiCenterLogStatus::Success)])
            ->withMax(['aiLogs as last_used' => fn ($q) => $q->whereBetween('created_at', [$start, $end])], 'created_at')
            ->having('usage_count', '>', 0)
            ->orderByDesc('usage_count')
            ->get()
            ->map(fn (Sop $sop) => [
                'name' => $sop->name,
                'usage_count' => (int) $sop->usage_count,
                'success_rate' => $sop->usage_count > 0 ? round($sop->success_count / $sop->usage_count * 100, 1) : 0.0,
                'last_used' => $sop->last_used,
            ])
            ->values()
            ->all();
    }

    protected function replyTemplateAnalytics(Carbon $start, Carbon $end): array
    {
        $logs = AiLog::query()
            ->whereNotNull('reply_template_id')
            ->whereBetween('created_at', [$start, $end])
            ->get(['id', 'reply_template_id']);

        $draftsByLog = Draft::query()
            ->whereIn('ai_log_id', $logs->pluck('id'))
            ->get(['ai_log_id', 'status', 'content', 'original_content'])
            ->groupBy('ai_log_id');

        return ReplyTemplate::query()
            ->whereIn('id', $logs->pluck('reply_template_id')->unique())
            ->get(['id', 'name'])
            ->map(function (ReplyTemplate $template) use ($logs, $draftsByLog) {
                $templateLogIds = $logs->where('reply_template_id', $template->id)->pluck('id');
                $templateDrafts = $templateLogIds->flatMap(fn ($logId) => $draftsByLog->get($logId, collect()));

                $decided = $templateDrafts->whereIn('status', [DraftStatus::Approved, DraftStatus::Sent, DraftStatus::Discarded]);
                $successful = $decided->whereIn('status', [DraftStatus::Approved, DraftStatus::Sent]);

                return [
                    'name' => $template->name,
                    'usage_count' => $templateLogIds->count(),
                    'edited_by_agent' => $templateDrafts->filter->wasEditedByAgent()->count(),
                    'success_rate' => $decided->count() > 0 ? round($successful->count() / $decided->count() * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('usage_count')
            ->values()
            ->all();
    }

    protected function workflowAnalytics(Carbon $start, Carbon $end): array
    {
        return Workflow::query()
            ->withCount(['aiLogs as run_count' => fn ($q) => $q->whereBetween('created_at', [$start, $end])])
            ->withCount(['aiLogs as success_count' => fn ($q) => $q->whereBetween('created_at', [$start, $end])->where('status', AiCenterLogStatus::Success)])
            ->withCount(['aiLogs as failed_count' => fn ($q) => $q->whereBetween('created_at', [$start, $end])->where('status', AiCenterLogStatus::Failed)])
            ->withAvg(['aiLogs as avg_latency' => fn ($q) => $q->whereBetween('created_at', [$start, $end])], 'latency_ms')
            ->having('run_count', '>', 0)
            ->orderByDesc('run_count')
            ->get()
            ->map(fn (Workflow $workflow) => [
                'name' => $workflow->name,
                'run_count' => (int) $workflow->run_count,
                'success' => (int) $workflow->success_count,
                'failed' => (int) $workflow->failed_count,
                'avg_duration_ms' => (int) round($workflow->avg_latency ?? 0),
            ])
            ->values()
            ->all();
    }
}

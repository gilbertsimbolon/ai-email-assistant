<?php

namespace App\Services\Reports;

use App\Enums\ConversationStatus;
use App\Enums\DraftStatus;
use App\Enums\MessageType;
use App\Enums\SenderType;
use App\Models\AiCenter\AiLog;
use App\Models\AiCenter\Intent;
use App\Models\Conversation;
use App\Models\Draft;
use App\Models\GmailAccount;
use App\Models\Message;
use Carbon\Carbon;

/**
 * Builds the Reports landing page: 12 KPI cards (all-time totals, except the
 * two explicitly "Today" cards) plus the period-filtered Email Analytics,
 * Intent Analytics, AI Performance and AI Accuracy charts.
 */
class DashboardOverviewService
{
    public function __construct(
        protected ReportPeriodResolver $periodResolver,
        protected ResponseTimeCalculator $responseTime,
        protected ActivityTimelineService $timeline,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function compute(Carbon $start, Carbon $end, string $bucket, ?int $gmailAccountId = null): array
    {
        return [
            'kpis' => $this->kpis(),
            'email_chart' => $this->emailChart($start, $end, $bucket, $gmailAccountId),
            'intent_chart' => $this->intentChart($start, $end),
            'ai_performance_chart' => $this->aiPerformanceChart($start, $end),
            'ai_accuracy_chart' => $this->aiAccuracyChart($start, $end),
            'timeline' => $this->timeline->recent(10),
        ];
    }

    /**
     * @return array<string, int|float|string>
     */
    protected function kpis(): array
    {
        $today = Carbon::today();

        $totalDrafts = Draft::query()->whereNotNull('ai_log_id')->get(['content', 'original_content', 'status']);

        return [
            'total_email' => Message::query()->where('message_type', MessageType::Email)->count(),
            'incoming_today' => Message::query()->where('sender_type', SenderType::Customer)->whereDate('sent_at', $today)->count(),
            'outgoing_today' => Message::query()->where('sender_type', SenderType::Agent)->whereDate('sent_at', $today)->count(),
            'pending_reply' => Conversation::query()->where('status', ConversationStatus::PendingReview)->count(),
            'ai_generated_draft' => $totalDrafts->count(),
            'draft_approved' => Draft::query()->whereIn('status', [DraftStatus::Approved, DraftStatus::Sent])->count(),
            'draft_edited' => $totalDrafts->filter->wasEditedByAgent()->count(),
            'draft_rejected' => Draft::query()->where('status', DraftStatus::Discarded)->count(),
            'average_response_time' => $this->responseTime->stats()['average'],
            'estimated_ai_cost' => (float) AiLog::query()->sum('cost'),
            'total_tokens' => (int) AiLog::query()->sum('total_tokens'),
            'connected_gmail_accounts' => GmailAccount::query()->where('status', 'connected')->count(),
        ];
    }

    /**
     * @return array{labels: array<int, string>, incoming: array<int, int>, outgoing: array<int, int>, pending: array<int, int>, replied: array<int, int>}
     */
    protected function emailChart(Carbon $start, Carbon $end, string $bucket, ?int $gmailAccountId): array
    {
        $buckets = $this->periodResolver->buckets($start, $end, $bucket);
        $series = ['incoming' => [], 'outgoing' => [], 'pending' => [], 'replied' => []];

        foreach (array_keys($series) as $key) {
            $series[$key] = array_fill_keys(array_column($buckets, 'key'), 0);
        }

        Message::query()
            ->whereIn('sender_type', [SenderType::Customer, SenderType::Agent])
            ->whereBetween('sent_at', [$start, $end])
            ->when($gmailAccountId, fn ($q) => $q->whereHas('conversation', fn ($c) => $c->where('gmail_account_id', $gmailAccountId)))
            ->get(['sender_type', 'sent_at'])
            ->each(function (Message $message) use (&$series, $bucket) {
                $key = $this->periodResolver->keyFor($message->sent_at, $bucket);
                $bucketKey = $message->sender_type === SenderType::Customer ? 'incoming' : 'outgoing';

                if (array_key_exists($key, $series[$bucketKey])) {
                    $series[$bucketKey][$key]++;
                }
            });

        foreach (['pending' => ConversationStatus::PendingReview, 'replied' => ConversationStatus::Replied] as $seriesKey => $status) {
            Conversation::query()
                ->where('status', $status)
                ->whereBetween('last_message_at', [$start, $end])
                ->when($gmailAccountId, fn ($q) => $q->where('gmail_account_id', $gmailAccountId))
                ->get(['last_message_at'])
                ->each(function (Conversation $conversation) use (&$series, $seriesKey, $bucket) {
                    $key = $this->periodResolver->keyFor($conversation->last_message_at, $bucket);

                    if (array_key_exists($key, $series[$seriesKey])) {
                        $series[$seriesKey][$key]++;
                    }
                });
        }

        return [
            'labels' => array_column($buckets, 'label'),
            'incoming' => array_values($series['incoming']),
            'outgoing' => array_values($series['outgoing']),
            'pending' => array_values($series['pending']),
            'replied' => array_values($series['replied']),
        ];
    }

    /**
     * @return array{labels: array<int, string>, counts: array<int, int>, percentages: array<int, float>}
     */
    protected function intentChart(Carbon $start, Carbon $end): array
    {
        $rows = Intent::query()
            ->withCount(['analyses' => fn ($q) => $q->whereBetween('created_at', [$start, $end])])
            ->having('analyses_count', '>', 0)
            ->orderByDesc('analyses_count')
            ->get();

        $total = $rows->sum('analyses_count');

        return [
            'labels' => $rows->pluck('name')->values()->all(),
            'counts' => $rows->pluck('analyses_count')->values()->all(),
            'percentages' => $rows->map(fn ($intent) => $total > 0 ? round($intent->analyses_count / $total * 100, 1) : 0.0)->values()->all(),
        ];
    }

    /**
     * @return array{labels: array<int, string>, counts: array<int, int>}
     */
    protected function aiPerformanceChart(Carbon $start, Carbon $end): array
    {
        $drafts = Draft::query()->whereBetween('created_at', [$start, $end])->get(['status', 'content', 'original_content', 'ai_log_id']);

        return [
            'labels' => ['Draft Generated', 'Draft Approved', 'Draft Regenerated', 'Draft Edited', 'Draft Deleted'],
            'counts' => [
                $drafts->whereNotNull('ai_log_id')->count(),
                $drafts->whereIn('status', [DraftStatus::Approved, DraftStatus::Sent])->count(),
                $drafts->where('status', DraftStatus::Regenerated)->count(),
                $drafts->whereNotNull('ai_log_id')->filter->wasEditedByAgent()->count(),
                $drafts->where('status', DraftStatus::Discarded)->count(),
            ],
        ];
    }

    /**
     * @return array{labels: array<int, string>, counts: array<int, int>}
     */
    protected function aiAccuracyChart(Carbon $start, Carbon $end): array
    {
        $drafts = Draft::query()
            ->whereNotNull('ai_log_id')
            ->whereIn('status', [DraftStatus::Approved, DraftStatus::Sent, DraftStatus::Discarded])
            ->whereBetween('updated_at', [$start, $end])
            ->get(['status', 'content', 'original_content']);

        $sentAsIs = 0;
        $editedLittle = 0;
        $editedALot = 0;
        $rejected = 0;

        foreach ($drafts as $draft) {
            if ($draft->status === DraftStatus::Discarded) {
                $rejected++;

                continue;
            }

            $similarity = $draft->editSimilarityPercent() ?? 100.0;

            match (true) {
                $similarity >= 98.0 => $sentAsIs++,
                $similarity >= 80.0 => $editedLittle++,
                default => $editedALot++,
            };
        }

        return [
            'labels' => ['Dikirim Langsung', 'Diedit Sedikit', 'Diedit Banyak', 'Ditolak'],
            'counts' => [$sentAsIs, $editedLittle, $editedALot, $rejected],
        ];
    }
}

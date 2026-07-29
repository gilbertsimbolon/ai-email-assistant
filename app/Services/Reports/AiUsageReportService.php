<?php

namespace App\Services\Reports;

use App\Models\AiCenter\AiLog;
use App\Models\AiCenter\AiModel;
use Carbon\Carbon;

/**
 * AI Usage + AI Models + Response Time sections: everything here reads from
 * `ai_logs` (tokens/cost/latency per run) except Response Time, which is
 * derived from message timestamps via ResponseTimeCalculator.
 */
class AiUsageReportService
{
    public function __construct(
        protected ResponseTimeCalculator $responseTime,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function compute(Carbon $start, Carbon $end, ?int $aiModelId = null): array
    {
        $logs = AiLog::query()
            ->whereBetween('created_at', [$start, $end])
            ->when($aiModelId, fn ($q) => $q->where('ai_model_id', $aiModelId));

        $requestCount = (clone $logs)->count();

        return [
            'usage' => [
                'prompt_tokens' => (int) (clone $logs)->sum('prompt_tokens'),
                'completion_tokens' => (int) (clone $logs)->sum('completion_tokens'),
                'total_tokens' => (int) (clone $logs)->sum('total_tokens'),
                'average_tokens' => $requestCount > 0 ? round((clone $logs)->sum('total_tokens') / $requestCount, 1) : 0.0,
                'estimated_cost' => (float) (clone $logs)->sum('cost'),
                'request_count' => $requestCount,
            ],
            'models' => $this->modelBreakdown($start, $end),
            'response_time' => $this->responseTime->stats($start, $end),
        ];
    }

    /**
     * @return array<int, array{name: string, provider: ?string, model: ?string, requests: int, tokens: int, avg_response_time_ms: int}>
     */
    protected function modelBreakdown(Carbon $start, Carbon $end): array
    {
        return AiModel::query()
            ->withCount(['aiLogs as requests' => fn ($q) => $q->whereBetween('created_at', [$start, $end])])
            ->withSum(['aiLogs as tokens' => fn ($q) => $q->whereBetween('created_at', [$start, $end])], 'total_tokens')
            ->withAvg(['aiLogs as avg_latency' => fn ($q) => $q->whereBetween('created_at', [$start, $end])], 'latency_ms')
            ->having('requests', '>', 0)
            ->orderByDesc('requests')
            ->get()
            ->map(fn (AiModel $model) => [
                'name' => $model->name,
                'provider' => $model->provider?->label() ?? $model->provider?->value,
                'model' => $model->model,
                'requests' => (int) $model->requests,
                'tokens' => (int) $model->tokens,
                'avg_response_time_ms' => (int) round($model->avg_latency ?? 0),
            ])
            ->values()
            ->all();
    }
}

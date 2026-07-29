<?php

namespace App\Services\Reports;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Turns the "Hari / Minggu / Bulan / Tahun / Custom Range" filter every
 * Reports page shares into a concrete date range + chart bucket size, kept
 * DB-portable by never relying on a DB-specific date-truncation function —
 * callers group rows into buckets() in PHP instead.
 */
class ReportPeriodResolver
{
    /**
     * @return array{start: Carbon, end: Carbon, bucket: string, label: string, period: string}
     */
    public function resolve(Request $request): array
    {
        $period = strtolower((string) $request->query('period', 'month'));
        $now = Carbon::now();

        $range = match ($period) {
            'day' => ['start' => $now->copy()->startOfDay(), 'end' => $now->copy()->endOfDay(), 'bucket' => 'hour', 'label' => 'Hari Ini'],
            'week' => ['start' => $now->copy()->startOfWeek(), 'end' => $now->copy()->endOfWeek(), 'bucket' => 'day', 'label' => 'Minggu Ini'],
            'year' => ['start' => $now->copy()->startOfYear(), 'end' => $now->copy()->endOfYear(), 'bucket' => 'month', 'label' => 'Tahun Ini'],
            'custom' => $this->customRange($request),
            default => ['start' => $now->copy()->startOfMonth(), 'end' => $now->copy()->endOfMonth(), 'bucket' => 'day', 'label' => 'Bulan Ini'],
        };

        return $range + ['period' => $period];
    }

    /**
     * @return array{start: Carbon, end: Carbon, bucket: string, label: string}
     */
    protected function customRange(Request $request): array
    {
        $start = $request->filled('from')
            ? Carbon::parse((string) $request->query('from'))->startOfDay()
            : Carbon::now()->subDays(29)->startOfDay();

        $end = $request->filled('to')
            ? Carbon::parse((string) $request->query('to'))->endOfDay()
            : Carbon::now()->endOfDay();

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        $days = $start->diffInDays($end);
        $bucket = $days > 62 ? 'month' : ($days > 2 ? 'day' : 'hour');

        return ['start' => $start, 'end' => $end, 'bucket' => $bucket, 'label' => 'Custom Range'];
    }

    /**
     * Zero-filled bucket boundaries between $start and $end, so a chart
     * series never has gaps for buckets with no rows.
     *
     * @return array<int, array{key: string, label: string, start: Carbon, end: Carbon}>
     */
    public function buckets(Carbon $start, Carbon $end, string $bucket): array
    {
        $buckets = [];
        $cursor = $start->copy();

        while ($cursor->lessThanOrEqualTo($end)) {
            [$bucketStart, $bucketEnd, $key, $label, $next] = match ($bucket) {
                'hour' => [
                    $cursor->copy()->startOfHour(),
                    $cursor->copy()->endOfHour(),
                    $cursor->format('Y-m-d H:00'),
                    $cursor->format('H:00'),
                    $cursor->copy()->addHour(),
                ],
                'month' => [
                    $cursor->copy()->startOfMonth(),
                    $cursor->copy()->endOfMonth(),
                    $cursor->format('Y-m'),
                    $cursor->translatedFormat('M Y'),
                    $cursor->copy()->addMonthNoOverflow()->startOfMonth(),
                ],
                default => [
                    $cursor->copy()->startOfDay(),
                    $cursor->copy()->endOfDay(),
                    $cursor->format('Y-m-d'),
                    $cursor->format('d M'),
                    $cursor->copy()->addDay(),
                ],
            };

            $buckets[] = ['key' => $key, 'label' => $label, 'start' => $bucketStart, 'end' => $bucketEnd];
            $cursor = $next;
        }

        return $buckets;
    }

    /**
     * The bucket key a given timestamp falls into, matching the format used
     * in buckets() above, for tallying rows fetched with a single query.
     */
    public function keyFor(Carbon $timestamp, string $bucket): string
    {
        return match ($bucket) {
            'hour' => $timestamp->format('Y-m-d H:00'),
            'month' => $timestamp->format('Y-m'),
            default => $timestamp->format('Y-m-d'),
        };
    }
}

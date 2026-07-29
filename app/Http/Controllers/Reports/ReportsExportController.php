<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\ActivityTimelineService;
use App\Services\Reports\AiUsageReportService;
use App\Services\Reports\ContentAnalyticsService;
use App\Services\Reports\CustomerAnalyticsService;
use App\Services\Reports\DashboardOverviewService;
use App\Services\Reports\Export\ExcelReportExporter;
use App\Services\Reports\Export\PdfReportExporter;
use App\Services\Reports\GmailAnalyticsService;
use App\Services\Reports\ReportPeriodResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class ReportsExportController extends Controller
{
    public function __construct(
        protected ReportPeriodResolver $periodResolver,
        protected DashboardOverviewService $overview,
        protected AiUsageReportService $aiUsage,
        protected ContentAnalyticsService $content,
        protected CustomerAnalyticsService $customers,
        protected GmailAnalyticsService $gmail,
        protected ActivityTimelineService $timeline,
        protected PdfReportExporter $pdfExporter,
        protected ExcelReportExporter $excelExporter,
    ) {
    }

    public function export(Request $request, string $report, string $format): Response
    {
        abort_unless(in_array($format, ['pdf', 'excel', 'csv'], true), 404);

        [$title, $headings, $rows] = $this->dataset($request, $report);

        $filename = $report.'-'.now()->format('Ymd_His');

        return match ($format) {
            'pdf' => $this->pdfExporter->download($title, $headings, $rows, $filename.'.pdf'),
            'excel' => $this->excelExporter->download($headings, $rows, $filename.'.xlsx', 'xlsx'),
            'csv' => $this->excelExporter->download($headings, $rows, $filename.'.csv', 'csv'),
        };
    }

    /**
     * @return array{0: string, 1: array<int, string>, 2: Collection}
     */
    protected function dataset(Request $request, string $report): array
    {
        $period = $this->periodResolver->resolve($request);
        $start = $period['start'];
        $end = $period['end'];

        return match ($report) {
            'overview' => $this->overviewDataset($start, $end, $period['bucket']),
            'ai-usage' => $this->aiUsageDataset($start, $end),
            'content' => $this->contentDataset($start, $end, (string) $request->query('tab', 'sops')),
            'customers' => $this->customersDataset((string) $request->query('search', '') ?: null),
            'gmail-accounts' => $this->gmailDataset(),
            'timeline' => $this->timelineDataset(),
            default => abort(404),
        };
    }

    protected function overviewDataset($start, $end, string $bucket): array
    {
        $kpis = $this->overview->compute($start, $end, $bucket)['kpis'];

        $rows = collect($kpis)->map(fn ($value, $key) => [ucwords(str_replace('_', ' ', $key)), $value])->values();

        return ['Reports Overview', ['Metric', 'Value'], $rows];
    }

    protected function aiUsageDataset($start, $end): array
    {
        $models = collect($this->aiUsage->compute($start, $end)['models']);

        $rows = $models->map(fn (array $m) => [
            $m['name'], $m['provider'], $m['model'], $m['requests'], $m['tokens'], $m['avg_response_time_ms'],
        ]);

        return ['AI Models Usage', ['Name', 'Provider', 'Model', 'Requests', 'Tokens', 'Avg Response (ms)'], $rows];
    }

    protected function contentDataset($start, $end, string $tab): array
    {
        $data = $this->content->compute($start, $end);

        return match ($tab) {
            'knowledge' => [
                'Knowledge Analytics',
                ['Title', 'Type', 'Usage Count', 'Last Used'],
                collect($data['knowledge'])->map(fn (array $r) => [$r['title'], $r['type'], $r['usage_count'], optional($r['last_used'])->format('Y-m-d H:i')]),
            ],
            'reply-templates' => [
                'Reply Template Analytics',
                ['Name', 'Usage Count', 'Edited by Agent', 'Success Rate (%)'],
                collect($data['reply_templates'])->map(fn (array $r) => [$r['name'], $r['usage_count'], $r['edited_by_agent'], $r['success_rate']]),
            ],
            'workflows' => [
                'Workflow Analytics',
                ['Name', 'Run Count', 'Success', 'Failed', 'Avg Duration (ms)'],
                collect($data['workflows'])->map(fn (array $r) => [$r['name'], $r['run_count'], $r['success'], $r['failed'], $r['avg_duration_ms']]),
            ],
            default => [
                'SOP Analytics',
                ['Name', 'Usage Count', 'Success Rate (%)', 'Last Used'],
                collect($data['sops'])->map(fn (array $r) => [$r['name'], $r['usage_count'], $r['success_rate'], optional($r['last_used'])->format('Y-m-d H:i')]),
            ],
        };
    }

    protected function customersDataset(?string $search): array
    {
        $rows = $this->customers->forExport($search)->map(fn ($row) => [
            $row->contact_email, $row->contact_name, $row->ticket_count, $row->email_count,
            optional($row->last_contact)->format('Y-m-d H:i'),
        ]);

        return ['Customer Analytics', ['Email', 'Name', 'Tickets', 'Emails', 'Last Contact'], $rows];
    }

    protected function gmailDataset(): array
    {
        $rows = collect($this->gmail->all())->map(fn (array $a) => [
            $a['email'], $a['owner'], $a['conversation_count'],
            optional($a['last_synced_at'])->format('Y-m-d H:i'), $a['status'], $a['history_id'], $a['last_error'],
        ]);

        return ['Gmail Analytics', ['Email', 'Owner', 'Conversations', 'Last Synced', 'Status', 'History ID', 'Last Error'], $rows];
    }

    protected function timelineDataset(): array
    {
        $rows = $this->timeline->forExport()->map(fn (array $e) => [
            $e['time']->format('Y-m-d H:i'), $e['title'], $e['description'], $e['conversation_id'],
        ]);

        return ['Activity Timeline', ['Time', 'Event', 'Description', 'Conversation ID'], $rows];
    }
}

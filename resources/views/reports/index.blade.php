@extends('layouts.app')

@section('title', 'Reports | AI Email Assistant')

@php
    $tiles = [
        ['label' => 'Total Email', 'value' => number_format($kpis['total_email']), 'icon' => 'bx-envelope'],
        ['label' => 'Incoming Today', 'value' => number_format($kpis['incoming_today']), 'icon' => 'bx-download', 'color' => 'success'],
        ['label' => 'Outgoing Today', 'value' => number_format($kpis['outgoing_today']), 'icon' => 'bx-upload', 'color' => 'info'],
        ['label' => 'Pending Reply', 'value' => number_format($kpis['pending_reply']), 'icon' => 'bx-time', 'color' => 'warning'],
        ['label' => 'AI Generated Draft', 'value' => number_format($kpis['ai_generated_draft']), 'icon' => 'bx-bot'],
        ['label' => 'Draft Approved', 'value' => number_format($kpis['draft_approved']), 'icon' => 'bx-check-circle', 'color' => 'success'],
        ['label' => 'Draft Edited', 'value' => number_format($kpis['draft_edited']), 'icon' => 'bx-edit-alt', 'color' => 'warning'],
        ['label' => 'Draft Rejected', 'value' => number_format($kpis['draft_rejected']), 'icon' => 'bx-x-circle', 'color' => 'danger'],
        ['label' => 'Avg Response Time', 'value' => $kpis['average_response_time'].' min', 'icon' => 'bx-time-five', 'color' => 'info'],
        ['label' => 'Estimated AI Cost', 'value' => '$'.number_format($kpis['estimated_ai_cost'], 4), 'icon' => 'bx-dollar-circle'],
        ['label' => 'Total Tokens', 'value' => number_format($kpis['total_tokens']), 'icon' => 'bx-coin-stack'],
        ['label' => 'Connected Gmail Accounts', 'value' => number_format($kpis['connected_gmail_accounts']), 'icon' => 'bx-envelope-open', 'color' => 'success'],
    ];
@endphp

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Reports Overview</h4>
    </div>

    <x-reports.filter-bar :period="$period" export-report="overview">
        <div class="col-auto">
            <label class="form-label small mb-0 d-block">Gmail Account</label>
            <select name="gmail_account_id" class="form-select form-select-sm">
                <option value="">Semua Akun</option>
                @foreach ($gmailAccounts as $account)
                    <option value="{{ $account->id }}" {{ (int) $selectedGmailAccountId === $account->id ? 'selected' : '' }}>{{ $account->email }}</option>
                @endforeach
            </select>
        </div>
    </x-reports.filter-bar>

    <x-reports.kpi-card :tiles="$tiles" />

    <div class="row g-4 mt-1">
        <div class="col-lg-8">
            <x-reports.chart-card title="Email Analytics ({{ $period['label'] }})" id="emailAnalyticsChart" />
        </div>
        <div class="col-lg-4">
            <x-reports.chart-card title="Intent Analytics" id="intentAnalyticsChart" />
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-lg-6">
            <x-reports.chart-card title="AI Performance" id="aiPerformanceChart" />
        </div>
        <div class="col-lg-6">
            <x-reports.chart-card title="AI Accuracy" id="aiAccuracyChart" />
        </div>
    </div>

    <div class="card shadow-sm mt-3">
        <div class="card-header d-flex justify-content-between align-items-center bg-white">
            <h6 class="mb-0"><i class="bx bx-time-five me-1 text-primary"></i> Activity Timeline Terbaru</h6>
            <a href="{{ route('reports.timeline') }}" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
        </div>
        <x-reports.timeline-list :events="$timeline" />
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    <script>
        const emailChartData = @json($email_chart);
        const intentChartData = @json($intent_chart);
        const aiPerformanceChartData = @json($ai_performance_chart);
        const aiAccuracyChartData = @json($ai_accuracy_chart);

        new Chart(document.getElementById('emailAnalyticsChart'), {
            type: 'line',
            data: {
                labels: emailChartData.labels,
                datasets: [
                    { label: 'Incoming', data: emailChartData.incoming, borderColor: '#696cff', tension: 0.35 },
                    { label: 'Outgoing', data: emailChartData.outgoing, borderColor: '#71dd37', tension: 0.35 },
                    { label: 'Pending', data: emailChartData.pending, borderColor: '#ffab00', tension: 0.35 },
                    { label: 'Replied', data: emailChartData.replied, borderColor: '#03c3ec', tension: 0.35 },
                ],
            },
            options: { responsive: true, maintainAspectRatio: false },
        });

        new Chart(document.getElementById('intentAnalyticsChart'), {
            type: 'doughnut',
            data: {
                labels: intentChartData.labels,
                datasets: [{ data: intentChartData.counts, backgroundColor: ['#696cff', '#03c3ec', '#71dd37', '#ffab00', '#ff3e1d', '#8592a3'] }],
            },
            options: { responsive: true, maintainAspectRatio: false },
        });

        new Chart(document.getElementById('aiPerformanceChart'), {
            type: 'bar',
            data: {
                labels: aiPerformanceChartData.labels,
                datasets: [{ label: 'Jumlah', data: aiPerformanceChartData.counts, backgroundColor: '#696cff' }],
            },
            options: { responsive: true, maintainAspectRatio: false },
        });

        new Chart(document.getElementById('aiAccuracyChart'), {
            type: 'pie',
            data: {
                labels: aiAccuracyChartData.labels,
                datasets: [{ data: aiAccuracyChartData.counts, backgroundColor: ['#71dd37', '#ffab00', '#ff9f43', '#ff3e1d'] }],
            },
            options: { responsive: true, maintainAspectRatio: false },
        });
    </script>
@endpush

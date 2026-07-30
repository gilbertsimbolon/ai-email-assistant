@extends('layouts.app')

@section('title', 'AI Usage & Models | Reports')

@php
    $tiles = [
        [
            'label' => 'Prompt Tokens',
            'value' => number_format($usage['prompt_tokens']),
            'icon' => 'bx-message-square-dots',
        ],
        [
            'label' => 'Completion Tokens',
            'value' => number_format($usage['completion_tokens']),
            'icon' => 'bx-message-square-check',
        ],
        ['label' => 'Total Tokens', 'value' => number_format($usage['total_tokens']), 'icon' => 'bx-coin-stack'],
        [
            'label' => 'Average Tokens / Request',
            'value' => number_format($usage['average_tokens'], 1),
            'icon' => 'bx-trending-up',
        ],
        [
            'label' => 'Estimated Cost',
            'value' => '$' . number_format($usage['estimated_cost'], 4),
            'icon' => 'bx-dollar-circle',
            'color' => 'success',
        ],
        ['label' => 'Total Requests', 'value' => number_format($usage['request_count']), 'icon' => 'bx-bolt'],
    ];

    $responseTiles = [
        ['label' => 'Average Response Time', 'value' => $response_time['average'] . ' min', 'icon' => 'bx-time-five'],
        ['label' => 'Median Response Time', 'value' => $response_time['median'] . ' min', 'icon' => 'bx-time'],
        [
            'label' => 'Fastest',
            'value' => $response_time['fastest'] . ' min',
            'icon' => 'bx-rocket',
            'color' => 'success',
        ],
        [
            'label' => 'Slowest',
            'value' => $response_time['slowest'] . ' min',
            'icon' => 'bx-hourglass',
            'color' => 'danger',
        ],
    ];
@endphp

@section('content')
    <h4 class="mb-4">AI Usage &amp; Models</h4>

    <x-reports.filter-bar :period="$period" export-report="ai-usage">
        <div class="col-auto">
            <label class="form-label small mb-0 d-block">AI Model</label>
            <select name="ai_model_id" class="form-select form-select-sm">
                <option value="">Semua Model</option>
                @foreach ($aiModels as $model)
                    <option value="{{ $model->id }}" {{ (int) $selectedAiModelId === $model->id ? 'selected' : '' }}>
                        {{ $model->name }}</option>
                @endforeach
            </select>
        </div>
    </x-reports.filter-bar>

    <h6 class="mb-2">AI Usage</h6>
    <x-reports.kpi-card :tiles="$tiles" />

    <h6 class="mb-2 mt-2">Response Time</h6>
    <x-reports.kpi-card :tiles="$responseTiles" />

    <div class="card shadow-sm mt-3">
        <div class="card-header bg-white">
            <h6 class="mb-0">AI Models</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover" id="aiModelsTable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Provider</th>
                        <th>Model</th>
                        <th>Requests</th>
                        <th>Tokens</th>
                        <th>Avg Response Time</th>
                    </tr>
                </thead>
                @if (count($models))
                    <tbody>
                        @foreach ($models as $model)
                            <tr>
                                <td>{{ $model['name'] }}</td>
                                <td>{{ $model['provider'] }}</td>
                                <td>{{ $model['model'] }}</td>
                                <td>{{ number_format($model['requests']) }}</td>
                                <td>{{ number_format($model['tokens']) }}</td>
                                <td>{{ $model['avg_response_time_ms'] }} ms</td>
                            </tr>
                        @endforeach
                    </tbody>
                @endif
            </table>
        </div>
    </div>
@endsection

@push('scripts')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@2/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.jsdelivr.net/npm/datatables.net@2/js/dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@2/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(function() {
            $('#aiModelsTable').DataTable({
                order: [
                    [3, 'desc']
                ]
            });
        });
    </script>
@endpush

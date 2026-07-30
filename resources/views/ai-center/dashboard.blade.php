@extends('layouts.app')

@section('title', 'Dashboard | AI Center')

@php
    $tiles = [
        ['label' => 'AI Provider', 'value' => $stats['ai_provider'] ?? '-', 'icon' => 'bx-server'],
        ['label' => 'Model Aktif', 'value' => $stats['active_model'] ?? '-', 'icon' => 'bx-chip'],
        ['label' => 'Total SOP', 'value' => $stats['total_sops'], 'icon' => 'bx-book-content'],
        ['label' => 'Total Rule', 'value' => $stats['total_rules'], 'icon' => 'bx-git-branch'],
        ['label' => 'Total Workflow', 'value' => $stats['total_workflows'], 'icon' => 'bx-sitemap'],
        ['label' => 'Total Intent', 'value' => $stats['total_intents'], 'icon' => 'bx-target-lock'],
        ['label' => 'Total Template', 'value' => $stats['total_templates'], 'icon' => 'bx-file'],
        ['label' => 'Knowledge Base', 'value' => $stats['total_knowledge_bases'], 'icon' => 'bx-library'],
        ['label' => 'Request Hari Ini', 'value' => $stats['requests_today'], 'icon' => 'bx-bolt'],
        ['label' => 'Token Hari Ini', 'value' => number_format($stats['tokens_today']), 'icon' => 'bx-coin-stack'],
        ['label' => 'Avg Response Time', 'value' => $stats['avg_response_time_ms'].' ms', 'icon' => 'bx-time-five'],
        ['label' => 'Estimated Cost', 'value' => '$'.number_format($stats['estimated_cost_today'], 4), 'icon' => 'bx-dollar-circle'],
        ['label' => 'Draft Generated', 'value' => $stats['drafts_generated'], 'icon' => 'bx-edit-alt'],
        ['label' => 'Human Approval Rate', 'value' => $stats['human_approval_rate'].'%', 'icon' => 'bx-check-shield'],
    ];
@endphp

@section('content')
    <div class="row g-4">
        @foreach ($tiles as $tile)
            <div class="col-md-3 col-sm-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="avatar avatar-lg flex-shrink-0 me-3">
                            <span class="avatar-initial rounded bg-label-primary">
                                <i class="icon-base bx {{ $tile['icon'] }} icon-lg"></i>
                            </span>
                        </div>
                        <div>
                            <span class="text-body small d-block">{{ $tile['label'] }}</span>
                            <h5 class="mb-0">{{ $tile['value'] ?: '-' }}</h5>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection

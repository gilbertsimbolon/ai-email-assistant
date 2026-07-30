@extends('layouts.app')

@section('title', 'AI Log #'.$aiLog->id.' | AI Center')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0">AI Log #{{ $aiLog->id }}</h5>
        <a href="{{ route('ai-center.ai-logs.index') }}" class="btn btn-outline-secondary btn-sm">Kembali</a>
    </div>

    <div class="row">
        <div class="col-md-5">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white"><h6 class="mb-0">Ringkasan</h6></div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr><th>Tanggal</th><td>{{ $aiLog->created_at->format('d M Y H:i:s') }}</td></tr>
                            <tr><th>Source</th><td>{{ ucfirst($aiLog->source->value) }}</td></tr>
                            <tr><th>Conversation</th><td>{{ $aiLog->conversation?->subject ?? ($aiLog->conversation_id ? "#{$aiLog->conversation_id}" : '-') }}</td></tr>
                            <tr><th>Intent</th><td>{{ $aiLog->intent?->name ?? '-' }}</td></tr>
                            <tr><th>Matched SOP</th><td>{{ $aiLog->sop?->name ?? '-' }}</td></tr>
                            <tr><th>Matched Workflow</th><td>{{ $aiLog->workflow?->name ?? '-' }}</td></tr>
                            <tr><th>Matched Rules</th><td>{{ implode(', ', $aiLog->matched_rule_ids ?? []) ?: '-' }}</td></tr>
                            <tr><th>Matched Actions</th><td>{{ implode(', ', $aiLog->matched_action_types ?? []) ?: '-' }}</td></tr>
                            <tr><th>Matched Knowledge Base</th><td>{{ implode(', ', $aiLog->matched_knowledge_base_ids ?? []) ?: '-' }}</td></tr>
                            <tr><th>Template</th><td>{{ $aiLog->replyTemplate?->name ?? '-' }}</td></tr>
                            <tr><th>AI Model</th><td>{{ $aiLog->aiModel?->name ?? '-' }}</td></tr>
                            <tr><th>Triggered By</th><td>{{ $aiLog->triggeredByUser?->name ?? '-' }}</td></tr>
                            <tr><th>Prompt Tokens</th><td>{{ $aiLog->prompt_tokens ?? '-' }}</td></tr>
                            <tr><th>Completion Tokens</th><td>{{ $aiLog->completion_tokens ?? '-' }}</td></tr>
                            <tr><th>Total Tokens</th><td>{{ $aiLog->total_tokens ?? '-' }}</td></tr>
                            <tr><th>Latency</th><td>{{ $aiLog->latency_ms }} ms</td></tr>
                            <tr><th>Cost</th><td>{{ $aiLog->cost !== null ? '$'.number_format($aiLog->cost, 4) : '-' }}</td></tr>
                            <tr><th>Confidence</th><td>{{ $aiLog->confidence_score ?? '-' }}</td></tr>
                            <tr>
                                <th>Status</th>
                                <td>
                                    <span class="badge {{ $aiLog->status->value === 'success' ? 'bg-label-success' : 'bg-label-danger' }}">
                                        {{ ucfirst($aiLog->status->value) }}
                                    </span>
                                </td>
                            </tr>
                            @if ($aiLog->error)
                                <tr><th>Error</th><td class="text-danger">{{ $aiLog->error }}</td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white"><h6 class="mb-0">Prompt</h6></div>
                <div class="card-body">
                    <pre class="p-4 rounded bg-body-secondary" style="white-space: pre-wrap;">{{ $aiLog->prompt }}</pre>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0">Response</h6></div>
                <div class="card-body">
                    <pre class="p-4 rounded bg-body-secondary" style="white-space: pre-wrap;">{{ $aiLog->response }}</pre>
                </div>
            </div>
        </div>
    </div>
@endsection

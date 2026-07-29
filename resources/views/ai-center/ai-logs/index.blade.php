@extends('layouts.app')

@section('title', 'AI Logs | AI Center')

@section('content')
    <div class="card">
        <div class="card-header"><h5 class="mb-0">AI Logs</h5></div>
        <div class="card-body">
            <form method="GET" class="row g-2 mb-4">
                <div class="col-md-3">
                    <select name="source" class="form-select" onchange="this.form.submit()">
                        <option value="">Semua Source</option>
                        @foreach ($sources as $source)
                            <option value="{{ $source->value }}" {{ request('source') === $source->value ? 'selected' : '' }}>
                                {{ ucfirst($source->value) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">Semua Status</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" {{ request('status') === $status->value ? 'selected' : '' }}>
                                {{ ucfirst($status->value) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="date" name="date" class="form-control" value="{{ request('date') }}" onchange="this.form.submit()">
                </div>
                <div class="col-md-3">
                    <a href="{{ route('ai-center.ai-logs.index') }}" class="btn btn-outline-secondary">Reset Filter</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Source</th>
                            <th>Conversation</th>
                            <th>Intent</th>
                            <th>Matched SOP</th>
                            <th>Matched Workflow</th>
                            <th>Token</th>
                            <th>Latency</th>
                            <th>Cost</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td>{{ $log->created_at->format('d M Y H:i') }}</td>
                                <td><span class="badge bg-label-secondary">{{ ucfirst($log->source->value) }}</span></td>
                                <td>{{ $log->conversation?->subject ?? ($log->conversation_id ? "#{$log->conversation_id}" : '-') }}</td>
                                <td>{{ $log->intent?->name ?? '-' }}</td>
                                <td>{{ $log->sop?->name ?? '-' }}</td>
                                <td>{{ $log->workflow?->name ?? '-' }}</td>
                                <td>{{ $log->total_tokens ?? '-' }}</td>
                                <td>{{ $log->latency_ms }} ms</td>
                                <td>{{ $log->cost !== null ? '$'.number_format($log->cost, 4) : '-' }}</td>
                                <td>
                                    <span class="badge {{ $log->status->value === 'success' ? 'bg-label-success' : 'bg-label-danger' }}">
                                        {{ ucfirst($log->status->value) }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('ai-center.ai-logs.show', $log) }}" class="btn btn-sm btn-outline-primary">Detail</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="text-center text-body">Belum ada log AI.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $logs->links() }}
        </div>
    </div>
@endsection

@extends('layouts.app')

@section('content')
    <div class="container-fluid py-4">
        <h2 class="mb-4">Dashboard</h2>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <a href="{{ route('inbox.index', ['status' => 'pending_review']) }}" class="text-decoration-none">
                    <div class="card shadow-sm border-start border-primary border-4">
                        <div class="card-body">
                            <div class="text-muted">Pending Review</div>
                            <div class="fs-3 fw-bold">{{ $counts['pending_review'] }}</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="{{ route('inbox.index', ['status' => 'replied']) }}" class="text-decoration-none">
                    <div class="card shadow-sm border-start border-success border-4">
                        <div class="card-body">
                            <div class="text-muted">Replied</div>
                            <div class="fs-3 fw-bold">{{ $counts['replied'] }}</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="{{ route('inbox.index', ['status' => 'closed']) }}" class="text-decoration-none">
                    <div class="card shadow-sm border-start border-secondary border-4">
                        <div class="card-body">
                            <div class="text-muted">Closed</div>
                            <div class="fs-3 fw-bold">{{ $counts['closed'] }}</div>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header">Percakapan Terbaru</div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    @forelse($recentConversations as $item)
                        <a href="{{ route('inbox.show', $item->id) }}" class="list-group-item list-group-item-action p-3">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <h6 class="mb-1 fw-bold">{{ $item->contact_name ?? ($item->contact_email ?? 'Pelanggan') }}</h6>
                                <small class="text-muted">{{ $item->last_message_at?->diffForHumans() }}</small>
                            </div>
                            @if ($item->analysis)
                                <small class="text-muted">{{ Str::limit($item->analysis->summary, 120) }}</small>
                            @endif
                        </a>
                    @empty
                        <div class="p-4 text-center text-muted">Belum ada percakapan.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection

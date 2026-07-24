@extends('layouts.app') {{-- Sesuaikan dengan nama layout utama Anda --}}

@section('content')
<div class="container-fluid py-4">
    <h2 class="mb-4">Inbox Percakapan</h2>

    {{-- Filter Status --}}
    <div class="mb-3 btn-group">
        <a href="{{ route('inbox.index', ['status' => 'pending_review']) }}" 
           class="btn {{ $status === 'pending_review' ? 'btn-primary' : 'btn-outline-primary' }}">
           Pending Review
        </a>
        <a href="{{ route('inbox.index', ['status' => 'replied']) }}" 
           class="btn {{ $status === 'replied' ? 'btn-primary' : 'btn-outline-primary' }}">
           Replied
        </a>
        <a href="{{ route('inbox.index', ['status' => 'closed']) }}" 
           class="btn {{ $status === 'closed' ? 'btn-primary' : 'btn-outline-primary' }}">
           Closed
        </a>
    </div>

    {{-- Daftar Percakapan --}}
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="list-group list-group-flush">
                @forelse($conversations as $item)
                    <a href="{{ route('inbox.show', $item->id) }}" class="list-group-item list-group-item-action p-3">
                        <div class="d-flex w-100 justify-content-between align-items-center">
                            <h5 class="mb-1 fw-bold">{{ $item->contact_name ?? $item->contact_email ?? 'Pelanggan' }}</h5>
                            <small class="text-muted">{{ $item->last_message_at ? $item->last_message_at->diffForHumans() : '' }}</small>
                        </div>
                        
                        <p class="mb-1 text-secondary">
                            <strong>Channel:</strong> <span class="badge bg-secondary">{{ strtoupper($item->channel) }}</span>
                            @if($item->analysis)
                                | <strong>Intent:</strong> {{ $item->analysis->customer_intent }}
                                | <strong>Sentimen:</strong> 
                                <span class="badge bg-{{ $item->analysis->sentiment === 'positive' ? 'success' : ($item->analysis->sentiment === 'negative' ? 'danger' : 'warning') }}">
                                    {{ ucfirst($item->analysis->sentiment) }}
                                </span>
                            @endif
                        </p>

                        @if($item->analysis)
                            <small class="text-muted"><strong>Ringkasan AI:</strong> {{ Str::limit($item->analysis->summary, 120) }}</small>
                        @endif
                    </a>
                @empty
                    <div class="p-4 text-center text-muted">
                        Tidak ada percakapan dengan status ini.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Pagination --}}
    <div class="mt-3">
        {{ $conversations->links() }}
    </div>
</div>
@endsection

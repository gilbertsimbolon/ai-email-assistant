@extends('layouts.app')

@section('title', 'Dashboard | AI Email Assistant')

@php
    $tiles = [
        ['label' => 'Pending Review', 'value' => $counts['pending_review'], 'icon' => 'bx-time-five', 'color' => 'warning', 'status' => 'pending_review'],
        ['label' => 'Replied', 'value' => $counts['replied'], 'icon' => 'bx-check-double', 'color' => 'success', 'status' => 'replied'],
        ['label' => 'Closed', 'value' => $counts['closed'], 'icon' => 'bx-archive', 'color' => 'secondary', 'status' => 'closed'],
    ];
@endphp

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-0 fw-bold">Dashboard</h4>
            <small class="text-muted">Ringkasan aktivitas inbox Anda</small>
        </div>
    </div>

    @unless ($hasGmailAccount)
        <div class="alert alert-warning d-flex justify-content-between align-items-center">
            <span>Belum ada akun Gmail yang terhubung. Inbox tidak akan menampilkan data apa pun.</span>
            <a href="{{ route('settings.index') }}" class="btn btn-sm btn-warning">Hubungkan Gmail</a>
        </div>
    @endunless

    <div class="row g-4 mb-4">
        @foreach ($tiles as $tile)
            <div class="col-md-4">
                <a href="{{ route('inbox.index', ['status' => $tile['status']]) }}" class="text-decoration-none">
                    <div class="card shadow-sm h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="avatar avatar-lg flex-shrink-0 me-3">
                                <span class="avatar-initial rounded bg-label-{{ $tile['color'] }}">
                                    <i class="bx {{ $tile['icon'] }} icon-lg"></i>
                                </span>
                            </div>
                            <div>
                                <span class="text-body small d-block">{{ $tile['label'] }}</span>
                                <h4 class="mb-0 fw-bold">{{ $tile['value'] }}</h4>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="card shadow-sm">
        <div class="card-header d-flex align-items-center justify-content-between bg-white">
            <h6 class="mb-0 fw-bold">Percakapan Terbaru</h6>
            <a href="{{ route('inbox.index') }}" class="btn btn-sm btn-outline-primary">Lihat Semua Inbox</a>
        </div>
        <div class="card-body p-0">
            <div class="list-group list-group-flush">
                @forelse($recentConversations as $item)
                    <a href="{{ route('inbox.show', $item->id) }}" class="list-group-item list-group-item-action d-flex align-items-center px-4 py-3">
                        <div class="avatar avatar-sm me-3 flex-shrink-0">
                            <span class="avatar-initial rounded-circle bg-label-primary">
                                {{ strtoupper(substr($item->contact_name ?? $item->contact_email ?? 'P', 0, 1)) }}
                            </span>
                        </div>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold text-truncate">{{ $item->contact_name ?? ($item->contact_email ?? 'Pelanggan') }}</h6>
                                <small class="text-muted flex-shrink-0 ms-2">{{ $item->last_message_at?->diffForHumans() }}</small>
                            </div>
                            @if ($item->analysis)
                                <small class="text-muted text-truncate d-block">{{ Str::limit($item->analysis->summary, 120) }}</small>
                            @endif
                        </div>
                    </a>
                @empty
                    <div class="p-5 text-center text-muted">
                        <i class="bx bx-envelope-open display-4 mb-2"></i>
                        <p class="mb-0">Belum ada percakapan.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
@endsection

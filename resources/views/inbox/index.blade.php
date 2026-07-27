@extends('layouts.app')

@section('title', 'Inbox Percakapan | AI Email Assistant')

@section('content')
<div class="app-email card overflow-hidden">
    <div class="row g-0">

        {{-- Area Konten Utama Email (Tanpa Sidebar Kiri yang Panjang) --}}
        <div class="col app-emails-list w-100">

            {{-- Toolbar / Search & Filter Status Berbentuk Icon Button Group di Kanan --}}
            <div class="card-header border-bottom py-3 px-4 d-flex align-items-center justify-content-between bg-white flex-wrap gap-3">

                {{-- Search Bar --}}
                <div class="d-flex align-items-center flex-grow-1" style="min-width: 250px; max-width: 400px;">
                    <div class="input-group input-group-merge">
                        <span class="input-group-text bg-transparent border-end-0"><i class="bx bx-search"></i></span>
                        <input type="text" class="form-control border-start-0 ps-0" placeholder="Search mail..." />
                    </div>
                </div>

                {{-- Button Group Icon Status di Pojok Kanan --}}
                <div class="d-flex align-items-center gap-2 ms-auto">

                    {{-- Filter: Pending Review --}}
                    <div class="btn-group" role="group" aria-label="Status Filters">
                        <a href="{{ route('inbox.index', ['status' => 'pending_review']) }}"
                           class="btn {{ ($status ?? 'pending_review') === 'pending_review' ? 'btn-primary' : 'btn-outline-secondary' }}"
                           title="Pending Review">
                            <i class="bx bx-envelope"></i>
                        </a>

                        {{-- Filter: Replied --}}
                        <a href="{{ route('inbox.index', ['status' => 'replied']) }}"
                           class="btn {{ ($status ?? '') === 'replied' ? 'btn-primary' : 'btn-outline-secondary' }}"
                           title="Replied">
                            <i class="bx bx-check-double"></i>
                        </a>

                        {{-- Filter: Closed --}}
                        <a href="{{ route('inbox.index', ['status' => 'closed']) }}"
                           class="btn {{ ($status ?? '') === 'closed' ? 'btn-primary' : 'btn-outline-secondary' }}"
                           title="Closed">
                            <i class="bx bx-archive"></i>
                        </a>
                    </div>

                    <button class="btn btn-icon btn-outline-secondary" type="button" title="Refresh"><i class="bx bx-refresh"></i></button>
                    <button class="btn btn-icon btn-outline-secondary" type="button" title="Options"><i class="bx bx-dots-vertical-rounded"></i></button>
                </div>
            </div>

            {{-- List Email / Percakapan --}}
            <div class="email-list pt-0">
                <ul class="list-unstyled m-0">
                    @forelse($conversations as $item)
                        <li class="email-item border-bottom d-flex align-items-center px-4 py-3 bg-white position-relative hover-shadow">

                            {{-- Checkbox & Star --}}
                            <div class="d-flex align-items-center me-3">
                                <div class="form-check mb-0 me-2">
                                    <input class="form-check-input" type="checkbox" id="email-check-{{ $item->id }}" />
                                </div>
                                <a href="javascript:void(0);" class="text-muted"><i class="bx bx-star"></i></a>
                            </div>

                            {{-- Informasi Pengirim & Isi Pesan --}}
                            <div class="email-list-item-content d-flex align-items-center flex-grow-1 cursor-pointer overflow-hidden"
                                 onclick="window.location='{{ route('inbox.show', $item->id) }}'">

                                {{-- Avatar / Inisial Nama --}}
                                <div class="avatar avatar-sm me-3 flex-shrink-0">
                                    <span class="avatar-initial rounded-circle bg-label-primary text-primary fw-bold">
                                        {{ strtoupper(substr($item->contact_name ?? $item->contact_email ?? 'P', 0, 2)) }}
                                    </span>
                                </div>

                                {{-- Nama & Preview Ringkas --}}
                                <div class="email-details flex-grow-1 overflow-hidden me-3">
                                    <div class="d-flex align-items-center mb-0">
                                        <h6 class="email-list-item-username mb-0 me-2 fw-bold text-dark text-truncate" style="max-width: 180px;">
                                            {{ $item->contact_name ?? ($item->contact_email ?? 'Pelanggan') }}
                                        </h6>
                                        <span class="badge bg-label-secondary me-2" style="font-size: 10px;">
                                            {{ strtoupper($item->channel->value ?? $item->channel) }}
                                        </span>
                                        @if ($item->analysis && isset($item->analysis->customer_intent))
                                            <span class="text-muted small text-truncate">[{{ $item->analysis->customer_intent }}]</span>
                                        @endif
                                    </div>
                                    <p class="email-list-item-subject mb-0 text-truncate text-secondary small">
                                        @if ($item->analysis && isset($item->analysis->summary))
                                            {{ $item->analysis->summary }}
                                        @else
                                            Tidak ada ringkasan AI.
                                        @endif
                                    </p>
                                </div>
                            </div>

                            {{-- Indikator Sentimen & Waktu di Sebelah Kanan --}}
                            <div class="email-list-item-meta d-flex align-items-center flex-shrink-0">
                                @if ($item->analysis)
                                    @php
                                        $sentiment = $item->analysis->sentiment->value ?? $item->analysis->sentiment;
                                        $badgeColor = $sentiment === 'positive' ? 'success' : ($sentiment === 'negative' ? 'danger' : 'warning');
                                    @endphp
                                    <span class="badge bg-{{ $badgeColor }} me-3 rounded-pill" style="width: 8px; height: 8px; padding: 0;" title="Sentimen: {{ ucfirst($sentiment) }}"></span>
                                @endif
                                <small class="text-muted">{{ $item->last_message_at ? $item->last_message_at->diffForHumans(null, true) : '' }}</small>
                            </div>

                        </li>
                    @empty
                        <li class="text-center p-5 text-muted bg-white">
                            <i class="bx bx-folder-open display-4 mb-2"></i>
                            <p class="mb-0">Tidak ada percakapan dengan status ini.</p>
                        </li>
                    @endforelse
                </ul>
            </div>

            {{-- Pagination Footer --}}
            <div class="card-footer bg-white py-3 px-4 d-flex justify-content-end border-top">
                {{ $conversations->links() }}
            </div>

        </div>
    </div>
</div>
@endsection

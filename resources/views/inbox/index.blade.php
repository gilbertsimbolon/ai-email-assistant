@extends('layouts.app')

@section('title', 'Inbox Email | AI Email Assistant')

@section('content')

@unless ($hasGmailAccount)
    <div class="alert alert-warning d-flex justify-content-between align-items-center">
        <span>Belum ada akun Gmail yang terhubung, jadi belum ada data untuk ditampilkan di sini.</span>
        <a href="{{ route('settings.index') }}" class="btn btn-sm btn-warning">Hubungkan Gmail</a>
    </div>
@endunless

<div class="app-email card overflow-hidden" style="height: calc(100vh - 12rem); min-height: 500px;">
    <div class="row g-0 h-100">

        {{-- Panel Kiri: Daftar Percakapan --}}
        <div class="col-12 col-lg-5 col-xl-4 border-end app-emails-list h-100 flex-column {{ $activeConversation ? 'd-none d-lg-flex' : 'd-flex' }}">

            {{-- Toolbar / Search & Filter --}}
            <div class="card-header border-bottom py-3 px-4 bg-white flex-shrink-0">
                <div class="input-group input-group-merge mb-3">
                    <span class="input-group-text bg-transparent border-end-0"><i class="bx bx-search"></i></span>
                    <input type="text" class="form-control border-start-0 ps-0" placeholder="Search mail..." />
                </div>

                <div class="d-flex align-items-center justify-content-between gap-2">
                    {{-- Filter: All / Unread / Starred --}}
                    <div class="btn-group" role="group" aria-label="Inbox Filters">
                        <a href="{{ route('inbox.index', ['filter' => 'all']) }}"
                           class="btn btn-sm {{ $filter === 'all' ? 'btn-primary' : 'btn-outline-secondary' }}">
                            All
                        </a>
                        <a href="{{ route('inbox.index', ['filter' => 'unread']) }}"
                           class="btn btn-sm {{ $filter === 'unread' ? 'btn-primary' : 'btn-outline-secondary' }}">
                            Unread
                        </a>
                        <a href="{{ route('inbox.index', ['filter' => 'starred']) }}"
                           class="btn btn-sm {{ $filter === 'starred' ? 'btn-primary' : 'btn-outline-secondary' }}">
                            Starred
                        </a>
                    </div>

                    <a href="{{ route('inbox.index', ['filter' => $filter]) }}" class="btn btn-icon btn-sm btn-outline-secondary" title="Refresh">
                        <i class="bx bx-refresh"></i>
                    </a>
                </div>
            </div>

            {{-- List Email / Percakapan --}}
            <div class="email-list flex-grow-1 overflow-auto">
                <ul class="list-unstyled m-0">
                    @forelse($conversations as $item)
                        @php $isActive = $activeConversation && $activeConversation->id === $item->id; @endphp
                        <li class="email-item border-bottom d-flex align-items-center px-4 py-3 position-relative hover-shadow {{ $isActive ? 'bg-label-primary' : 'bg-white' }}">

                            {{-- Checkbox & Star --}}
                            <div class="d-flex align-items-center me-3">
                                <div class="form-check mb-0 me-2">
                                    <input class="form-check-input" type="checkbox" id="email-check-{{ $item->id }}" />
                                </div>
                                <a href="javascript:void(0);" class="text-muted" onclick="toggleStar(event, this, {{ $item->id }})">
                                    <i class="bx {{ $item->is_starred ? 'bxs-star text-warning' : 'bx-star' }}"></i>
                                </a>
                            </div>

                            {{-- Informasi Pengirim & Isi Pesan --}}
                            <a href="{{ route('inbox.index', ['conversation' => $item->id, 'filter' => $filter]) }}"
                               class="email-list-item-content d-flex align-items-center flex-grow-1 overflow-hidden text-decoration-none text-reset">

                                {{-- Avatar / Inisial Nama --}}
                                <div class="avatar avatar-sm me-3 flex-shrink-0">
                                    <span class="avatar-initial rounded-circle bg-label-primary text-primary fw-bold">
                                        {{ strtoupper(substr($item->contact_name ?? $item->contact_email ?? 'P', 0, 2)) }}
                                    </span>
                                </div>

                                {{-- Nama & Preview Ringkas --}}
                                <div class="email-details flex-grow-1 overflow-hidden me-3">
                                    <div class="d-flex align-items-center mb-0">
                                        @unless ($item->is_read)
                                            <span class="bg-primary rounded-circle me-2 flex-shrink-0" style="width: 8px; height: 8px;" title="Belum dibaca"></span>
                                        @endunless
                                        <h6 class="email-list-item-username mb-0 me-2 {{ $item->is_read ? 'fw-normal' : 'fw-bold' }} text-dark text-truncate" style="max-width: 160px;">
                                            {{ $item->contact_name ?? ($item->contact_email ?? 'Pelanggan') }}
                                        </h6>
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
                            </a>

                            {{-- Indikator Sentimen & Waktu di Sebelah Kanan --}}
                            <div class="email-list-item-meta d-flex flex-column align-items-end flex-shrink-0">
                                <small class="text-muted">{{ $item->last_message_at ? $item->last_message_at->diffForHumans(null, true) : '' }}</small>
                                @if ($item->analysis)
                                    @php
                                        $sentiment = $item->analysis->sentiment->value ?? $item->analysis->sentiment;
                                        $badgeColor = $sentiment === 'positive' ? 'success' : ($sentiment === 'negative' ? 'danger' : 'warning');
                                    @endphp
                                    <span class="badge bg-{{ $badgeColor }} mt-1 rounded-pill" style="width: 8px; height: 8px; padding: 0;" title="Sentimen: {{ ucfirst($sentiment) }}"></span>
                                @endif
                            </div>

                        </li>
                    @empty
                        <li class="text-center p-5 text-muted bg-white">
                            <i class="bx bx-folder-open display-4 mb-2"></i>
                            <p class="mb-0">
                                @if ($filter === 'unread')
                                    Tidak ada pesan yang belum dibaca.
                                @elseif ($filter === 'starred')
                                    Belum ada percakapan yang dibintangi.
                                @else
                                    Tidak ada percakapan email.
                                @endif
                            </p>
                        </li>
                    @endforelse
                </ul>
            </div>

            {{-- Pagination Footer --}}
            @if ($conversations->hasPages())
                <div class="card-footer bg-white py-2 px-4 d-flex justify-content-center border-top flex-shrink-0">
                    {{ $conversations->links() }}
                </div>
            @endif
        </div>

        {{-- Panel Kanan: Pratinjau Percakapan --}}
        <div class="col-12 col-lg-7 col-xl-8 app-email-view h-100 overflow-hidden {{ $activeConversation ? 'd-flex' : 'd-none d-lg-flex' }} flex-column">
            @include('inbox.partials.preview')
        </div>

    </div>
</div>

<script>
    const starRouteTemplate = "{{ route('inbox.star', ['conversation' => '__ID__']) }}";

    function toggleStar(event, el, id) {
        event.preventDefault();
        event.stopPropagation();

        fetch(starRouteTemplate.replace('__ID__', id), {
            method: 'PUT',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
        })
            .then((response) => response.json())
            .then((data) => {
                const icon = el.querySelector('i');
                icon.classList.toggle('bxs-star', data.is_starred);
                icon.classList.toggle('text-warning', data.is_starred);
                icon.classList.toggle('bx-star', !data.is_starred);
            });
    }
</script>
@endsection

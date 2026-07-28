<li class="email-item border-bottom d-flex align-items-center px-4 py-3 position-relative hover-shadow {{ $isActive ? 'bg-label-primary' : 'bg-white' }}">

    {{-- Star --}}
    <div class="d-flex align-items-center me-3">
        <a href="javascript:void(0);" class="text-muted" onclick="toggleStar(event, this, {{ $item->id }})">
            <i class="bx {{ $item->is_starred ? 'bxs-star text-warning' : 'bx-star' }}"></i>
        </a>
    </div>

    {{-- Informasi Pengirim & Isi Pesan --}}
    <a href="{{ route('inbox.index', ['conversation' => $item->id, 'filter' => $filter, 'q' => $search ?? null]) }}"
       class="email-list-item-content d-flex align-items-center flex-grow-1 overflow-hidden text-decoration-none text-reset">

        {{-- Avatar / Inisial Nama --}}
        <div class="avatar avatar-sm me-3 flex-shrink-0">
            <span class="avatar-initial rounded-circle bg-label-primary text-primary fw-bold">
                {{ strtoupper(substr($item->contact_name ?? $item->contact_email ?? 'P', 0, 2)) }}
            </span>
        </div>

        {{-- Nama, Subjek & Preview Ringkas --}}
        <div class="email-details flex-grow-1 overflow-hidden me-3">
            <div class="d-flex align-items-center mb-0">
                @unless ($item->is_read)
                    <span class="bg-primary rounded-circle me-2 flex-shrink-0" style="width: 8px; height: 8px;" title="Belum dibaca"></span>
                @endunless
                <h6 class="email-list-item-username mb-0 me-2 {{ $item->is_read ? 'fw-normal' : 'fw-bold' }} text-dark text-truncate" style="max-width: 140px;">
                    {{ $item->contact_name ?? ($item->contact_email ?? 'Pelanggan') }}
                </h6>
                @if ($item->status === \App\Enums\ConversationStatus::Replied)
                    <span class="badge bg-label-success rounded-pill" title="Sudah dibalas"><i class="bx bx-check"></i></span>
                @endif
                @if ($item->has_draft)
                    <span class="badge bg-label-primary rounded-pill ms-1" title="Draft AI tersedia">✨</span>
                @endif
            </div>
            <p class="email-list-item-subject mb-0 text-truncate text-secondary small fw-semibold">
                {{ $item->subject ?: '(Tanpa subjek)' }}
            </p>
            <p class="mb-0 text-truncate text-muted small">
                {{ Str::limit($item->latestMessage?->body ?? 'Tidak ada pesan.', 60) }}
            </p>
        </div>
    </a>

    {{-- Waktu --}}
    <div class="email-list-item-meta d-flex flex-column align-items-end flex-shrink-0">
        <small class="text-muted">{{ $item->last_message_at ? $item->last_message_at->diffForHumans(null, true) : '' }}</small>
    </div>

</li>

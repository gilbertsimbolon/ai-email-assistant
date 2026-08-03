<li class="email-item conversation-item border-bottom px-3 py-3 position-relative {{ $isActive ? 'bg-label-primary' : 'bg-white' }}"
    data-conversation-id="{{ $item->ghlConversationId }}">

    <div class="d-flex align-items-start">
        <div class="form-check mt-1 me-2 flex-shrink-0">
            <input class="form-check-input conversation-select-checkbox" type="checkbox" aria-label="Pilih percakapan" onclick="event.stopPropagation()">
        </div>

        {{-- Informasi Pengirim & Isi Pesan --}}
        <a href="{{ route('inbox.index', ['conversation' => $item->ghlConversationId, 'filter' => $filter, 'q' => $search ?? null]) }}"
           class="email-list-item-content js-conversation-link d-flex align-items-start flex-grow-1 overflow-hidden text-decoration-none text-reset">

            {{-- Avatar / Inisial Nama --}}
            <div class="avatar avatar-sm me-3 flex-shrink-0 position-relative">
                <span class="avatar-initial rounded-circle bg-label-primary text-primary fw-bold">
                    {{ strtoupper(substr($item->contactName ?? $item->contactEmail ?? 'P', 0, 2)) }}
                </span>
                @if ($item->isStarred)
                    <i class="bx bxs-star text-warning conversation-star-badge" title="Dibintangi"></i>
                @endif
            </div>

            {{-- Nama, Channel, Status & Preview Ringkas --}}
            <div class="email-details flex-grow-1 overflow-hidden">
                <div class="d-flex align-items-center justify-content-between mb-0">
                    <h6 class="email-list-item-username mb-0 me-2 {{ $item->isRead ? 'fw-normal' : 'fw-bold' }} text-dark text-truncate">
                        {{ $item->contactName ?? ($item->contactEmail ?? 'Pelanggan') }}
                    </h6>
                    <small class="text-muted flex-shrink-0 conversation-item-timestamp">{{ $item->lastActivityAt?->format('M j') }}</small>
                </div>

                <p class="mb-1 text-truncate text-muted small conversation-item-preview">
                    {{ Str::limit($item->preview ?? 'Tidak ada pesan.', 60) }}
                </p>

                <div class="d-flex align-items-center gap-1">
                    <span class="badge bg-label-secondary rounded-pill" title="Channel">{{ $item->channelLabel }}</span>
                    @if ($item->status === \App\Enums\ConversationStatus::Replied)
                        <span class="badge bg-label-success rounded-pill" title="Sudah dibalas"><i class="bx bx-check"></i></span>
                    @elseif ($item->status === \App\Enums\ConversationStatus::PendingReview && ! $item->hasDraft)
                        <span class="badge bg-label-warning rounded-pill" title="Menunggu balasan agent">Waiting</span>
                    @endif
                    @if ($item->hasDraft)
                        <span class="badge bg-label-primary rounded-pill" title="Draft AI tersedia">✨</span>
                    @endif
                </div>
            </div>
        </a>
    </div>

    @if (! $item->isRead && $item->unreadCount > 0)
        <span class="badge bg-primary rounded-pill conversation-unread-badge" title="Belum dibaca">{{ $item->unreadCount > 99 ? '99+' : $item->unreadCount }}</span>
    @endif

</li>

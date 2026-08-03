@php
    // 4 filter utama tampil sebagai icon group compact
    $primaryFilters = [
        'unread' => ['label' => 'Unread', 'icon' => 'bx-envelope'],
        'all' => ['label' => 'All', 'icon' => 'bx-grid-alt'],
        'recent' => ['label' => 'Recent', 'icon' => 'bx-time-five'],
        'starred' => ['label' => 'Starred', 'icon' => 'bx-star'],
    ];

    $moreFilters = [
        'waiting_agent' => ['label' => 'Waiting Agent', 'icon' => 'bx-user-voice'],
        'waiting_customer' => ['label' => 'Waiting Customer', 'icon' => 'bx-user-check'],
        'ai_draft' => ['label' => 'AI Draft', 'icon' => 'bx-bot'],
        'closed' => ['label' => 'Closed', 'icon' => 'bx-check-circle'],
    ];

    $localOnlyFilters = ['recent', 'starred', 'waiting_agent', 'waiting_customer', 'ai_draft', 'closed'];
@endphp

{{-- Main Container Panel Kiri: Mengisi tinggi 100% dari parent tanpa scroll halaman --}}
<div class="d-flex flex-column min-vh-100 overflow-hidden bg-white border-end ms-2">

    {{-- Header & Search Section (Tetap / Fixed Top) --}}
    <div class="border-bottom py-3 px-3 bg-white flex-shrink-0">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h5 class="mb-0 fw-bold">Conversations</h5>
            <a href="{{ route('inbox.index', ['filter' => $filter, 'q' => $search]) }}"
                class="btn btn-icon btn-sm btn-outline-secondary" title="Refresh">
                <i class="bx bx-refresh"></i>
            </a>
        </div>

        <form method="GET" action="{{ route('inbox.index') }}" class="mb-3">
            <input type="hidden" name="filter" value="{{ $filter }}">
            <div class="input-group input-group-merge">
                <span class="input-group-text bg-transparent border-end-0"><i class="bx bx-search"></i></span>
                <input type="text" name="q" value="{{ $search }}"
                    class="form-control border-start-0 ps-0" placeholder="Cari nama, email, atau nomor telepon...">
            </div>
        </form>

        {{-- ICON GROUP FILTER --}}
        <div class="d-flex align-items-center gap-2">
            <div class="inbox-filter-group d-flex align-items-center gap-1 flex-grow-1 w-100">

                @foreach ($primaryFilters as $value => $meta)
                    <a href="{{ route('inbox.index', ['filter' => $value, 'q' => $search]) }}"
                        class="btn btn-sm flex-fill d-flex align-items-center justify-content-center position-relative
            {{ $filter === $value ? 'btn-primary' : 'btn-outline-secondary' }}"
                        data-bs-toggle="tooltip" data-bs-placement="top" title="{{ $meta['label'] }}">

                        <i class="bx {{ $meta['icon'] }}"></i>

                        @if ($value === 'unread' && ($unreadCount ?? 0) > 0)
                            <span
                                class="badge bg-primary rounded-pill position-absolute top-0 start-100 translate-middle">
                                {{ $unreadCount > 999 ? round($unreadCount / 1000, 1) . 'K' : $unreadCount }}
                            </span>
                        @endif

                    </a>
                @endforeach

            </div>

            <div class="dropdown">
                <button type="button" class="btn btn-icon btn-sm btn-outline-secondary dropdown-toggle"
                    data-bs-toggle="dropdown" aria-expanded="false" title="Filter lainnya">
                    <i class="bx bx-filter-alt"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    @foreach ($moreFilters as $value => $meta)
                        <li>
                            <a class="dropdown-item {{ $filter === $value ? 'active' : '' }}"
                                href="{{ route('inbox.index', ['filter' => $value, 'q' => $search]) }}">
                                <i class="bx {{ $meta['icon'] }} me-2"></i>{{ $meta['label'] }}
                                <span class="text-muted small">(lokal)</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>

    {{-- Select All Bar (Fixed) --}}
    <div class="d-flex align-items-center border-bottom px-3 py-2 bg-white flex-shrink-0">
        <div class="form-check mb-0">
            <input class="form-check-input" type="checkbox" id="selectAllConversations">
            <label class="form-check-label small text-muted" for="selectAllConversations">Select All</label>
        </div>
    </div>

    @if ($ghlError ?? false)
        <div class="alert alert-danger rounded-0 mb-0 flex-shrink-0" role="alert">
            Unable to load conversations from GHL. Please try again.
        </div>
    @endif

    {{-- List Percakapan (Satu-satunya area yang bisa di-scroll secara internal) --}}
    <div class="email-list flex-grow-1 overflow-y-auto">
        <ul class="list-unstyled m-0" id="conversationList">
            @forelse($conversations as $item)
                @include('inbox.components.conversation-item', [
                    'item' => $item,
                    'filter' => $filter,
                    'isActive' =>
                        $activeConversation &&
                        $activeConversation->ghl_conversation_id === $item->ghlConversationId,
                ])
            @empty
                <li class="text-center p-5 text-muted bg-white">
                    <i class="bx bx-folder-open display-4 mb-2"></i>
                    <p class="mb-0">
                        @if ($filter === 'unread')
                            Tidak ada pesan yang belum dibaca.
                        @elseif ($filter === 'recent')
                            Tidak ada percakapan dalam 24 jam terakhir.
                        @elseif ($filter === 'starred')
                            Belum ada percakapan yang dibintangi.
                        @elseif ($filter === 'waiting_agent')
                            Tidak ada percakapan yang menunggu balasan agent.
                        @elseif ($filter === 'waiting_customer')
                            Tidak ada percakapan yang menunggu respon customer.
                        @elseif ($filter === 'ai_draft')
                            Tidak ada draft AI yang tersedia.
                        @elseif ($filter === 'closed')
                            Tidak ada percakapan yang ditutup.
                        @else
                            Tidak ada percakapan.
                        @endif
                    </p>
                </li>
            @endforelse
        </ul>
    </div>

    {{-- Pagination Footer (Fixed Bottom) --}}
    @if ($localPaginator && $localPaginator->hasPages())
        <div class="card-footer bg-white py-2 px-4 d-flex justify-content-center border-top flex-shrink-0">
            {{ $localPaginator->links() }}
        </div>
    @elseif ($nextCursor ?? null)
        <div class="card-footer bg-white py-2 px-4 d-flex justify-content-center border-top flex-shrink-0">
            <a href="{{ route('inbox.index', array_merge(['filter' => $filter, 'q' => $search], $nextCursor)) }}"
                class="btn btn-sm btn-outline-secondary">
                Load more
            </a>
        </div>
    @endif

</div>

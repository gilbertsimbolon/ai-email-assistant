@php
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
@endphp

{{-- Panel kiri --}}
<div class="d-flex flex-column h-100 overflow-hidden bg-white border-end">

    {{-- Header --}}
    <div class="border-bottom py-3 px-3 bg-white flex-shrink-0">

        <div class="d-flex align-items-center justify-content-between mb-3">
            <h5 class="mb-0 fw-bold">Conversations</h5>

            <a href="{{ route('inbox.index', ['filter' => $filter, 'q' => $search]) }}"
                class="btn btn-icon btn-sm btn-outline-secondary"
                title="Refresh">
                <i class="bx bx-refresh"></i>
            </a>
        </div>

        {{-- Search --}}
        <form method="GET" action="{{ route('inbox.index') }}" class="mb-3">
            <input type="hidden" name="filter" value="{{ $filter }}">

            <div class="input-group input-group-merge">
                <span class="input-group-text bg-transparent border-end-0">
                    <i class="bx bx-search"></i>
                </span>

                <input
                    type="text"
                    name="q"
                    value="{{ $search }}"
                    class="form-control border-start-0 ps-0"
                    placeholder="Cari nama, email, atau nomor telepon..."
                >
            </div>
        </form>

        {{-- Filters --}}
        <div class="d-flex align-items-center gap-2">

            <div class="d-flex align-items-center gap-1 flex-grow-1">

                @foreach ($primaryFilters as $value => $meta)

                    <a href="{{ route('inbox.index', [
                        'filter' => $value,
                        'q' => $search,
                    ]) }}"
                        class="btn btn-sm flex-fill d-flex align-items-center justify-content-center position-relative
                        {{ $filter === $value ? 'btn-primary' : 'btn-outline-secondary' }}"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        title="{{ $meta['label'] }}">

                        <i class="bx {{ $meta['icon'] }}"></i>

                        @if ($value === 'unread' && ($unreadCount ?? 0) > 0)
                            <span class="badge bg-primary rounded-pill position-absolute top-0 start-100 translate-middle">
                                {{ $unreadCount > 999 ? round($unreadCount / 1000, 1) . 'K' : $unreadCount }}
                            </span>
                        @endif

                    </a>

                @endforeach

            </div>

            {{-- More filters --}}
            <div class="dropdown">

                <button
                    type="button"
                    class="btn btn-icon btn-sm btn-outline-secondary"
                    data-bs-toggle="dropdown"
                    title="Filter lainnya">

                    <i class="bx bx-filter-alt"></i>

                </button>

                <ul class="dropdown-menu dropdown-menu-end">

                    @foreach ($moreFilters as $value => $meta)

                        <li>
                            <a
                                class="dropdown-item {{ $filter === $value ? 'active' : '' }}"
                                href="{{ route('inbox.index', [
                                    'filter' => $value,
                                    'q' => $search,
                                ]) }}">

                                <i class="bx {{ $meta['icon'] }} me-2"></i>

                                {{ $meta['label'] }}

                            </a>
                        </li>

                    @endforeach

                </ul>

            </div>

        </div>

    </div>


    {{-- Error --}}
    @if ($ghlError ?? false)

        <div class="alert alert-danger rounded-0 mb-0 flex-shrink-0" role="alert">

            Unable to load conversations from GHL.
            Please try again.

        </div>

    @endif


    {{-- Conversation List --}}
    <div class="flex-grow-1 overflow-auto">

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

                <li class="text-center p-5 text-muted">

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

        {{-- Load More: pages forward through GHL's real cursor
             (startAfterDate/startAfter) instead of ever asking for a bigger
             single limit (claude.txt Task 2). Hidden whenever GHL didn't
             report a next page. --}}
        <div id="conversationListLoadMore"
            class="text-center py-3 {{ $nextCursor ? '' : 'd-none' }}"
            data-start-after-date="{{ $nextCursor['startAfterDate'] ?? '' }}"
            data-start-after="{{ $nextCursor['startAfter'] ?? '' }}">

            <button type="button" id="btnLoadMoreConversations" class="btn btn-sm btn-outline-secondary">
                Load More
            </button>

        </div>

    </div>

</div>
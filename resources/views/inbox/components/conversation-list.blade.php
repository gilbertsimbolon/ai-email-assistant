@php
    $filterOptions = [
        'all' => 'All',
        'unread' => 'Unread',
        'recent' => 'Recent',
        'starred' => 'Starred',
        'waiting_agent' => 'Waiting Agent',
        'waiting_customer' => 'Waiting Customer',
        'ai_draft' => 'AI Draft',
        'closed' => 'Closed',
    ];

    // Menggunakan kelas icon Boxicons yang sesuai dengan Bootstrap/Sneat theme
    $filterIcons = [
        'all' => 'bx-grid-alt',
        'unread' => 'bx-envelope',
        'recent' => 'bx-time-five',
        'starred' => 'bx-star',
        'waiting_agent' => 'bx-user-voice',
        'waiting_customer' => 'bx-user-check',
        'ai_draft' => 'bx-bot',
        'closed' => 'bx-check-circle',
    ];

    $localOnlyFilters = ['recent', 'starred', 'waiting_agent', 'waiting_customer', 'ai_draft', 'closed'];
@endphp

<div class="border-bottom py-3 px-3 bg-white flex-shrink-0 ms-2">
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
            <input type="text" name="q" value="{{ $search }}" class="form-control border-start-0 ps-0"
                placeholder="Cari nama, email, atau nomor telepon...">
        </div>
    </form>

    {{-- ICON GROUP FILTER --}}
    <div class="btn-group w-100 flex-wrap" role="group" aria-label="Filter Conversations">
        @foreach ($filterOptions as $value => $label)
            @php
                $isLocal = in_array($value, $localOnlyFilters, true);
                $tooltip = $label . ($isLocal ? ' (Percakapan lokal)' : '');
            @endphp

            <a href="{{ route('inbox.index', ['filter' => $value, 'q' => $search]) }}"
                class="btn btn-sm {{ $filter === $value ? 'btn-primary' : 'btn-outline-secondary' }}"
                data-bs-toggle="tooltip" data-bs-placement="top" title="{{ $tooltip }}">
                <i class="bx {{ $filterIcons[$value] ?? 'bx-filter' }}"></i>
            </a>
        @endforeach
    </div>
</div>

@if ($ghlError ?? false)
    <div class="alert alert-danger rounded-0 mb-0 flex-shrink-0" role="alert">
        Unable to load conversations from GHL. Please try again.
    </div>
@endif

{{-- List Percakapan --}}
<div class="email-list flex-grow-1 overflow-auto ms-2">
    <ul class="list-unstyled m-0" id="conversationList">
        @forelse($conversations as $item)
            @include('inbox.components.conversation-item', [
                'item' => $item,
                'filter' => $filter,
                'isActive' =>
                    $activeConversation && $activeConversation->ghl_conversation_id === $item->ghlConversationId,
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

{{-- Pagination Footer --}}
@if ($localPaginator && $localPaginator->hasPages())
    <div class="card-footer bg-white py-2 px-4 d-flex justify-content-center border-top flex-shrink-0 ms-2">
        {{ $localPaginator->links() }}
    </div>
@elseif ($nextCursor ?? null)
    <div class="card-footer bg-white py-2 px-4 d-flex justify-content-center border-top flex-shrink-0 ms-2">
        <a href="{{ route('inbox.index', array_merge(['filter' => $filter, 'q' => $search], $nextCursor)) }}"
            class="btn btn-sm btn-outline-secondary">
            Load more
        </a>
    </div>
@endif

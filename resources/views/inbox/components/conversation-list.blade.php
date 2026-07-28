{{-- Panel Kiri: toolbar (search & filter) + daftar percakapan --}}
@php
    $filterOptions = [
        'all' => 'All',
        'unread' => 'Unread',
        'starred' => 'Starred',
        'waiting_agent' => 'Waiting Agent',
        'waiting_customer' => 'Waiting Customer',
        'ai_draft' => 'AI Draft',
        'closed' => 'Closed',
    ];
@endphp

<div class="border-bottom py-3 px-3 bg-white flex-shrink-0">
    <form method="GET" action="{{ route('inbox.index') }}" class="mb-3">
        <input type="hidden" name="filter" value="{{ $filter }}">
        <div class="input-group input-group-merge">
            <span class="input-group-text bg-transparent border-end-0"><i class="bx bx-search"></i></span>
            <input type="text" name="q" value="{{ $search }}" class="form-control border-start-0 ps-0" placeholder="Cari nama, email, atau subjek...">
        </div>
    </form>

    <div class="d-flex flex-wrap gap-1">
        @foreach ($filterOptions as $value => $label)
            <a href="{{ route('inbox.index', ['filter' => $value, 'q' => $search]) }}"
               class="btn btn-sm {{ $filter === $value ? 'btn-primary' : 'btn-outline-secondary' }}">
                {{ $label }}
            </a>
        @endforeach

        <a href="{{ route('inbox.index', ['filter' => $filter, 'q' => $search]) }}" class="btn btn-icon btn-sm btn-outline-secondary ms-auto" title="Refresh">
            <i class="bx bx-refresh"></i>
        </a>
    </div>
</div>

{{-- List Email / Percakapan --}}
<div class="email-list flex-grow-1 overflow-auto">
    <ul class="list-unstyled m-0" id="conversationList">
        @forelse($conversations as $item)
            @include('inbox.components.conversation-item', ['item' => $item, 'filter' => $filter, 'isActive' => $activeConversation && $activeConversation->id === $item->id])
        @empty
            <li class="text-center p-5 text-muted bg-white">
                <i class="bx bx-folder-open display-4 mb-2"></i>
                <p class="mb-0">
                    @if ($filter === 'unread')
                        Tidak ada pesan yang belum dibaca.
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

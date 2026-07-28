{{-- Panel Kiri: toolbar (search & filter) + daftar percakapan --}}

<div class="card-header border-bottom py-3 px-4 bg-white flex-shrink-0">
    <form method="GET" action="{{ route('inbox.index') }}" class="mb-3">
        <input type="hidden" name="filter" value="{{ $filter }}">
        <div class="input-group input-group-merge">
            <span class="input-group-text bg-transparent border-end-0"><i class="bx bx-search"></i></span>
            <input type="text" name="q" value="{{ $search }}" class="form-control border-start-0 ps-0" placeholder="Cari nama, email, atau subjek...">
        </div>
    </form>

    <div class="d-flex align-items-center justify-content-between gap-2">
        {{-- Filter: All / Unread / Starred --}}
        <div class="btn-group" role="group" aria-label="Inbox Filters">
            <a href="{{ route('inbox.index', ['filter' => 'all', 'q' => $search]) }}"
               class="btn btn-sm {{ $filter === 'all' ? 'btn-primary' : 'btn-outline-secondary' }}">
                All
            </a>
            <a href="{{ route('inbox.index', ['filter' => 'unread', 'q' => $search]) }}"
               class="btn btn-sm {{ $filter === 'unread' ? 'btn-primary' : 'btn-outline-secondary' }}">
                Unread
            </a>
            <a href="{{ route('inbox.index', ['filter' => 'starred', 'q' => $search]) }}"
               class="btn btn-sm {{ $filter === 'starred' ? 'btn-primary' : 'btn-outline-secondary' }}">
                Starred
            </a>
        </div>

        <a href="{{ route('inbox.index', ['filter' => $filter, 'q' => $search]) }}" class="btn btn-icon btn-sm btn-outline-secondary" title="Refresh">
            <i class="bx bx-refresh"></i>
        </a>
    </div>
</div>

{{-- List Email / Percakapan --}}
<div class="email-list flex-grow-1 overflow-auto">
    <ul class="list-unstyled m-0">
        @forelse($conversations as $item)
            @include('inbox.partials.list-item', ['item' => $item, 'filter' => $filter, 'isActive' => $activeConversation && $activeConversation->id === $item->id])
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

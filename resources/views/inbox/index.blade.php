@extends('layouts.app') {{-- Sesuaikan dengan nama layout utama Anda --}}

@section('content')
    <div class="container-fluid py-4">
        <h2 class="mb-4">Inbox Percakapan</h2>

        {{-- Filter Status --}}
        <div class="mb-3 btn-group">
            <a href="{{ route('inbox.index', ['status' => 'pending_review']) }}"
                class="btn {{ $status === 'pending_review' ? 'btn-primary' : 'btn-outline-primary' }}">
                Pending Review
            </a>
            <a href="{{ route('inbox.index', ['status' => 'replied']) }}"
                class="btn {{ $status === 'replied' ? 'btn-primary' : 'btn-outline-primary' }}">
                Replied
            </a>
            <a href="{{ route('inbox.index', ['status' => 'closed']) }}"
                class="btn {{ $status === 'closed' ? 'btn-primary' : 'btn-outline-primary' }}">
                Closed
            </a>
        </div>

        <div id="inbox-list" data-status="{{ $status }}">
            @include('inbox.partials.list')
        </div>
    </div>

    <script>
        (function () {
            const container = document.getElementById('inbox-list');
            if (!container) {
                return;
            }

            const status = container.dataset.status;
            const pollUrl = @json(route('inbox.poll'));

            async function refresh() {
                const page = new URLSearchParams(window.location.search).get('page') || 1;

                try {
                    const response = await fetch(
                        `${pollUrl}?status=${encodeURIComponent(status)}&page=${encodeURIComponent(page)}`,
                        { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
                    );

                    if (!response.ok) {
                        return;
                    }

                    const data = await response.json();
                    container.innerHTML = data.html;
                } catch (error) {
                    // Silent fail — the next tick will retry.
                }
            }

            setInterval(refresh, 15000);
        })();
    </script>
@endsection

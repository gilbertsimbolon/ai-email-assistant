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
        <div class="col-12 col-lg-4 col-xl-3 border-end app-emails-list h-100 flex-column {{ $activeConversation ? 'd-none d-lg-flex' : 'd-flex' }}">
            @include('inbox.partials.list')
        </div>

        {{-- Panel Tengah: Thread Chat + Composer --}}
        <div class="col-12 col-lg-8 col-xl-6 app-email-thread h-100 overflow-hidden {{ $activeConversation ? 'd-flex' : 'd-none d-lg-flex' }} flex-column">
            @include('inbox.partials.thread')
        </div>

        {{-- Panel Kanan: Info Percakapan. offcanvas-xl = kolom statis di layar
             >= xl (perilaku bawaan Bootstrap 5.2+), slide-in offcanvas di
             bawah itu — cukup satu markup, tidak perlu duplikasi partial. --}}
        <div class="offcanvas offcanvas-end offcanvas-xl col-xl-3 border-start app-email-info h-100 flex-column" tabindex="-1" id="infoOffcanvas">
            <div class="offcanvas-header border-bottom d-xl-none">
                <h6 class="offcanvas-title fw-bold mb-0">Info Percakapan</h6>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
            </div>
            <div class="offcanvas-body p-0 d-flex flex-column">
                @include('inbox.partials.info')
            </div>
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
@if ($activeConversation)
    <script src="{{ asset('js/inbox-composer.js') }}"></script>
@endif
@endsection

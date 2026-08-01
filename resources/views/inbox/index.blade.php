@extends('layouts.app')

@section('title', 'Conversations | AI Email Assistant')

@section('content-padding', 'p-0')

@section('content')

    @unless ($ghlConfigured)
        <div class="alert alert-warning rounded-0 mb-0 flex-shrink-0">
            <span>GoHighLevel Private Integration belum dikonfigurasi, jadi belum ada data untuk ditampilkan di sini. Hubungi admin untuk mengatur GHL_API_KEY &amp; GHL_LOCATION_ID.</span>
        </div>
    @endunless

    {{-- Layout 3 panel murni flexbox (bukan Bootstrap Card) supaya mengisi tinggi
     layar dan scroll hanya terjadi di panel yang membutuhkan (list & thread). --}}
    <div id="inboxApp" class="inbox-app d-flex overflow-hidden flex-grow-1"
        data-star-url-template="{{ route('inbox.star', ['conversation' => '__ID__']) }}"
        data-read-url-template="{{ route('inbox.read.toggle', ['conversation' => '__ID__']) }}">

        {{-- Panel Kiri: Daftar Percakapan --}}
        <div id="conversationListPanel"
            class="inbox-list-panel border-end h-full flex-column {{ $activeConversation ? 'd-none d-lg-flex' : 'd-flex' }}">
            @include('inbox.components.conversation-list')
        </div>

        {{-- Panel Tengah: Toolbar + Bubble Chat + Composer --}}
        <div id="threadPanel"
            class="inbox-thread-panel flex-grow-1 h-100 overflow-hidden {{ $activeConversation ? 'd-flex' : 'd-none d-lg-flex' }} flex-column">
            @include('inbox.components.conversation-thread')
        </div>

        {{-- Panel Kanan: Contact Details. offcanvas-xl = kolom statis di layar
         >= xl (perilaku bawaan Bootstrap 5.2+), slide-in offcanvas di
         bawah itu. Bisa di-collapse manual di desktop lewat #btn-toggle-ai-panel
         (lihat inbox-navigation.js), preferensinya disimpan di localStorage. --}}
        <div id="infoOffcanvas" class="offcanvas offcanvas-end offcanvas-xl inbox-ai-panel border-start h-100 flex-column"
            tabindex="-1">
            <div class="offcanvas-header border-bottom d-xl-none">
                <h6 class="offcanvas-title fw-bold mb-0">Contact Details</h6>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
            </div>
            <div class="d-none d-xl-flex align-items-center border-bottom px-3 py-2 flex-shrink-0">
                <h6 class="fw-bold mb-0"><i class="bx bx-id-card me-1"></i> Contact Details</h6>
            </div>
            <div id="aiPanelBody" class="offcanvas-body p-0 d-flex flex-column">
                @include('inbox.components.ai-panel')
            </div>
        </div>

    </div>

    <script src="{{ asset('js/inbox-composer.js') }}"></script>
    <script src="{{ asset('js/inbox-toolbar.js') }}"></script>
    <script src="{{ asset('js/inbox-navigation.js') }}"></script>
@endsection

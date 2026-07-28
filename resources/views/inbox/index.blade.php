@extends('layouts.app')

@section('title', 'Inbox Email | AI Email Assistant')

@section('content')

@unless ($hasGmailAccount)
    <div class="alert alert-warning d-flex justify-content-between align-items-center">
        <span>Belum ada akun Gmail yang terhubung, jadi belum ada data untuk ditampilkan di sini.</span>
        <a href="{{ route('settings.index') }}" class="btn btn-sm btn-warning">Hubungkan Gmail</a>
    </div>
@endunless

{{-- Layout 3 panel murni flexbox (bukan Bootstrap Card) supaya mengisi tinggi
     layar dan scroll hanya terjadi di panel yang membutuhkan (list & thread). --}}
<div id="inboxApp" class="inbox-app d-flex overflow-hidden"
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

    {{-- Panel Kanan: AI Assistant. offcanvas-xl = kolom statis di layar
         >= xl (perilaku bawaan Bootstrap 5.2+), slide-in offcanvas di
         bawah itu. Bisa di-collapse manual di desktop lewat #btn-toggle-ai-panel
         (lihat inbox-navigation.js), preferensinya disimpan di localStorage. --}}
    <div id="infoOffcanvas" class="offcanvas offcanvas-end offcanvas-xl inbox-ai-panel border-start h-100 flex-column" tabindex="-1">
        <div class="offcanvas-header border-bottom d-xl-none">
            <h6 class="offcanvas-title fw-bold mb-0">AI Assistant</h6>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
        </div>
        <div class="d-none d-xl-flex align-items-center border-bottom px-3 py-2 flex-shrink-0">
            <h6 class="fw-bold mb-0"><i class="bx bx-brain me-1"></i> AI Assistant</h6>
        </div>
        <div id="aiPanelBody" class="offcanvas-body p-0 d-flex flex-column">
            @include('inbox.components.ai-panel')
        </div>
    </div>

</div>

<script src="{{ asset('js/inbox-composer.js') }}"></script>
<script src="{{ asset('js/inbox-navigation.js') }}"></script>
@endsection

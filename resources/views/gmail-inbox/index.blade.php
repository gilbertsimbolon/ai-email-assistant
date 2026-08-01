@extends('layouts.app')

@section('title', 'Gmail Inbox | AI Email Assistant')

@section('content-padding', 'p-0')

@section('content')

    {{-- Split out of the GHL-only Conversations inbox (claude.txt only
     migrates the GHL side) — Gmail keeps its original DB-backed 3-panel
     layout, unchanged, just relocated to its own page/menu entry. --}}
    <div id="inboxApp" class="inbox-app d-flex overflow-hidden flex-grow-1"
        data-star-url-template="{{ route('inbox.star', ['conversation' => '__ID__']) }}"
        data-read-url-template="{{ route('inbox.read.toggle', ['conversation' => '__ID__']) }}">

        <div id="conversationListPanel"
            class="inbox-list-panel border-end h-full flex-column {{ $activeConversation ? 'd-none d-lg-flex' : 'd-flex' }}">
            @include('gmail-inbox.components.conversation-list')
        </div>

        <div id="threadPanel"
            class="inbox-thread-panel flex-grow-1 h-100 overflow-hidden {{ $activeConversation ? 'd-flex' : 'd-none d-lg-flex' }} flex-column">
            @include('inbox.components.conversation-thread')
        </div>

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

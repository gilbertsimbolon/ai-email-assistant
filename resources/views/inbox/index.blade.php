@extends('layouts.app')

@section('title', 'Conversations | AI Email Assistant')

@section('content-padding', 'p-0')

@section('content')

    @unless ($ghlConfigured)
        <div class="alert alert-warning rounded-0 mb-0 flex-shrink-0">
            <span>
                GoHighLevel Private Integration belum dikonfigurasi,
                jadi belum ada data untuk ditampilkan di sini.
                Hubungi admin untuk mengatur GHL_API_KEY &amp; GHL_LOCATION_ID.
            </span>
        </div>
    @endunless

    {{-- ================================================================
        INBOX APP
        3-column layout:
        1. Conversation List
        2. Conversation Thread
        3. Contact / AI Details
    ================================================================= --}}

    <div
        id="inboxApp"
        class="d-flex flex-grow-1 overflow-hidden w-100"

        data-star-url-template="{{ route('inbox.star', ['conversation' => '__ID__']) }}"
        data-read-url-template="{{ route('inbox.read.toggle', ['conversation' => '__ID__']) }}"
        data-list-poll-url="{{ route('inbox.index', ['filter' => $filter, 'q' => $search]) }}"
        data-messages-poll-url-template="{{ route('inbox.messages', ['conversation' => '__ID__']) }}"
        data-active-conversation-id="{{ $activeConversation?->ghl_conversation_id }}"
    >

        {{-- ============================================================
            COLUMN 1 — CONVERSATION LIST
        ============================================================= --}}

        <aside
            id="conversationListPanel"
            class="inbox-list-panel border-end d-flex flex-column flex-shrink-0
                {{ $activeConversation ? 'd-none d-lg-flex' : 'd-flex' }}"
        >
            @include('inbox.components.conversation-list')
        </aside>


        {{-- ============================================================
            COLUMN 2 — CONVERSATION THREAD
        ============================================================= --}}

        <main
            id="threadPanel"
            class="inbox-thread-panel d-flex flex-column flex-grow-1 min-w-0
                {{ $activeConversation ? 'd-flex' : 'd-none d-lg-flex' }}"
        >
            @include('inbox.components.conversation-thread')
        </main>


        {{-- ============================================================
            COLUMN 3 — CONTACT / AI PANEL
        ============================================================= --}}

        <aside
            id="infoPanel"
            class="inbox-info-panel border-start d-none d-xl-flex flex-column flex-shrink-0" style="width: 350px">

            {{-- Header --}}
            <div
                class="d-flex align-items-center justify-content-between
                    border-bottom px-3 py-2 flex-shrink-0"
            >

                <h6 class="fw-bold mb-0">
                    <i class="bx bx-id-card me-1"></i>
                    Contact Details
                </h6>

                <div class="d-flex align-items-center gap-1">

                    {{-- Future: open contact in new window --}}
                    <button
                        type="button"
                        class="btn btn-icon btn-sm btn-outline-secondary"
                        disabled
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        title="Segera hadir (buka di jendela baru)"
                    >
                        <i class="bx bx-link-external"></i>
                    </button>

                    {{-- Collapse right panel --}}
                    <button
                        type="button"
                        id="btn-toggle-ai-panel"
                        class="btn btn-icon btn-sm btn-outline-secondary"
                        title="Tutup panel"
                    >
                        <i class="bx bx-x"></i>
                    </button>

                </div>

            </div>


            {{-- Panel Body --}}
            <div
                id="aiPanelBody"
                class="flex-grow-1 overflow-hidden d-flex flex-column"
            >
                @include('inbox.components.ai-panel')
            </div>

        </aside>

    </div>


    {{-- ================================================================
        MOBILE / TABLET RIGHT PANEL
        On < XL screens the right panel becomes an offcanvas.
    ================================================================= --}}

    <div
        class="offcanvas offcanvas-end d-xl-none"
        tabindex="-1"
        id="mobileInfoPanel"
        aria-labelledby="mobileInfoPanelLabel"
    >

        <div class="offcanvas-header border-bottom">

            <h6
                class="offcanvas-title fw-bold"
                id="mobileInfoPanelLabel"
            >
                <i class="bx bx-id-card me-1"></i>
                Contact Details
            </h6>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="offcanvas"
                aria-label="Tutup"
            ></button>

        </div>

        <div
            id="mobileAiPanelBody"
            class="offcanvas-body p-0 d-flex flex-column"
        >
            {{-- Mobile panel can be populated by JS when needed --}}
        </div>

    </div>


    {{-- ================================================================
        JAVASCRIPT
    ================================================================= --}}

    <script src="{{ asset('js/inbox-composer.js') }}"></script>
    <script src="{{ asset('js/inbox-toolbar.js') }}"></script>
    <script src="{{ asset('js/inbox-navigation.js') }}"></script>
    <script src="{{ asset('js/inbox-polling.js') }}"></script>

@endsection
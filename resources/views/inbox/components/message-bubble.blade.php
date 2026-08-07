@php
    /*
    |--------------------------------------------------------------------------
    | Sender Type & Identity
    |--------------------------------------------------------------------------
    */
    $isAgent = $message->sender_type !== \App\Enums\SenderType::Customer;

    $contactName =
        $contactDetails?->fullName() ?:
        $activeConversation->contact_name ?:
        $activeConversation->contact_email ?:
        'Pelanggan';

<<<<<<< HEAD
    $contactEmail = $contactDetails?->email ?: $activeConversation->contact_email ?: 'okaywhatsnext@gmail.com';
=======
    $contactEmail = $contactDetails?->email
        ?: $activeConversation->contact_email
        ?: '';
>>>>>>> acb3fd5 (note: untuk diperbaiki menggunakan claude)

    $contactPhone = $contactDetails?->phone ?: $activeConversation->contact_phone;

    /*
    |--------------------------------------------------------------------------
    | Sender Name & Avatar Initials
    |--------------------------------------------------------------------------
    */
    $senderName = $isAgent ? 'Dom Ricci' : $contactName;

    $senderInitials = strtoupper(substr(trim($senderName), 0, 2));

    /*
    |--------------------------------------------------------------------------
    | Recipient & Subject Header
    |--------------------------------------------------------------------------
    */
<<<<<<< HEAD
    $recipient = $isAgent ? ($contactEmail ?: $contactPhone) : 'Anda';
=======
    $recipient = $isAgent
        ? ($contactEmail ?: $contactPhone ?: 'Pelanggan')
        : 'Anda';
>>>>>>> acb3fd5 (note: untuk diperbaiki menggunakan claude)

    $subject =
        $message->subject ??
        ($message->meta['subject'] ??
            ($message->body
                ? \Illuminate\Support\Str::limit(strip_tags((string) $message->body), 40)
                : 'Tanpa Subjek'));

    /*
    |--------------------------------------------------------------------------
    | Reply Preview & Message ID
    |--------------------------------------------------------------------------
    */
    $replySnippet = \Illuminate\Support\Str::limit(
        str_replace(["\r\n", "\n", "\r"], ' ', strip_tags((string) $message->body)),
        160,
    );

    $messageId = $message->ghl_message_id ?? ($message->gmail_message_id ?? $message->id);

    $isExpanded = isset($loop) ? $loop->last : true;
@endphp

<style>
    .ghl-email-card {
        border-color: #e2e8f0 !important;
        background-color: #ffffff;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .ghl-email-card:hover {
        border-color: #cbd5e1 !important;
    }

    .ghl-email-card .card-header {
        background-color: #f4f6f8 !important;
        user-select: none;
    }

    .ghl-email-card .card-header:hover {
        background-color: #eef2f5 !important;
    }

    .cursor-pointer {
        cursor: pointer;
    }

    .toggle-email-btn[aria-expanded="true"] .accordion-icon {
        transform: rotate(180deg);
    }

    .accordion-icon {
        transition: transform 0.2s ease-in-out;
    }

    .email-html-payload {
        color: #1e293b;
    }

    .email-html-payload p {
        margin-bottom: 0.85rem;
    }

    .email-html-payload a {
        color: #0d6efd;
        text-decoration: underline;
        word-break: break-all;
    }

    .fs-7 {
        font-size: 0.8125rem !important;
    }
</style>

<<<<<<< HEAD
{{-- =========================================================
     CARD CONTAINER
========================================================== --}}
<div class="card border rounded-3 mb-3 shadow-sm ghl-email-card" id="message-card-{{ $messageId }}"
    data-message-id="{{ $messageId }}">
=======
<div class="card border rounded-3 mb-3 shadow-sm ghl-email-card" id="message-card-{{ $messageId }}" data-message-id="{{ $messageId }}">
>>>>>>> acb3fd5 (note: untuk diperbaiki menggunakan claude)

    {{-- Header Card --}}
    <div class="card-header bg-light d-flex justify-content-between align-items-center py-2 px-3 border-bottom-0 cursor-pointer toggle-email-btn"
        data-bs-toggle="collapse" data-bs-target="#email-body-{{ $messageId }}"
        aria-expanded="{{ $isExpanded ? 'true' : 'false' }}">

        <span class="fw-semibold text-dark fs-6">{{ $subject }}</span>

        <div class="d-flex align-items-center gap-2 text-muted">
            <button type="button" class="btn btn-sm p-0 text-muted border-0 me-1" title="Expand">
                <i class="bx bx-expand-alt fs-6"></i>
            </button>
            <i class="bx bx-chevron-down fs-5 transition-transform accordion-icon"></i>
        </div>
    </div>

    {{-- Card Body --}}
    <div class="card-body p-3">

        {{-- Metadata Row --}}
        <div class="d-flex align-items-start justify-content-between mb-2">
            <div class="d-flex align-items-center gap-3">

                {{-- Avatar Circle --}}
                <div class="position-relative">
                    <div class="avatar-circle {{ $isAgent ? 'bg-primary bg-opacity-10 text-primary' : 'bg-secondary bg-opacity-10 text-dark' }} fw-bold d-flex align-items-center justify-content-center rounded-circle"
                        style="width: 40px; height: 40px; font-size: 14px;">
                        {{ $senderInitials }}
                    </div>
                    <span class="position-absolute bottom-0 end-0 bg-white rounded-circle p-1 d-flex shadow-sm"
                        style="transform: translate(20%, 20%);">
                        <i class="bx bx-envelope text-primary" style="font-size: 11px;"></i>
                    </span>
                </div>

                {{-- Sender Name --}}
                <div>
                    <div class="fw-bold text-dark fs-6 lh-sm">{{ $senderName }}</div>

                    <div class="collapse {{ $isExpanded ? 'show' : '' }}" id="email-meta-to-{{ $messageId }}">
                        <div class="dropdown small text-muted">
                            To: <span class="text-secondary dropdown-toggle cursor-pointer"
                                data-bs-toggle="dropdown">{{ $recipient }}</span>
                            <ul class="dropdown-menu p-2 fs-7 shadow-sm">
                                <li><strong>From:</strong> {{ $senderName }}</li>
                                <li><strong>To:</strong> {{ $recipient }}</li>
                                <li><strong>Date:</strong> {{ $message->sent_at?->format('M j, Y, h:i A') }}</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Time & Action Menu --}}
            <div class="d-flex align-items-center gap-2 text-muted">
                @if ($message->sent_at)
                    <span class="small fw-semibold text-secondary me-1">{{ $message->sent_at->format('h:i A') }}</span>
                @endif

<<<<<<< HEAD
                {{-- Reply Button --}}
                <button type="button" class="btn btn-sm btn-icon text-muted p-0 border-0 js-msg-reply"
                    data-sender="{{ $senderName }}" data-snippet="{{ $replySnippet }}" title="Reply pesan ini">
=======
                <button
                    type="button"
                    class="btn btn-sm btn-icon text-muted p-0 border-0 js-msg-reply"
                    data-sender="{{ $senderName }}"
                    data-snippet="{{ $replySnippet }}"
                    title="Reply pesan ini">
>>>>>>> acb3fd5 (note: untuk diperbaiki menggunakan claude)
                    <i class="bx bx-undo fs-5"></i>
                </button>

                <div class="dropdown d-inline-block">
                    <button class="btn btn-sm btn-icon text-muted p-0 border-0" type="button" data-bs-toggle="dropdown"
                        title="Opsi lainnya">
                        <i class="bx bx-dots-vertical-rounded fs-5"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm fs-7">
                        <li><a class="dropdown-item js-msg-reply" href="javascript:void(0);"
                                data-sender="{{ $senderName }}" data-snippet="{{ $replySnippet }}"><i
                                    class="bx bx-undo me-2"></i>Balas</a></li>
                        <li><a class="dropdown-item" href="javascript:void(0);"><i class="bx bx-copy me-2"></i>Salin
                                Teks</a></li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- Collapsed Snippet --}}
        <div class="collapse {{ !$isExpanded ? 'show' : '' }} snippet-wrapper" id="email-snippet-{{ $messageId }}">
            <p class="text-muted small mb-0 text-truncate ms-5 ps-2" style="max-width: 85%;">
                {{ $replySnippet }}
            </p>
        </div>

<<<<<<< HEAD
        {{-- Expanded Email Body Content --}}
        <div class="collapse {{ $isExpanded ? 'show' : '' }} email-body-content mt-3"
            id="email-body-{{ $messageId }}">

            @if (preg_match('/<[a-z][\s\S]*>/i', $message->body))
                {{-- Render Email HTML utuh menggunakan iframe isolated --}}
                <div class="email-iframe-wrapper rounded border overflow-hidden bg-white">
                    <iframe srcdoc="{{ e($message->body) }}" class="w-100 border-0 email-html-iframe"
                        onload="this.style.height=(this.contentWindow.document.body.scrollHeight+20)+'px';"
                        style="min-height: 250px; width: 100%; display: block;"
                        sandbox="allow-popups allow-popups-to-escape-sandbox allow-same-origin">
                    </iframe>
                </div>
            @else
                {{-- Plain Text (SMS/LiveChat) --}}
                <div class="text-dark px-1"
                    style="font-size: 14.5px; line-height: 1.6; white-space: pre-wrap; overflow-wrap: anywhere;">
                    {{ $message->body }}
                </div>
            @endif
=======
        {{-- Expanded Body Content --}}
        <div class="collapse {{ $isExpanded ? 'show' : '' }} email-body-content mt-3" id="email-body-{{ $messageId }}">

            <div class="text-dark email-html-payload px-1" style="font-size: 14.5px; line-height: 1.6; overflow-wrap: anywhere;">
                {!! $message->body !!}
            </div>
>>>>>>> acb3fd5 (note: untuk diperbaiki menggunakan claude)

            @if (!empty($message->attachments))
                <div class="mt-3 pt-2 border-top d-flex flex-wrap gap-2">
                    @foreach ($message->attachments as $attachment)
                        @include('inbox.components.attachment', [
                            'message' => $message,
                            'attachment' => $attachment,
                        ])
                    @endforeach
                </div>
            @endif

            <div class="mt-4 pt-2">
                <button type="button"
                    class="btn btn-primary btn-sm px-3 d-inline-flex align-items-center gap-1 shadow-sm js-msg-reply"
                    data-sender="{{ $senderName }}" data-snippet="{{ $replySnippet }}">
                    <i class="bx bx-undo fs-5"></i> Reply
                </button>
            </div>

        </div>

    </div>
</div>

<script>
    if (typeof ghlCardToggleInit === 'undefined') {
        var ghlCardToggleInit = true;

        document.addEventListener('DOMContentLoaded', function() {
            document.addEventListener('click', function(e) {
                const header = e.target.closest('.toggle-email-btn');
                if (!header) return;

                const targetId = header.getAttribute('data-bs-target').replace('#email-body-', '');
                const snippetEl = document.getElementById('email-snippet-' + targetId);
                const metaToEl = document.getElementById('email-meta-to-' + targetId);
                const isExpanded = header.getAttribute('aria-expanded') === 'true';

                if (snippetEl) {
                    const bsSnippet = bootstrap.Collapse.getInstance(snippetEl) || new bootstrap
                        .Collapse(snippetEl, {
                            toggle: false
                        });
                    isExpanded ? bsSnippet.show() : bsSnippet.hide();
                }

                if (metaToEl) {
                    const bsMetaTo = bootstrap.Collapse.getInstance(metaToEl) || new bootstrap.Collapse(
                        metaToEl, {
                            toggle: false
                        });
                    isExpanded ? bsMetaTo.hide() : bsMetaTo.show();
                }
            });
        });
    }
</script>
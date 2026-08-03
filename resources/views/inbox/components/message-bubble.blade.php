@php
    $isAgent = $message->sender_type !== \App\Enums\SenderType::Customer;

    $senderName = $isAgent
        ? 'Anda'
        : ($activeConversation->contact_name
            ?? $activeConversation->contact_email
            ?? 'Pelanggan');

    $senderInitials = strtoupper(substr($senderName, 0, 2));

    $recipient = $isAgent
        ? ($activeConversation->contact_email ?? $activeConversation->contact_phone)
        : null;

    // Single-line preview used by the per-message Reply button (composer
    // reply-preview bar) — never re-rendered server-side elsewhere, so no
    // HTML escaping concerns beyond the blade attribute output below.
    $replySnippet = \Illuminate\Support\Str::limit(
        str_replace(["\r\n", "\n", "\r"], ' ', strip_tags((string) $message->body)),
        160
    );
@endphp

<div
    class="d-flex mb-4 gap-2 {{ $isAgent ? 'justify-content-end' : 'justify-content-start' }}"
    data-message-id="{{ $message->ghl_message_id ?? $message->gmail_message_id ?? $message->id }}"
>

    {{-- CUSTOMER AVATAR --}}
    @unless ($isAgent)

        <div class="avatar avatar-sm flex-shrink-0">
            <span class="avatar-initial rounded-circle bg-label-secondary text-dark fw-bold">
                {{ $senderInitials }}
            </span>
        </div>

    @endunless


    {{-- MESSAGE --}}
    <div
        class="d-flex flex-column {{ $isAgent ? 'align-items-end' : 'align-items-start' }}"
        style="max-width: 80%;"
    >

        {{-- Sender / meta --}}
        <div class="d-flex align-items-center flex-wrap gap-1 mb-1 px-1 small">

            <span class="fw-semibold text-dark">
                {{ $senderName }}
            </span>

            @if ($recipient)
                <span class="text-muted">
                    · To: {{ $recipient }}
                </span>
            @endif

            @if ($message->sent_at)
                <span class="text-muted">
                    · {{ $message->sent_at->format('h:i A') }}
                </span>
            @endif

            <button type="button"
                class="btn btn-link btn-sm text-muted p-0 ms-1 js-msg-reply"
                data-sender="{{ $senderName }}"
                data-snippet="{{ $replySnippet }}"
                title="Reply pesan ini"
            >
                <i class="bx bx-reply"></i>
            </button>

        </div>


        {{-- Bubble --}}
        <div
            class="border rounded-3 px-3 py-3 shadow-sm {{ $isAgent ? 'chat-bubble-agent' : 'chat-bubble-customer' }}"
        >

            {{-- Message body --}}
            <div
                class="text-dark"
                style="white-space: pre-wrap; overflow-wrap: anywhere;"
            >
                {{ $message->body }}
            </div>


            {{-- Attachments --}}
            @if (!empty($message->attachments))

                <div class="mt-3 d-flex flex-wrap gap-2">

                    @foreach ($message->attachments as $attachment)

                        @include('inbox.components.attachment', [
                            'message' => $message,
                            'attachment' => $attachment
                        ])

                    @endforeach

                </div>

            @endif

        </div>

    </div>


    {{-- AGENT AVATAR --}}
    @if ($isAgent)

        <div class="avatar avatar-sm flex-shrink-0">

            <span class="avatar-initial rounded-circle bg-label-primary text-primary fw-bold">
                {{ $senderInitials }}
            </span>

        </div>

    @endif

</div>

@php
    /*
    |--------------------------------------------------------------------------
    | Sender Type
    |--------------------------------------------------------------------------
    */

    $isAgent = $message->sender_type !== \App\Enums\SenderType::Customer;


    /*
    |--------------------------------------------------------------------------
    | Contact Identity
    |--------------------------------------------------------------------------
    | Prioritaskan data contact langsung dari GHL.
    | Fallback ke conversation anchor jika contactDetails tidak tersedia.
    |--------------------------------------------------------------------------
    */

    $contactName =
        $contactDetails?->fullName()
        ?: $activeConversation->contact_name
        ?: $activeConversation->contact_email
        ?: 'Pelanggan';

    $contactEmail =
        $contactDetails?->email
        ?: $activeConversation->contact_email;

    $contactPhone =
        $contactDetails?->phone
        ?: $activeConversation->contact_phone;


    /*
    |--------------------------------------------------------------------------
    | Sender
    |--------------------------------------------------------------------------
    */

    $senderName = $isAgent
        ? 'Anda'
        : $contactName;


    /*
    |--------------------------------------------------------------------------
    | Initials
    |--------------------------------------------------------------------------
    */

    $senderInitials = strtoupper(
        substr(
            trim($senderName),
            0,
            2
        )
    );


    /*
    |--------------------------------------------------------------------------
    | Recipient
    |--------------------------------------------------------------------------
    */

    $recipient = $isAgent
        ? ($contactEmail ?: $contactPhone)
        : null;


    /*
    |--------------------------------------------------------------------------
    | Reply Preview
    |--------------------------------------------------------------------------
    */

    $replySnippet = \Illuminate\Support\Str::limit(
        str_replace(
            ["\r\n", "\n", "\r"],
            ' ',
            strip_tags((string) $message->body)
        ),
        160
    );


    /*
    |--------------------------------------------------------------------------
    | Message ID
    |--------------------------------------------------------------------------
    */

    $messageId =
        $message->ghl_message_id
        ?? $message->gmail_message_id
        ?? $message->id;
@endphp


<div
    class="d-flex mb-4 gap-2 {{ $isAgent ? 'justify-content-end' : 'justify-content-start' }}"
    data-message-id="{{ $messageId }}"
>

    {{-- =========================================================
         CUSTOMER AVATAR
    ========================================================== --}}
    @unless ($isAgent)

        <div class="avatar avatar-sm flex-shrink-0">

            <span class="avatar-initial rounded-circle bg-label-secondary text-dark fw-bold">
                {{ $senderInitials }}
            </span>

        </div>

    @endunless


    {{-- =========================================================
         MESSAGE CONTENT
    ========================================================== --}}
    <div
        class="d-flex flex-column {{ $isAgent ? 'align-items-end' : 'align-items-start' }}"
        style="max-width: 80%;"
    >

        {{-- =====================================================
             MESSAGE META
        ====================================================== --}}
        <div class="d-flex align-items-center flex-wrap gap-1 mb-1 px-1 small">

            {{-- Sender --}}
            <span class="fw-semibold text-dark">
                {{ $senderName }}
            </span>


            {{-- Recipient --}}
            @if ($recipient)

                <span class="text-muted">
                    · To: {{ $recipient }}
                </span>

            @endif


            {{-- Time --}}
            @if ($message->sent_at)

                <span class="text-muted">
                    · {{ $message->sent_at->format('h:i A') }}
                </span>

            @endif


            {{-- Reply --}}
            <button
                type="button"
                class="btn btn-link btn-sm text-muted p-0 ms-1 js-msg-reply"
                data-sender="{{ $senderName }}"
                data-snippet="{{ $replySnippet }}"
                title="Reply pesan ini"
            >
                <i class="bx bx-reply"></i>
            </button>

        </div>


        {{-- =====================================================
             MESSAGE BODY
        ====================================================== --}}
        <div
            class="border rounded-3 px-3 py-3 shadow-sm {{ $isAgent ? 'chat-bubble-agent' : 'chat-bubble-customer' }}"
        >

            <div
                class="text-dark"
                style="white-space: pre-wrap; overflow-wrap: anywhere;"
            >
                {{ $message->body }}
            </div>


            {{-- =================================================
                 ATTACHMENTS
            ================================================== --}}
            @if (!empty($message->attachments))

                <div class="mt-3 d-flex flex-wrap gap-2">

                    @foreach ($message->attachments as $attachment)

                        @include('inbox.components.attachment', [
                            'message' => $message,
                            'attachment' => $attachment,
                        ])

                    @endforeach

                </div>

            @endif

        </div>

    </div>


    {{-- =========================================================
         AGENT AVATAR
    ========================================================== --}}
    @if ($isAgent)

        <div class="avatar avatar-sm flex-shrink-0">

            <span class="avatar-initial rounded-circle bg-label-primary text-primary fw-bold">
                {{ $senderInitials }}
            </span>

        </div>

    @endif

</div>
@php
    $isAgent = $message->sender_type !== \App\Enums\SenderType::Customer;
    $senderName = $isAgent ? 'Anda' : ($activeConversation->contact_name ?? $activeConversation->contact_email ?? 'Pelanggan');
    $senderInitials = strtoupper(substr($senderName, 0, 2));
    // "To" hanya bisa ditampilkan untuk pesan dari agent — penerimanya adalah
    // kontak GHL yang sudah kita ketahui. Untuk pesan dari customer, tujuan
    // aslinya (mailbox/nomor perusahaan) tidak tersimpan di data ini.
    $recipient = $isAgent ? ($activeConversation->contact_email ?? $activeConversation->contact_phone) : null;
@endphp
<div class="d-flex mb-3 gap-2 {{ $isAgent ? 'justify-content-end' : 'justify-content-start' }}"
     data-message-id="{{ $message->ghl_message_id ?? $message->gmail_message_id ?? $message->id }}">

    @unless ($isAgent)
        <div class="avatar avatar-sm flex-shrink-0">
            <span class="avatar-initial rounded-circle bg-label-secondary text-dark fw-bold">{{ $senderInitials }}</span>
        </div>
    @endunless

    <div class="chat-bubble {{ $isAgent ? 'chat-bubble-agent' : 'chat-bubble-customer' }}">
        <div class="chat-bubble-meta small text-muted d-flex align-items-center flex-wrap gap-1">
            <span class="fw-semibold text-dark">{{ $senderName }}</span>
            @if ($recipient)
                <span>&middot; To: {{ $recipient }}</span>
            @endif
            <span>&middot; {{ $message->sent_at?->format('h:i A') }}</span>
        </div>
        <div class="chat-bubble-body">{{ $message->body }}</div>

        @if (!empty($message->attachments))
            <div class="chat-bubble-attachments mt-2 d-flex flex-wrap gap-2">
                @foreach ($message->attachments as $attachment)
                    @include('inbox.components.attachment', ['message' => $message, 'attachment' => $attachment])
                @endforeach
            </div>
        @endif
    </div>

    @if ($isAgent)
        <div class="avatar avatar-sm flex-shrink-0">
            <span class="avatar-initial rounded-circle bg-label-primary text-primary fw-bold">{{ $senderInitials }}</span>
        </div>
    @endif
</div>

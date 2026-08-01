@php $isAgent = $message->sender_type !== \App\Enums\SenderType::Customer; @endphp
<div class="d-flex mb-3 {{ $isAgent ? 'justify-content-end' : 'justify-content-start' }}"
     data-message-id="{{ $message->ghl_message_id ?? $message->gmail_message_id ?? $message->id }}">
    <div class="chat-bubble {{ $isAgent ? 'chat-bubble-agent' : 'chat-bubble-customer' }}">
        <div class="chat-bubble-meta small {{ $isAgent ? 'text-white-50' : 'text-muted' }}">
            {{ $isAgent ? 'Anda' : ($activeConversation->contact_name ?? 'Pelanggan') }}
            &middot; {{ $message->sent_at?->format('d M, H:i') }}
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
</div>

@if ($activeConversation)
    <div class="email-thread d-flex flex-column h-100" id="conversationThread"
        data-conversation-id="{{ $activeConversation->id }}">

        @include('inbox.components.toolbar', ['activeConversation' => $activeConversation])

        {{-- Bubble Chat --}}
        <div class="chat-history flex-grow-1 overflow-auto p-4" id="chatHistory">
            @php $lastDividerDate = null; @endphp
            @forelse ($activeConversation->messages as $message)
                @php $messageDate = $message->sent_at?->toDateString(); @endphp
                @if ($messageDate && $messageDate !== $lastDividerDate)
                    @php $lastDividerDate = $messageDate; @endphp
                    <div class="chat-date-divider d-flex align-items-center text-muted small my-3">
                        <span class="flex-grow-1 border-top"></span>
                        <span class="px-2">{{ $message->sent_at->format('M j') }}</span>
                        <span class="flex-grow-1 border-top"></span>
                    </div>
                @endif
                @include('inbox.components.message-bubble', [
                    'message' => $message,
                    'activeConversation' => $activeConversation,
                    'contactDetails' => $contactDetails ?? null,
                ])
            @empty
                <div class="alert alert-secondary text-center mb-0" role="alert">
                    Belum ada riwayat pesan dalam thread ini.
                </div>
            @endforelse
        </div>

        @include('inbox.components.ai-toolbar', [
            'activeConversation' => $activeConversation,
            'activeDraft' => $activeDraft ?? null,
        ])
        @include('inbox.components.composer')
        @include('inbox.components.ai-tool-modals')
    </div>
@else
    {{-- Empty state — belum ada percakapan yang dipilih --}}
    <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted py-5">
        <i class="bx bx-envelope-open display-1 mb-3"></i>
        <p class="mb-0">Pilih percakapan di sebelah kiri untuk melihat detailnya.</p>
    </div>
@endif

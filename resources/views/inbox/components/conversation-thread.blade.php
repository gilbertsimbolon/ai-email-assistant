@if ($activeConversation)
    <div class="email-thread d-flex flex-column h-100" id="conversationThread"
        data-conversation-id="{{ $activeConversation->id }}">

        @include('inbox.components.toolbar', ['activeConversation' => $activeConversation])

        {{-- Email Card History Thread --}}
        <div class="chat-history flex-grow-1 overflow-auto p-3 bg-white" id="chatHistory">
            @php $lastDividerDate = null; @endphp

            @forelse ($activeConversation->messages as $message)
                @php $messageDate = $message->sent_at?->toDateString(); @endphp

                {{-- Date Divider Pill Style --}}
                @if ($messageDate && $messageDate !== $lastDividerDate)
                    @php $lastDividerDate = $messageDate; @endphp
                    <div class="d-flex justify-content-center my-3">
                        <span class="badge rounded-pill bg-light text-secondary border px-3 py-2 fw-normal d-inline-flex align-items-center gap-2">
                            <i class="bx bx-calendar fs-6"></i>
                            {{ $message->sent_at->format('M j') }}
                        </span>
                    </div>
                @endif

                {{-- Include Custom Message Card --}}
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
    <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted py-5">
        <i class="bx bx-envelope-open display-1 mb-3"></i>
        <p class="mb-0">Pilih percakapan di sebelah kiri untuk melihat detailnya.</p>
    </div>
@endif

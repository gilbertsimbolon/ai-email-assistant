@if ($activeConversation)
    <div class="email-thread d-flex flex-column h-100">

        {{-- Header: kontak, subjek, channel, status --}}
        <div class="card-header border-bottom py-3 px-4 bg-white d-flex align-items-start justify-content-between flex-wrap gap-2 flex-shrink-0">
            <div class="overflow-hidden">
                <a href="{{ route('inbox.index') }}" class="btn btn-icon btn-sm btn-outline-secondary d-lg-none me-2" title="Kembali ke daftar">
                    <i class="bx bx-arrow-back"></i>
                </a>
                <span class="fw-bold fs-5">{{ $activeConversation->contact_name ?? ($activeConversation->contact_email ?? 'Pelanggan') }}</span>
                <div class="text-muted small mt-1 text-truncate">
                    <i class="bx bx-envelope me-1"></i>{{ $activeConversation->contact_email ?? 'Tidak ada email' }}
                    &middot; {{ $activeConversation->subject ?: '(Tanpa subjek)' }}
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-{{ $activeConversation->status === \App\Enums\ConversationStatus::Replied ? 'success' : ($activeConversation->status === \App\Enums\ConversationStatus::Closed ? 'secondary' : 'warning') }}">
                    {{ ucwords(str_replace('_', ' ', $activeConversation->status->value)) }}
                </span>
                <button type="button" class="btn btn-icon btn-sm btn-outline-secondary d-xl-none" data-bs-toggle="offcanvas" data-bs-target="#infoOffcanvas" title="Info percakapan">
                    <i class="bx bx-info-circle"></i>
                </button>
            </div>
        </div>

        {{-- Bubble Chat --}}
        <div class="chat-history flex-grow-1 overflow-auto p-4" id="chatHistory">
            @forelse ($activeConversation->messages as $message)
                @php $isAgent = $message->sender_type !== \App\Enums\SenderType::Customer; @endphp
                <div class="d-flex mb-3 {{ $isAgent ? 'justify-content-end' : 'justify-content-start' }}">
                    <div class="chat-bubble {{ $isAgent ? 'chat-bubble-agent' : 'chat-bubble-customer' }}">
                        <div class="chat-bubble-meta small {{ $isAgent ? 'text-white-50' : 'text-muted' }}">
                            {{ $isAgent ? 'Anda' : ($activeConversation->contact_name ?? 'Pelanggan') }}
                            &middot; {{ $message->sent_at?->format('d M, H:i') }}
                        </div>
                        <div class="chat-bubble-body">{{ $message->body }}</div>

                        @if (!empty($message->attachments))
                            <div class="chat-bubble-attachments mt-2 d-flex flex-wrap gap-2">
                                @foreach ($message->attachments as $attachment)
                                    @include('inbox.partials.attachment', ['message' => $message, 'attachment' => $attachment])
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="alert alert-secondary text-center mb-0" role="alert">
                    Belum ada riwayat pesan dalam thread ini.
                </div>
            @endforelse
        </div>

        @include('inbox.partials.composer')
    </div>
@else
    {{-- Empty state — belum ada percakapan yang dipilih --}}
    <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted py-5">
        <i class="bx bx-envelope-open display-1 mb-3"></i>
        <p class="mb-0">Pilih percakapan di sebelah kiri untuk melihat detailnya.</p>
    </div>
@endif

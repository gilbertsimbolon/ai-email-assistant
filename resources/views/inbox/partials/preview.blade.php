{{-- Panel pratinjau percakapan (kanan) — dipakai oleh halaman Inbox dua-panel --}}
@if ($activeConversation)
    <div class="email-view d-flex flex-column h-100">

        {{-- Header: kontak, channel, status --}}
        <div class="card-header border-bottom py-3 px-4 bg-white d-flex align-items-start justify-content-between flex-wrap gap-2">
            <div>
                <a href="{{ route('inbox.index') }}" class="btn btn-icon btn-sm btn-outline-secondary d-lg-none me-2" title="Kembali ke daftar">
                    <i class="bx bx-arrow-back"></i>
                </a>
                <span class="fw-bold fs-5">{{ $activeConversation->contact_name ?? ($activeConversation->contact_email ?? 'Pelanggan') }}</span>
                <div class="text-muted small mt-1">
                    <i class="bx bx-envelope me-1"></i>{{ $activeConversation->contact_email ?? 'Tidak ada email' }}
                </div>
            </div>
            <div class="d-flex gap-2">
                <span class="badge bg-label-primary">{{ strtoupper($activeConversation->channel->value ?? $activeConversation->channel) }}</span>
                <span class="badge bg-{{ $activeConversation->status === \App\Enums\ConversationStatus::Replied ? 'success' : ($activeConversation->status === \App\Enums\ConversationStatus::Closed ? 'secondary' : 'warning') }}">
                    {{ ucwords(str_replace('_', ' ', $activeConversation->status->value ?? $activeConversation->status)) }}
                </span>
            </div>
        </div>

        <div class="p-4 overflow-auto flex-grow-1">

            {{-- Analisis AI --}}
            <div class="card mb-3 shadow-none border">
                <div class="card-body py-3">
                    <h6 class="fw-bold text-primary mb-2"><i class="bx bx-brain me-1"></i> AI Analysis</h6>

                    @if ($activeConversation->analysis)
                        @php
                            $sentiment = $activeConversation->analysis->sentiment->value ?? $activeConversation->analysis->sentiment ?? 'neutral';
                            $badgeColor = $sentiment === 'positive' ? 'success' : ($sentiment === 'negative' ? 'danger' : 'warning');
                        @endphp
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <label class="form-label text-muted small fw-bold mb-0">CUSTOMER INTENT</label>
                                <p class="fw-semibold mb-0 text-dark">{{ $activeConversation->analysis->customer_intent ?? '-' }}</p>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label text-muted small fw-bold mb-0">SENTIMEN</label>
                                <div><span class="badge bg-{{ $badgeColor }}">{{ ucfirst($sentiment) }}</span></div>
                            </div>
                            <div class="col-12">
                                <label class="form-label text-muted small fw-bold mb-0">RINGKASAN</label>
                                <p class="text-secondary small mb-0">{{ $activeConversation->analysis->summary ?? 'Tidak ada ringkasan.' }}</p>
                            </div>
                        </div>
                    @else
                        <p class="text-muted small mb-0">Belum ada data analisis AI untuk percakapan ini.</p>
                    @endif
                </div>
            </div>

            {{-- Riwayat Thread Pesan --}}
            <div class="card mb-3 shadow-none border">
                <div class="card-body">
                    <h6 class="fw-bold mb-3 border-bottom pb-2">Riwayat Percakapan</h6>

                    @forelse ($activeConversation->messages ?? [] as $message)
                        <div class="card border mb-3 shadow-none bg-light">
                            <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-2 px-3 border-bottom">
                                <span class="fw-bold text-dark">
                                    {{ $message->sender_type->value === 'customer' ? ($activeConversation->contact_name ?? 'Pelanggan') : 'Anda / AI' }}
                                </span>
                                <small class="text-muted">{{ $message->sent_at ? $message->sent_at->format('d M Y, H:i') : '' }}</small>
                            </div>
                            <div class="card-body py-3">
                                <p class="mb-0 text-secondary" style="white-space: pre-line;">{{ $message->body ?? '-' }}</p>

                                @if (!empty($message->attachments))
                                    <div class="mt-2 d-flex flex-wrap gap-2">
                                        @foreach ($message->attachments as $attachment)
                                            <a href="{{ route('inbox.messages.attachments.download', ['message' => $message->id, 'attachmentId' => $attachment['id']]) }}"
                                               class="badge bg-label-secondary text-decoration-none">
                                                <i class="bx bx-paperclip"></i> {{ $attachment['filename'] ?? 'attachment' }}
                                            </a>
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
            </div>

            {{-- Review / Approve / Reject Draft AI --}}
            <div class="card shadow-none border">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Draf Balasan AI</h6>

                    @if ($activeDraft)
                        <form action="{{ route('inbox.drafts.update', $activeDraft) }}" method="POST" class="mb-3">
                            @csrf
                            @method('PUT')
                            <div class="mb-3">
                                <label class="form-label small text-muted">Subjek</label>
                                <input type="text" class="form-control" name="subject"
                                       value="{{ old('subject', $activeDraft->content['subject'] ?? '') }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small text-muted">Isi Balasan</label>
                                <textarea class="form-control" name="body" rows="5">{{ old('body', $activeDraft->content['body'] ?? '') }}</textarea>
                            </div>
                            <button type="submit" class="btn btn-outline-primary btn-sm">
                                <i class="bx bx-save me-1"></i> Simpan Perubahan
                            </button>
                        </form>

                        <div class="d-flex justify-content-end gap-2">
                            <form action="{{ route('inbox.drafts.reject', $activeDraft) }}" method="POST"
                                  onsubmit="return confirm('Tolak draf ini?');">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger">
                                    <i class="bx bx-x me-1"></i> Tolak
                                </button>
                            </form>
                            <form action="{{ route('inbox.drafts.approve', $activeDraft) }}" method="POST"
                                  onsubmit="return confirm('Kirim balasan ini via Gmail?');">
                                @csrf
                                <button type="submit" class="btn btn-primary">
                                    <i class="bx bx-send me-1"></i> Approve &amp; Kirim
                                </button>
                            </form>
                        </div>
                    @else
                        <p class="text-muted small mb-0">Belum ada draf AI aktif untuk percakapan ini.</p>
                    @endif
                </div>
            </div>

        </div>
    </div>
@else
    {{-- Empty state — belum ada percakapan yang dipilih --}}
    <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted py-5">
        <i class="bx bx-envelope-open display-1 mb-3"></i>
        <p class="mb-0">Pilih percakapan di sebelah kiri untuk melihat detailnya.</p>
    </div>
@endif

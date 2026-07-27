@extends('layouts.app')

@section('title', 'Detail Percakapan | AI Email Assistant')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

    {{-- Tombol Kembali & Status --}}
    <div class="d-flex align-items-center justify-content-between mb-4">
        <a href="{{ route('inbox.index', ['status' => $conversation->status]) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bx bx-arrow-back me-1"></i> Kembali ke Inbox
        </a>
        <div class="d-flex gap-2">
            <span class="badge bg-label-primary fs-6">
                Channel: {{ strtoupper($conversation->channel->value ?? $conversation->channel) }}
            </span>
            <span class="badge bg-{{ $conversation->status === 'pending_review' ? 'warning' : ($conversation->status === 'replied' ? 'success' : 'secondary') }} fs-6">
                Status: {{ ucwords(str_replace('_', ' ', $conversation->status)) }}
            </span>
        </div>
    </div>

    <div class="row">
        {{-- Sisi Kiri: Informasi Kontak & Riwayat Thread Pesan --}}
        <div class="col-lg-8 mb-4 mb-lg-0">

            {{-- Informasi Kontak --}}
            <div class="card mb-3 shadow-sm">
                <div class="card-body">
                    <h4 class="fw-bold mb-1">{{ $conversation->contact_name ?? ($conversation->contact_email ?? 'Pelanggan') }}</h4>
                    <p class="text-muted mb-0"><i class="bx bx-envelope me-1"></i> {{ $conversation->contact_email ?? 'Tidak ada email' }}</p>
                </div>
            </div>

            {{-- List Thread Pesan (Dari Relasi messages) --}}
            <div class="card mb-4 shadow-sm">
                <div class="card-body">
                    <h5 class="fw-bold mb-3 border-bottom pb-2">Riwayat Percakapan</h5>

                    <div class="timeline">
                        @forelse($conversation->messages ?? [] as $message)
                            <div class="card border mb-3 shadow-none bg-light">
                                <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-2 px-3 border-bottom">
                                    <span class="fw-bold text-dark">
                                        {{ $message->sender_type->value === 'customer' ? ($conversation->contact_name ?? 'Pelanggan') : 'Anda / AI' }}
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
            </div>

            {{-- Review / Approve / Reject Draft AI --}}
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Draf Balasan AI</h5>

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

        {{-- Sisi Kanan: Analisis AI (Dari Relasi analysis) --}}
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="fw-bold mb-3 text-primary"><i class="bx bx-brain me-1"></i> AI Analysis</h5>

                    @if($conversation->analysis)
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">CUSTOMER INTENT</label>
                            <p class="fw-semibold mb-2 text-dark">{{ $conversation->analysis->customer_intent ?? '-' }}</p>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">SENTIMEN</label>
                            <div>
                                @php
                                    $sentiment = $conversation->analysis->sentiment->value ?? $conversation->analysis->sentiment ?? 'neutral';
                                    $badgeColor = $sentiment === 'positive' ? 'success' : ($sentiment === 'negative' ? 'danger' : 'warning');
                                @endphp
                                <span class="badge bg-{{ $badgeColor }}">{{ ucfirst($sentiment) }}</span>
                            </div>
                        </div>

                        <div class="mb-0">
                            <label class="form-label text-muted small fw-bold">RINGKASAN</label>
                            <p class="text-secondary small mb-0">{{ $conversation->analysis->summary ?? 'Tidak ada ringkasan.' }}</p>
                        </div>
                    @else
                        <p class="text-muted small mb-0">Belum ada data analisis AI untuk percakapan ini.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

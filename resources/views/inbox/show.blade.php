@extends('layouts.app')

@section('title', 'Percakapan | AI Email Assistant')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h5 class="mb-0">{{ $conversation->subject ?? 'Tanpa Subjek' }}</h5>
            <small class="text-muted">
                {{ $conversation->contact_name ?? $conversation->contact_email ?? '-' }}
                &middot; {{ $conversation->contact_email }}
                &middot; <span class="text-capitalize">{{ $conversation->channel->value }}</span>
            </small>
        </div>

        <form action="{{ route('inbox.status.update', $conversation) }}" method="POST" class="d-flex gap-2">
            @csrf
            @method('PUT')
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach (\App\Enums\ConversationStatus::cases() as $case)
                    <option value="{{ $case->value }}" @selected($conversation->status === $case)>
                        {{ ucwords(str_replace('_', ' ', $case->value)) }}
                    </option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="row">
        <div class="col-md-7">
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Percakapan</h6>
                </div>
                <div class="card-body" style="max-height: 480px; overflow-y: auto;">
                    @forelse ($conversation->messages as $message)
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <strong class="text-capitalize">{{ $message->sender_type->value }}</strong>
                                <small class="text-muted">{{ optional($message->sent_at)->format('d M Y H:i') }}</small>
                            </div>
                            <div>{{ $message->body }}</div>
                        </div>
                        <hr>
                    @empty
                        <p class="text-muted mb-0">Belum ada pesan.</p>
                    @endforelse
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Draft Balasan (AI)</h6>
                    @if ($activeDraft)
                        <span class="badge bg-label-info">v{{ $activeDraft->version }} &middot; {{ $activeDraft->status->value }}</span>
                    @endif
                </div>
                <div class="card-body">
                    @if ($activeDraft)
                        <form action="{{ route('inbox.drafts.update', $activeDraft) }}" method="POST">
                            @csrf
                            @method('PUT')

                            <div class="mb-3">
                                <label class="form-label">Subjek</label>
                                <input type="text" name="subject" class="form-control"
                                    value="{{ old('subject', $activeDraft->content['subject'] ?? '') }}">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Isi Balasan</label>
                                <textarea name="body" rows="8" class="form-control">{{ old('body', $activeDraft->content['body'] ?? '') }}</textarea>
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    Tone: {{ $activeDraft->content['tone'] ?? '-' }}
                                    @if (!is_null($activeDraft->content['confidence'] ?? null))
                                        &middot; Confidence: {{ number_format($activeDraft->content['confidence'] * 100, 0) }}%
                                    @endif
                                </small>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-outline-primary btn-sm">Simpan</button>
                                </div>
                            </div>
                        </form>

                        <form action="{{ route('inbox.drafts.approve', $activeDraft) }}" method="POST" class="mt-2">
                            @csrf
                            <button type="submit" class="btn btn-success btn-sm">Approve Draft</button>
                        </form>
                    @else
                        <p class="text-muted mb-0">Belum ada draft AI untuk percakapan ini.</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Analisis AI</h6>
                </div>
                <div class="card-body">
                    @if ($conversation->analysis)
                        @php $analysis = $conversation->analysis; @endphp
                        <dl class="row mb-0">
                            <dt class="col-5">Intent</dt>
                            <dd class="col-7">{{ $analysis->customer_intent ?? '-' }}</dd>

                            <dt class="col-5">Sentiment</dt>
                            <dd class="col-7 text-capitalize">{{ $analysis->sentiment?->value ?? '-' }}</dd>

                            <dt class="col-5">Prioritas</dt>
                            <dd class="col-7 text-capitalize">{{ $analysis->priority?->value ?? '-' }}</dd>

                            <dt class="col-5">Status Pelanggan</dt>
                            <dd class="col-7 text-capitalize">{{ str_replace('_', ' ', $analysis->customer_status?->value ?? '-') }}</dd>

                            <dt class="col-5">Perlu Eskalasi</dt>
                            <dd class="col-7">{{ $analysis->escalation_required ? 'Ya' : 'Tidak' }}</dd>

                            <dt class="col-5">Permintaan Refund</dt>
                            <dd class="col-7">{{ $analysis->refund_requested ? 'Ya' : 'Tidak' }}</dd>

                            <dt class="col-12 mt-2">Ringkasan</dt>
                            <dd class="col-12">{{ $analysis->summary }}</dd>

                            <dt class="col-12">Permintaan Terakhir</dt>
                            <dd class="col-12">{{ $analysis->last_customer_request ?? '-' }}</dd>

                            <dt class="col-12">Rekomendasi Tindakan</dt>
                            <dd class="col-12 mb-0">{{ $analysis->recommended_action ?? '-' }}</dd>
                        </dl>
                    @else
                        <p class="text-muted mb-0">Belum ada analisis AI untuk percakapan ini.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

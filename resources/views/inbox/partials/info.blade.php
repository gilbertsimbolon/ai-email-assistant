@if ($activeConversation)
    @php
        $allAttachments = $activeConversation->messages
            ->flatMap(fn ($m) => collect($m->attachments ?? [])->map(fn ($a) => ['message' => $m, 'attachment' => $a]));

        $labels = $activeConversation->messages
            ->flatMap(fn ($m) => $m->label_ids ?? [])
            ->unique()
            ->values();

        $draftHistory = $activeConversation->drafts->sortByDesc('version');
    @endphp

    <div class="p-3 overflow-auto flex-grow-1">

        <h6 class="fw-bold border-bottom pb-2 mb-3">Info Percakapan</h6>

        <div class="mb-3">
            <label class="form-label text-muted small fw-bold mb-0">SUBJECT</label>
            <p class="mb-0">{{ $activeConversation->subject ?: '(Tanpa subjek)' }}</p>
        </div>

        <div class="mb-3">
            <label class="form-label text-muted small fw-bold mb-0">CUSTOMER</label>
            <p class="mb-0">{{ $activeConversation->contact_name ?? '-' }}</p>
            <p class="mb-0 text-muted small">{{ $activeConversation->contact_email ?? '-' }}</p>
        </div>

        <div class="mb-3">
            <label class="form-label text-muted small fw-bold mb-0">STATUS</label>
            <form action="{{ route('inbox.status.update', $activeConversation) }}" method="POST" class="mt-1">
                @csrf
                @method('PUT')
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach (\App\Enums\ConversationStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected($activeConversation->status === $status)>
                            {{ ucwords(str_replace('_', ' ', $status->value)) }}
                        </option>
                    @endforeach
                </select>
            </form>
        </div>

        @if ($labels->isNotEmpty())
            <div class="mb-3">
                <label class="form-label text-muted small fw-bold mb-0">GMAIL LABELS</label>
                <div class="d-flex flex-wrap gap-1 mt-1">
                    @foreach ($labels as $label)
                        <span class="badge bg-label-secondary">{{ $label }}</span>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="card mb-3 shadow-none border">
            <div class="card-body py-3">
                <h6 class="fw-bold text-primary mb-2"><i class="bx bx-brain me-1"></i> AI Analysis</h6>

                <div id="ai-analysis-card">
                    @include('inbox.partials.analysis-card', ['analysis' => $activeConversation->analysis])
                </div>
            </div>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-6">
                <label class="form-label text-muted small fw-bold mb-0">JUMLAH EMAIL</label>
                <p class="mb-0">{{ $activeConversation->messages->count() }}</p>
            </div>
            <div class="col-6">
                <label class="form-label text-muted small fw-bold mb-0">CHANNEL</label>
                <p class="mb-0">{{ strtoupper($activeConversation->channel->value) }}</p>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label text-muted small fw-bold mb-0">GMAIL THREAD ID</label>
            <div class="d-flex align-items-center gap-2">
                <code class="small text-truncate">{{ $activeConversation->gmail_thread_id }}</code>
                <button type="button" class="btn btn-icon btn-sm btn-outline-secondary flex-shrink-0"
                        onclick="navigator.clipboard.writeText('{{ $activeConversation->gmail_thread_id }}')" title="Salin Thread ID">
                    <i class="bx bx-copy"></i>
                </button>
            </div>
        </div>

        @if ($allAttachments->isNotEmpty())
            <div class="mb-3">
                <label class="form-label text-muted small fw-bold mb-0">ATTACHMENTS ({{ $allAttachments->count() }})</label>
                <div class="d-flex flex-column gap-1 mt-1">
                    @foreach ($allAttachments as $pair)
                        <a href="{{ route('inbox.messages.attachments.download', [$pair['message']->id, $pair['attachment']['id']]) }}"
                           class="text-decoration-none small text-truncate">
                            <i class="bx bx-paperclip me-1"></i>{{ $pair['attachment']['filename'] ?? 'attachment' }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($draftHistory->isNotEmpty())
            <div class="mb-3">
                <label class="form-label text-muted small fw-bold mb-0">RIWAYAT DRAFT</label>
                <ul class="list-unstyled small mb-0 mt-1">
                    @foreach ($draftHistory as $draft)
                        <li class="d-flex justify-content-between border-bottom py-1">
                            <span>v{{ $draft->version }}</span>
                            <span class="badge bg-label-{{ $draft->status === \App\Enums\DraftStatus::Active ? 'primary' : 'secondary' }}">
                                {{ ucfirst($draft->status->value) }}
                            </span>
                            <span class="text-muted">{{ $draft->created_at->format('d M, H:i') }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

    </div>
@else
    <div class="d-flex align-items-center justify-content-center h-100 text-muted p-4">
        <p class="mb-0 small">Pilih percakapan untuk melihat detail.</p>
    </div>
@endif

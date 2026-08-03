@php
    $defaultSubject = $activeConversation->subject ? 'Re: '.$activeConversation->subject : 'Re: percakapan Anda';
    // Channel pesan sebenarnya selalu mengikuti channel live percakapan di
    // GHL (lihat GhlSendService::send) — dropdown ini hanya menampilkan
    // channel aktif saat ini, bukan selector fungsional, supaya tidak
    // menjanjikan pengiriman ke channel lain yang belum didukung backend.
    $activeChannelLabel = ucwords(strtolower(str_replace(['TYPE_', '_'], ['', ' '], (string) $activeConversation->channel))) ?: 'Email';
@endphp
<div id="composer"
     class="composer-floating border-top bg-white p-3 flex-shrink-0 shadow-sm"
     data-conversation-id="{{ $activeConversation->id }}"
     data-generate-url="{{ route('inbox.drafts.generate', $activeConversation) }}"
     data-send-url="{{ route('inbox.drafts.send', $activeConversation) }}"
     data-draft-update-url-template="{{ route('inbox.drafts.update', ['draft' => '__ID__']) }}"
     data-draft-id="{{ $activeDraft->id ?? '' }}">

    <input type="text" id="composer-subject" class="form-control form-control-sm mb-2"
           placeholder="Subjek..." value="{{ $activeDraft->content['subject'] ?? $defaultSubject }}">

    <div class="composer-input-shell border rounded-3 p-2">
        <textarea id="composer-body" class="form-control composer-textarea border-0 shadow-none p-1" rows="4"
                  placeholder="Tulis balasan, atau klik Generate Reply untuk membuat draft AI...">{{ $activeDraft->content['body'] ?? '' }}</textarea>

        <div class="d-flex align-items-center justify-content-between mt-1">
            <div class="dropdown">
                <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"
                        data-bs-placement="top" title="Channel pesan mengikuti channel percakapan aktif">
                    <i class="bx bx-chat me-1"></i>{{ $activeChannelLabel }}
                </button>
                <ul class="dropdown-menu">
                    <li><span class="dropdown-item-text text-muted small">Channel mengikuti percakapan aktif ({{ $activeChannelLabel }})</span></li>
                </ul>
            </div>

            <button type="button" id="btn-send" class="btn btn-icon btn-primary composer-send-btn" title="Send">
                <i class="bx bx-paper-plane"></i>
            </button>
        </div>
    </div>

    <div class="d-flex align-items-center flex-wrap gap-2 mt-2">
        <button type="button" class="btn btn-sm btn-outline-secondary" disabled data-bs-toggle="tooltip" data-bs-placement="top" title="Segera hadir">
            <i class="bx bx-edit-alt me-1"></i>Improve
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary" disabled data-bs-toggle="tooltip" data-bs-placement="top" title="Segera hadir">
            <i class="bx bx-paperclip me-1"></i>Attachment
        </button>

        <button type="button" id="btn-clear" class="btn btn-sm btn-outline-secondary">
            <i class="bx bx-eraser me-1"></i> Clear
        </button>
        <button type="button" id="btn-copy" class="btn btn-sm btn-outline-secondary">
            <i class="bx bx-copy me-1"></i> Copy
        </button>

        <span id="ai-thinking" class="text-muted small ms-1 d-none">
            AI is thinking<span class="ai-thinking-dots"></span>
        </span>

        <span id="draft-save-indicator" class="text-muted small ms-auto"></span>
    </div>

    <div class="form-text">Ctrl+Enter: Send &middot; Ctrl+Shift+G: Generate/Regenerate &middot; Esc: Batal</div>
</div>

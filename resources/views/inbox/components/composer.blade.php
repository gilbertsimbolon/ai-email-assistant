@php
    $defaultSubject = $activeConversation->subject ? 'Re: '.$activeConversation->subject : 'Re: percakapan Anda';
@endphp
<div id="composer"
     class="border-top bg-white p-3 flex-shrink-0 shadow-sm"
     data-conversation-id="{{ $activeConversation->id }}"
     data-generate-url="{{ route('inbox.drafts.generate', $activeConversation) }}"
     data-send-url="{{ route('inbox.drafts.send', $activeConversation) }}"
     data-draft-update-url-template="{{ route('inbox.drafts.update', ['draft' => '__ID__']) }}"
     data-draft-id="{{ $activeDraft->id ?? '' }}">

    <input type="text" id="composer-subject" class="form-control form-control-sm mb-2"
           placeholder="Subjek..." value="{{ $activeDraft->content['subject'] ?? $defaultSubject }}">

    <textarea id="composer-body" class="form-control composer-textarea" rows="6"
              placeholder="Tulis balasan, atau klik Generate Reply untuk membuat draft AI...">{{ $activeDraft->content['body'] ?? '' }}</textarea>

    <div class="d-flex align-items-center flex-wrap gap-2 mt-2">
        <button type="button" id="btn-generate" class="btn btn-sm btn-primary {{ $activeDraft ? 'd-none' : '' }}">
            <i class="bx bx-magic-wand me-1"></i> Generate AI Reply
        </button>
        <button type="button" id="btn-regenerate" class="btn btn-sm btn-outline-primary {{ $activeDraft ? '' : 'd-none' }}">
            <i class="bx bx-refresh me-1"></i> Regenerate
        </button>

        <div class="vr mx-1 d-none d-sm-block"></div>

        <button type="button" class="btn btn-sm btn-outline-secondary" disabled data-bs-toggle="tooltip" data-bs-placement="top" title="Segera hadir">
            <i class="bx bx-edit-alt me-1"></i>Improve
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary" disabled data-bs-toggle="tooltip" data-bs-placement="top" title="Segera hadir">
            <i class="bx bx-globe me-1"></i>Translate
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary" disabled data-bs-toggle="tooltip" data-bs-placement="top" title="Segera hadir">
            <i class="bx bx-collapse-vertical me-1"></i>Summarize
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

        <div class="ms-auto d-flex align-items-center gap-2">
            <span id="draft-save-indicator" class="text-muted small"></span>
            <button type="button" id="btn-send" class="btn btn-sm btn-success">
                <i class="bx bx-send me-1"></i> Send
            </button>
        </div>
    </div>

    <div class="form-text">Ctrl+Enter: Send &middot; Ctrl+Shift+G: Generate/Regenerate &middot; Esc: Batal</div>
</div>

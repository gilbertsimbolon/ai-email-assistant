@php
    $defaultSubject = $activeConversation->subject ? 'Re: '.$activeConversation->subject : 'Re: percakapan Anda';
@endphp
<div id="composer"
     class="border-top bg-white p-3 flex-shrink-0"
     data-conversation-id="{{ $activeConversation->id }}"
     data-generate-url="{{ route('inbox.drafts.generate', $activeConversation) }}"
     data-send-url="{{ route('inbox.drafts.send', $activeConversation) }}"
     data-draft-update-url-template="{{ route('inbox.drafts.update', ['draft' => '__ID__']) }}"
     data-draft-id="{{ $activeDraft->id ?? '' }}">

    <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
        <button type="button" id="btn-generate" class="btn btn-sm btn-primary {{ $activeDraft ? 'd-none' : '' }}">
            ✨ Generate Reply
        </button>
        <button type="button" id="btn-regenerate" class="btn btn-sm btn-outline-primary {{ $activeDraft ? '' : 'd-none' }}">
            🔄 Regenerate
        </button>
        <button type="button" id="btn-clear" class="btn btn-sm btn-outline-secondary">
            🧹 Clear
        </button>
        <button type="button" id="btn-copy" class="btn btn-sm btn-outline-secondary">
            📋 Copy
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

    <input type="text" id="composer-subject" class="form-control form-control-sm mb-2"
           placeholder="Subjek..." value="{{ $activeDraft->content['subject'] ?? $defaultSubject }}">

    <textarea id="composer-body" class="form-control" rows="4"
              placeholder="Tulis balasan, atau klik Generate Reply untuk membuat draft AI...">{{ $activeDraft->content['body'] ?? '' }}</textarea>

    <div class="form-text">Ctrl+Enter: Send &middot; Ctrl+Shift+G: Generate/Regenerate &middot; Esc: Batal</div>
</div>

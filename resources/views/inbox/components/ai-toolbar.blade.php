{{-- AI toolbar above the composer (claude.txt Task 3). Every button here is
     a manual, user-initiated action — nothing runs automatically. Generate/
     Regenerate physically live here now (moved from composer.blade.php);
     inbox-composer.js still finds them by id regardless of DOM location. --}}
<div id="ai-toolbar"
     class="border-bottom bg-white px-4 py-2 d-flex flex-wrap align-items-center gap-2 flex-shrink-0"
     data-conversation-id="{{ $activeConversation->id }}"
     data-summarize-url="{{ route('inbox.ai-tools.summarize', $activeConversation) }}"
     data-translate-url="{{ route('inbox.ai-tools.translate', $activeConversation) }}"
     data-detect-intent-url="{{ route('inbox.ai-tools.detect-intent', $activeConversation) }}"
     data-extract-info-url="{{ route('inbox.ai-tools.extract-info', $activeConversation) }}"
     data-sentiment-url="{{ route('inbox.ai-tools.sentiment', $activeConversation) }}">

    <button type="button" id="btn-generate" class="btn btn-sm btn-primary {{ $activeDraft ? 'd-none' : '' }}">
        <i class="bx bx-magic-wand me-1"></i> Generate AI Reply
    </button>
    <button type="button" id="btn-regenerate" class="btn btn-sm btn-outline-primary {{ $activeDraft ? '' : 'd-none' }}">
        <i class="bx bx-refresh me-1"></i> Regenerate
    </button>

    <div class="vr mx-1 d-none d-sm-block"></div>

    <button type="button" class="btn btn-sm btn-outline-secondary ai-tool-trigger" data-tool="summarize">
        <i class="bx bx-collapse-vertical me-1"></i> Summarize Thread
    </button>

    <div class="dropdown">
        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bx bx-globe me-1"></i> Translate
        </button>
        <ul class="dropdown-menu">
            <li><a class="dropdown-item ai-tool-trigger" href="javascript:void(0);" data-tool="translate" data-language="en">English</a></li>
            <li><a class="dropdown-item ai-tool-trigger" href="javascript:void(0);" data-tool="translate" data-language="id">Indonesia</a></li>
            <li><a class="dropdown-item ai-tool-trigger" href="javascript:void(0);" data-tool="translate" data-language="ja">Japanese</a></li>
            <li><a class="dropdown-item ai-tool-trigger" href="javascript:void(0);" data-tool="translate" data-language="zh">Chinese</a></li>
            <li><a class="dropdown-item ai-tool-trigger" href="javascript:void(0);" data-tool="translate" data-language="es">Spanish</a></li>
            <li><a class="dropdown-item ai-tool-trigger" href="javascript:void(0);" data-tool="translate" data-language="fr">French</a></li>
            <li><a class="dropdown-item ai-tool-trigger" href="javascript:void(0);" data-tool="translate" data-language="de">German</a></li>
        </ul>
    </div>

    <button type="button" class="btn btn-sm btn-outline-secondary ai-tool-trigger" data-tool="detect-intent">
        <i class="bx bx-target-lock me-1"></i> Detect Intent
    </button>
    <button type="button" class="btn btn-sm btn-outline-secondary ai-tool-trigger" data-tool="extract-info">
        <i class="bx bx-id-card me-1"></i> Extract Info
    </button>
    <button type="button" class="btn btn-sm btn-outline-secondary ai-tool-trigger" data-tool="sentiment">
        <i class="bx bx-happy-heart-eyes me-1"></i> Sentiment
    </button>
</div>

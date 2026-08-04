{{-- One shared modal shape for all 5 AI toolbar tools (claude.txt Task 3);
     inbox-toolbar.js fills #ai-tool-result-{key} per tool's shape. Kept as
     a single loop instead of 5 near-identical partials per claude.txt's
     "refactor duplicated code" instruction. --}}
@php
    $aiToolModals = [
        'summarize' => ['title' => 'Summarize Thread', 'icon' => 'bx-collapse-vertical'],
        'translate' => ['title' => 'Translate', 'icon' => 'bx-globe'],
        'detect-intent' => ['title' => 'Detect Intent', 'icon' => 'bx-target-lock'],
        'extract-info' => ['title' => 'Extract Customer Information', 'icon' => 'bx-id-card'],
        'sentiment' => ['title' => 'Sentiment Analysis', 'icon' => 'bx-happy-heart-eyes'],
    ];
@endphp

@foreach ($aiToolModals as $key => $modal)
    <div class="modal fade ai-tool-modal" id="modal-{{ $key }}" data-tool="{{ $key }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bx {{ $modal['icon'] }} me-1 text-primary"></i> {{ $modal['title'] }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="ai-tool-loading text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="text-muted small mt-2 mb-0">{{ $key === 'extract-info' ? 'Fetching from GHL' : 'AI is thinking' }}<span class="ai-tool-loading-dots"></span></p>
                    </div>
                    <div class="ai-tool-error alert alert-danger d-none"></div>
                    <div class="ai-tool-result d-none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-outline-primary ai-tool-regenerate d-none">
                        <i class="bx bx-refresh me-1"></i> Regenerate
                    </button>
                    <button type="button" class="btn btn-primary ai-tool-copy d-none">
                        <i class="bx bx-copy me-1"></i> Copy Result
                    </button>
                </div>
            </div>
        </div>
    </div>
@endforeach

(function () {
    'use strict';

    // Same "re-query on every AJAX swap" convention as inbox-composer.js —
    // the toolbar/modals DOM is replaced wholesale with the rest of
    // conversation-thread.blade.php on every conversation switch.
    var TOOL_URL_KEYS = {
        'summarize': 'summarizeUrl',
        'translate': 'translateUrl',
        'detect-intent': 'detectIntentUrl',
        'extract-info': 'extractInfoUrl',
        'sentiment': 'sentimentUrl',
    };

    var LANGUAGE_LABELS = {
        en: 'English',
        id: 'Indonesia',
        ja: 'Japanese',
        zh: 'Chinese',
        es: 'Spanish',
        fr: 'French',
        de: 'German',
    };

    var lastRequest = {};
    var toolbar = null;

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]').content;
    }

    function urlForTool(tool) {
        var key = TOOL_URL_KEYS[tool];
        return toolbar && key ? toolbar.dataset[key] : null;
    }

    function modalFor(tool) {
        return document.getElementById('modal-' + tool);
    }

    function setModalState(modalEl, state, errorMessage) {
        var loading = modalEl.querySelector('.ai-tool-loading');
        var result = modalEl.querySelector('.ai-tool-result');
        var error = modalEl.querySelector('.ai-tool-error');
        var copyBtn = modalEl.querySelector('.ai-tool-copy');
        var regenerateBtn = modalEl.querySelector('.ai-tool-regenerate');

        loading.classList.toggle('d-none', state !== 'loading');
        error.classList.toggle('d-none', state !== 'error');
        result.classList.toggle('d-none', state !== 'result');
        copyBtn.classList.toggle('d-none', state !== 'result');
        regenerateBtn.classList.toggle('d-none', state !== 'result');

        if (state === 'error') {
            error.textContent = errorMessage || 'Gagal memproses permintaan AI.';
        }
    }

    function fieldRow(label, value) {
        var wrap = document.createElement('div');
        wrap.className = 'mb-3';

        var labelEl = document.createElement('label');
        labelEl.className = 'form-label text-muted small fw-bold mb-0 text-uppercase';
        labelEl.textContent = label;

        var valueEl = document.createElement('p');
        valueEl.className = 'mb-0';
        valueEl.textContent = (value === null || value === undefined || value === '') ? '-' : value;

        wrap.appendChild(labelEl);
        wrap.appendChild(valueEl);

        return wrap;
    }

    function renderFields(container, fields) {
        container.innerHTML = '';
        fields.forEach(function (field) {
            container.appendChild(fieldRow(field[0], field[1]));
        });
    }

    function renderSummarize(container, data) {
        renderFields(container, [
            ['Conversation Summary', data.conversation_summary],
            ['Customer Problem', data.customer_problem],
            ['Current Status', data.current_status],
            ['Pending Questions', data.pending_questions],
            ['Suggested Action', data.suggested_action],
            ['Risk Level', data.risk_level],
            ['Timeline', data.timeline],
            ['Important Notes', data.important_notes],
            ['Estimated Intent', data.estimated_intent],
            ['Recommended Reply', data.recommended_reply],
        ]);
    }

    function renderTranslate(container, data, language) {
        container.innerHTML = '';

        var label = document.createElement('label');
        label.className = 'form-label text-muted small fw-bold mb-2 text-uppercase';
        label.textContent = 'Translated to ' + (LANGUAGE_LABELS[language] || language || '');

        var body = document.createElement('p');
        body.className = 'mb-0';
        body.style.whiteSpace = 'pre-wrap';
        body.textContent = data.translated_text || '-';

        container.appendChild(label);
        container.appendChild(body);
    }

    function renderDetectIntent(container, data) {
        renderFields(container, [
            ['Intent', data.intent],
            ['Confidence Score', data.confidence_score],
            ['Matched SOP', data.matched_sop],
            ['Matched Knowledge', Array.isArray(data.matched_knowledge) ? data.matched_knowledge.join(', ') : data.matched_knowledge],
            ['Matched Template', data.matched_template],
            ['Reasoning', data.reasoning],
        ]);
    }

    function renderExtractInfo(container, data) {
        renderFields(container, [
            ['Customer Name', data.customer_name],
            ['Email', data.email],
            ['Order Number', data.order_number],
            ['Invoice Number', data.invoice_number],
            ['Subscription', data.subscription],
            ['Product', data.product],
            ['Platform', data.platform],
            ['Purchase Date', data.purchase_date],
            ['Refund Eligibility', data.refund_eligibility],
            ['Important Dates', data.important_dates],
        ]);
    }

    function renderSentiment(container, data) {
        renderFields(container, [
            ['Emotion', data.emotion],
            ['Frustration Level', data.frustration_level],
            ['Urgency', data.urgency],
            ['Customer Satisfaction', data.customer_satisfaction],
            ['Risk Score', data.risk_score],
            ['Priority', data.priority],
        ]);
    }

    var RENDERERS = {
        'summarize': renderSummarize,
        'translate': renderTranslate,
        'detect-intent': renderDetectIntent,
        'extract-info': renderExtractInfo,
        'sentiment': renderSentiment,
    };

    function runTool(tool, options) {
        options = options || {};

        var modalEl = modalFor(tool);
        var url = urlForTool(tool);

        if (!modalEl || !url) {
            return;
        }

        var language = options.language || (lastRequest[tool] && lastRequest[tool].language);
        lastRequest[tool] = { language: language };

        bootstrap.Modal.getOrCreateInstance(modalEl).show();
        setModalState(modalEl, 'loading');

        var body = { force_refresh: !!options.forceRefresh };
        if (tool === 'translate') {
            body.language = language;
        }

        fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(body),
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok) {
                        throw new Error(data.message || 'Gagal memproses permintaan AI.');
                    }
                    return data;
                });
            })
            .then(function (data) {
                var renderer = RENDERERS[tool];
                if (renderer) {
                    renderer(modalEl.querySelector('.ai-tool-result'), data.result, language);
                }
                setModalState(modalEl, 'result');
            })
            .catch(function (error) {
                setModalState(modalEl, 'error', error.message);
            });
    }

    function threadText() {
        var chatHistory = document.getElementById('chatHistory');
        return chatHistory ? chatHistory.innerText.trim() : '';
    }

    function copyEmail() {
        navigator.clipboard.writeText(threadText()).then(function () {
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Email disalin', timer: 1200, showConfirmButton: false });
        });
    }

    function downloadEmail() {
        var blob = new Blob([threadText()], { type: 'text/plain' });
        var url = URL.createObjectURL(blob);

        var link = document.createElement('a');
        link.href = url;
        link.download = 'email-thread-' + (toolbar ? toolbar.dataset.conversationId : 'export') + '.txt';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        URL.revokeObjectURL(url);
    }

    function printEmail() {
        window.print();
    }

    /**
     * (Re)binds the AI toolbar/modals for whatever conversation is
     * currently in the DOM. Called once on initial page load, and again by
     * inbox-navigation.js every time it swaps in a new thread via AJAX —
     * same pattern as inbox-composer.js's initComposer().
     */
    function initAiToolbar() {
        toolbar = document.getElementById('ai-toolbar');
        if (!toolbar) {
            return;
        }

        document.querySelectorAll('.ai-tool-trigger').forEach(function (el) {
            el.addEventListener('click', function () {
                runTool(el.dataset.tool, { language: el.dataset.language, forceRefresh: false });
            });
        });

        document.querySelectorAll('.ai-tool-modal').forEach(function (modalEl) {
            var tool = modalEl.dataset.tool;

            var copyBtn = modalEl.querySelector('.ai-tool-copy');
            if (copyBtn) {
                copyBtn.addEventListener('click', function () {
                    var text = modalEl.querySelector('.ai-tool-result').innerText;
                    navigator.clipboard.writeText(text).then(function () {
                        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Disalin', timer: 1200, showConfirmButton: false });
                    });
                });
            }

            var regenerateBtn = modalEl.querySelector('.ai-tool-regenerate');
            if (regenerateBtn) {
                regenerateBtn.addEventListener('click', function () {
                    var language = lastRequest[tool] ? lastRequest[tool].language : undefined;
                    runTool(tool, { language: language, forceRefresh: true });
                });
            }
        });

        var btnCopyEmail = document.getElementById('btn-copy-email');
        if (btnCopyEmail) {
            btnCopyEmail.addEventListener('click', copyEmail);
        }

        var btnDownloadEmail = document.getElementById('btn-download-email');
        if (btnDownloadEmail) {
            btnDownloadEmail.addEventListener('click', downloadEmail);
        }

        var btnPrintEmail = document.getElementById('btn-print-email');
        if (btnPrintEmail) {
            btnPrintEmail.addEventListener('click', printEmail);
        }
    }

    window.initAiToolbar = initAiToolbar;
    document.addEventListener('DOMContentLoaded', initAiToolbar);
})();

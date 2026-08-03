(function () {
    'use strict';

    // Re-queried every time initComposer() runs, because the composer DOM is
    // replaced wholesale on every AJAX conversation swap (see
    // inbox-navigation.js) — old element references would point at nodes no
    // longer in the document.
    let composer, subjectEl, bodyEl, btnGenerate, btnRegenerate, btnClear, btnCopy, btnSend;
    let thinkingEl, thinkingDotsEl, saveIndicatorEl;
    let replyPreviewEl, replySenderEl, replySnippetEl;
    let draftUpdateUrlTemplate, generateUrl, sendUrl;

    let autosaveTimer = null;
    let dotsTimer = null;
    let generateAbortController = null;
    let replyTarget = null;

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]').content;
    }

    function draftUpdateUrl() {
        return draftUpdateUrlTemplate.replace('__ID__', composer.dataset.draftId);
    }

    function setThinking(isThinking) {
        subjectEl.disabled = isThinking;
        bodyEl.disabled = isThinking;
        btnGenerate.disabled = isThinking;
        btnRegenerate.disabled = isThinking;
        btnSend.disabled = isThinking;

        if (thinkingEl) {
            thinkingEl.classList.toggle('d-none', !isThinking);
        }

        if (dotsTimer) {
            clearInterval(dotsTimer);
            dotsTimer = null;
        }

        if (isThinking && thinkingDotsEl) {
            let dots = 0;
            dotsTimer = setInterval(function () {
                dots = (dots + 1) % 4;
                thinkingDotsEl.textContent = '.'.repeat(dots);
            }, 400);
        }
    }

    function typewriterReveal(el, fullText) {
        const tokens = fullText.split(/(\s+)/);
        const perTick = tokens.length > 300 ? Math.ceil(tokens.length / 300) : 1;
        el.value = '';
        let i = 0;

        return new Promise(function (resolve) {
            const tick = setInterval(function () {
                el.value += tokens.slice(i, i + perTick).join('');
                i += perTick;
                el.scrollTop = el.scrollHeight;

                if (i >= tokens.length) {
                    clearInterval(tick);
                    el.value = fullText;
                    resolve();
                }
            }, 20);
        });
    }

    function saveDraftNow() {
        if (!composer || !composer.dataset.draftId) {
            return Promise.resolve();
        }

        if (saveIndicatorEl) {
            saveIndicatorEl.textContent = 'Menyimpan...';
        }

        return fetch(draftUpdateUrl(), {
            method: 'PUT',
            keepalive: true,
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                subject: subjectEl.value,
                body: bodyEl.value,
            }),
        })
            .then(function (response) {
                if (saveIndicatorEl) {
                    saveIndicatorEl.textContent = response.ok ? 'Draft tersimpan' : '';
                }
            })
            .catch(function () {
                if (saveIndicatorEl) {
                    saveIndicatorEl.textContent = '';
                }
            });
    }

    /**
     * Message-specific Reply (claude.txt task 1): clicking the reply icon on
     * a bubble (message-bubble.blade.php `.js-msg-reply`) calls this to show
     * a quoted reference above the composer, similar to GHL. Cleared on
     * cancel, on Escape, or whenever a fresh conversation is mounted.
     */
    function setReplyTarget(target) {
        replyTarget = target;

        if (!replyPreviewEl) {
            return;
        }

        if (replySenderEl) {
            replySenderEl.textContent = target.sender;
        }
        if (replySnippetEl) {
            replySnippetEl.textContent = target.snippet;
        }

        replyPreviewEl.classList.remove('d-none');
        replyPreviewEl.classList.add('d-flex');
    }

    function clearReplyTarget() {
        replyTarget = null;

        if (!replyPreviewEl) {
            return;
        }

        replyPreviewEl.classList.add('d-none');
        replyPreviewEl.classList.remove('d-flex');
    }

    function scheduleAutosave() {
        if (autosaveTimer) {
            clearTimeout(autosaveTimer);
        }
        autosaveTimer = setTimeout(saveDraftNow, 2500);
    }

    function renderAnalysisCard(analysis) {
        const container = document.getElementById('ai-analysis-card');
        if (!container) {
            return;
        }
        container.innerHTML = '';

        if (!analysis) {
            const p = document.createElement('p');
            p.className = 'text-muted small mb-0';
            p.textContent = 'Belum ada data analisis AI.';
            container.appendChild(p);
            return;
        }

        const sentiment = (analysis.sentiment || 'neutral').toLowerCase();
        const badgeColor = sentiment === 'positive' ? 'success' : (sentiment === 'negative' ? 'danger' : 'warning');

        const row = document.createElement('div');
        row.className = 'row g-2';
        row.appendChild(analysisColumn('CUSTOMER INTENT', analysis.customer_intent || '-', 'col-sm-6'));

        const sentimentCol = document.createElement('div');
        sentimentCol.className = 'col-sm-6';
        const label = document.createElement('label');
        label.className = 'form-label text-muted small fw-bold mb-0';
        label.textContent = 'SENTIMEN';
        const badge = document.createElement('span');
        badge.className = 'badge bg-' + badgeColor;
        badge.textContent = sentiment.charAt(0).toUpperCase() + sentiment.slice(1);
        const badgeWrap = document.createElement('div');
        badgeWrap.appendChild(badge);
        sentimentCol.appendChild(label);
        sentimentCol.appendChild(badgeWrap);
        row.appendChild(sentimentCol);

        row.appendChild(analysisColumn('RINGKASAN', analysis.summary || 'Tidak ada ringkasan.', 'col-12', true));

        container.appendChild(row);
    }

    function analysisColumn(labelText, value, colClass, isSecondary) {
        const col = document.createElement('div');
        col.className = colClass;
        const label = document.createElement('label');
        label.className = 'form-label text-muted small fw-bold mb-0';
        label.textContent = labelText;
        const p = document.createElement('p');
        p.className = isSecondary ? 'text-secondary small mb-0' : 'fw-semibold mb-0 text-dark';
        p.textContent = value;
        col.appendChild(label);
        col.appendChild(p);
        return col;
    }

    function generateDraft(asNewVersion) {
        generateAbortController = new AbortController();
        setThinking(true);

        return fetch(generateUrl, {
            method: 'POST',
            signal: generateAbortController.signal,
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ as_new_version: asNewVersion }),
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok) {
                        throw new Error(data.message || 'Gagal membuat draf AI.');
                    }
                    return data;
                });
            })
            .then(function (data) {
                composer.dataset.draftId = data.draft.id;
                subjectEl.value = data.draft.subject;
                btnGenerate.classList.add('d-none');
                btnRegenerate.classList.remove('d-none');
                renderAnalysisCard(data.analysis);

                return typewriterReveal(bodyEl, data.draft.body);
            })
            .catch(function (error) {
                if (error.name !== 'AbortError') {
                    Swal.fire({ icon: 'error', title: 'Gagal Generate', text: error.message });
                }
            })
            .finally(function () {
                setThinking(false);
                generateAbortController = null;
            });
    }

    function handleGenerateClick() {
        if (!composer.dataset.draftId) {
            generateDraft(false);
            return;
        }

        Swal.fire({
            title: 'Draft sudah ada',
            text: 'Ganti draft yang ada, atau buat versi baru?',
            showDenyButton: true,
            showCancelButton: true,
            confirmButtonText: 'Buat Versi Baru',
            denyButtonText: 'Ganti Draft Ini',
            cancelButtonText: 'Batal',
        }).then(function (result) {
            if (result.isConfirmed) {
                generateDraft(true);
            } else if (result.isDenied) {
                generateDraft(false);
            }
        });
    }

    function handleSendClick() {
        if (autosaveTimer) {
            clearTimeout(autosaveTimer);
        }

        setThinking(true);

        const payload = {
            subject: subjectEl.value,
            body: bodyEl.value,
        };

        if (replyTarget) {
            payload.reply_to_sender = replyTarget.sender;
            payload.reply_to_snippet = replyTarget.snippet;
        }

        fetch(sendUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload),
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok) {
                        throw new Error(data.message || 'Gagal mengirim balasan.');
                    }
                    return data;
                });
            })
            .then(function () {
                Swal.fire({ icon: 'success', title: 'Terkirim', timer: 1500, showConfirmButton: false })
                    .then(function () {
                        window.location.reload();
                    });
            })
            .catch(function (error) {
                setThinking(false);
                Swal.fire({ icon: 'error', title: 'Gagal Mengirim', text: error.message });
            });
    }

    function initLazyThumbs() {
        const lazyThumbs = document.querySelectorAll('.lazy-thumb[data-src]');
        if (lazyThumbs.length && 'IntersectionObserver' in window) {
            const observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                        observer.unobserve(img);
                    }
                });
            });
            lazyThumbs.forEach(function (img) {
                observer.observe(img);
            });
        } else {
            lazyThumbs.forEach(function (img) {
                img.src = img.dataset.src;
            });
        }
    }

    /**
     * (Re)binds the composer for whatever conversation is currently in the
     * DOM. Called once on initial page load, and again by
     * inbox-navigation.js every time it swaps in a new thread via AJAX.
     */
    function initComposer() {
        composer = document.getElementById('composer');
        if (!composer) {
            return;
        }

        subjectEl = document.getElementById('composer-subject');
        bodyEl = document.getElementById('composer-body');
        btnGenerate = document.getElementById('btn-generate');
        btnRegenerate = document.getElementById('btn-regenerate');
        btnClear = document.getElementById('btn-clear');
        btnCopy = document.getElementById('btn-copy');
        btnSend = document.getElementById('btn-send');
        thinkingEl = document.getElementById('ai-thinking');
        thinkingDotsEl = thinkingEl ? thinkingEl.querySelector('.ai-thinking-dots') : null;
        saveIndicatorEl = document.getElementById('draft-save-indicator');
        replyPreviewEl = document.getElementById('composer-reply-preview');
        replySenderEl = document.getElementById('composer-reply-sender');
        replySnippetEl = document.getElementById('composer-reply-snippet');

        draftUpdateUrlTemplate = composer.dataset.draftUpdateUrlTemplate;
        generateUrl = composer.dataset.generateUrl;
        sendUrl = composer.dataset.sendUrl;

        autosaveTimer = null;
        generateAbortController = null;
        // Reply state never carries over from a previous conversation —
        // each thread swap replaces the composer/preview DOM wholesale.
        replyTarget = null;

        btnGenerate.addEventListener('click', handleGenerateClick);
        btnRegenerate.addEventListener('click', handleGenerateClick);

        btnClear.addEventListener('click', function () {
            bodyEl.value = '';
            bodyEl.focus();
            scheduleAutosave();
        });

        btnCopy.addEventListener('click', function () {
            navigator.clipboard.writeText(bodyEl.value).then(function () {
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Disalin', timer: 1200, showConfirmButton: false });
            });
        });

        btnSend.addEventListener('click', handleSendClick);

        subjectEl.addEventListener('input', scheduleAutosave);
        bodyEl.addEventListener('input', scheduleAutosave);

        bodyEl.addEventListener('keydown', function (event) {
            const isCtrlOrCmd = event.ctrlKey || event.metaKey;

            if (isCtrlOrCmd && event.key === 'Enter') {
                event.preventDefault();
                if (!btnSend.disabled) {
                    btnSend.click();
                }
            } else if (isCtrlOrCmd && event.shiftKey && (event.key === 'G' || event.key === 'g')) {
                event.preventDefault();
                (composer.dataset.draftId ? btnRegenerate : btnGenerate).click();
            } else if (event.key === 'Escape' && generateAbortController) {
                generateAbortController.abort();
            } else if (event.key === 'Escape' && replyTarget) {
                clearReplyTarget();
            }
        });

        initLazyThumbs();

        const chatHistory = document.getElementById('chatHistory');
        if (chatHistory) {
            chatHistory.scrollTop = chatHistory.scrollHeight;
        }
    }

    // The composer element itself gets replaced on every AJAX swap, so a
    // single beforeunload listener registered once here (reading whatever
    // `composer`/`autosaveTimer` currently point at) is enough — no need to
    // re-register it from initComposer, which would stack up duplicates.
    window.addEventListener('beforeunload', function () {
        if (autosaveTimer && composer && composer.dataset.draftId) {
            saveDraftNow();
        }
    });

    // Message bubbles (chatHistory) and the composer's cancel button are
    // both replaced wholesale on every AJAX thread swap, so this is
    // delegated on document once — same convention as the reply/AI-panel
    // buttons in inbox-navigation.js — instead of re-bound from initComposer.
    document.addEventListener('click', function (event) {
        const replyBtn = event.target.closest('.js-msg-reply');
        if (replyBtn) {
            setReplyTarget({
                sender: replyBtn.dataset.sender || 'Pelanggan',
                snippet: replyBtn.dataset.snippet || '',
            });
            if (bodyEl) {
                bodyEl.focus();
            }
            return;
        }

        if (event.target.closest('#btn-cancel-reply')) {
            clearReplyTarget();
        }
    });

    window.initComposer = initComposer;
    document.addEventListener('DOMContentLoaded', initComposer);
})();

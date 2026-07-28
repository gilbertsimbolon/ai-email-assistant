(function () {
    'use strict';

    const composer = document.getElementById('composer');
    if (!composer) {
        return;
    }

    const subjectEl = document.getElementById('composer-subject');
    const bodyEl = document.getElementById('composer-body');
    const btnGenerate = document.getElementById('btn-generate');
    const btnRegenerate = document.getElementById('btn-regenerate');
    const btnClear = document.getElementById('btn-clear');
    const btnCopy = document.getElementById('btn-copy');
    const btnSend = document.getElementById('btn-send');
    const thinkingEl = document.getElementById('ai-thinking');
    const thinkingDotsEl = thinkingEl ? thinkingEl.querySelector('.ai-thinking-dots') : null;
    const saveIndicatorEl = document.getElementById('draft-save-indicator');

    const draftUpdateUrlTemplate = composer.dataset.draftUpdateUrlTemplate;
    const generateUrl = composer.dataset.generateUrl;
    const sendUrl = composer.dataset.sendUrl;

    let autosaveTimer = null;
    let dotsTimer = null;
    let generateAbortController = null;

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
        if (!composer.dataset.draftId) {
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

    btnSend.addEventListener('click', function () {
        if (autosaveTimer) {
            clearTimeout(autosaveTimer);
        }

        setThinking(true);

        fetch(sendUrl, {
            method: 'POST',
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
    });

    subjectEl.addEventListener('input', scheduleAutosave);
    bodyEl.addEventListener('input', scheduleAutosave);

    window.addEventListener('beforeunload', function () {
        if (autosaveTimer && composer.dataset.draftId) {
            saveDraftNow();
        }
    });

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
        }
    });

    // Lazy-load image attachment thumbnails only once they scroll into view.
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

    // Scroll chat history to the latest message on load.
    const chatHistory = document.getElementById('chatHistory');
    if (chatHistory) {
        chatHistory.scrollTop = chatHistory.scrollHeight;
    }
})();

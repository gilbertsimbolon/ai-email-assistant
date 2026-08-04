(function () {
    'use strict';

    const threadPanel = document.getElementById('threadPanel');
    const aiPanelBody = document.getElementById('aiPanelBody');
    const conversationList = document.getElementById('conversationList');
    const aiPanelWrapper = document.getElementById('infoOffcanvas');
    const inboxApp = document.getElementById('inboxApp');

    const AI_PANEL_COLLAPSE_KEY = 'inbox:ai-panel-collapsed';

    function starUrl(id) {
        return inboxApp.dataset.starUrlTemplate.replace('__ID__', id);
    }

    function readUrl(id) {
        return inboxApp.dataset.readUrlTemplate.replace('__ID__', id);
    }

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]').content;
    }

    function skeletonThreadHtml() {
        return '<div class="email-thread d-flex flex-column h-100 skeleton-thread">'
            + '<div class="border-bottom bg-white flex-shrink-0 px-4 py-3">'
            + '<div class="skeleton-line skeleton-line-title mb-2"></div>'
            + '<div class="skeleton-line skeleton-line-sm"></div>'
            + '</div>'
            + '<div class="chat-history flex-grow-1 overflow-hidden p-4">'
            + '<div class="d-flex mb-3 justify-content-start"><div class="skeleton-bubble" style="width: 55%;"></div></div>'
            + '<div class="d-flex mb-3 justify-content-end"><div class="skeleton-bubble" style="width: 45%;"></div></div>'
            + '<div class="d-flex mb-3 justify-content-start"><div class="skeleton-bubble" style="width: 65%;"></div></div>'
            + '</div></div>';
    }

    function initTooltips(scope) {
        const triggers = (scope || document).querySelectorAll('[data-bs-toggle="tooltip"]');
        triggers.forEach(function (el) {
            if (window.bootstrap && !window.bootstrap.Tooltip.getInstance(el)) {
                new window.bootstrap.Tooltip(el);
            }
        });
    }

    function markActiveInList(conversationId) {
        if (!conversationList) {
            return;
        }

        conversationList.querySelectorAll('.conversation-item').forEach(function (li) {
            li.classList.remove('bg-label-primary');
            li.classList.add('bg-white');
        });

        const activeLi = conversationList.querySelector('.conversation-item[data-conversation-id="' + conversationId + '"]');
        if (!activeLi) {
            return;
        }

        activeLi.classList.remove('bg-white');
        activeLi.classList.add('bg-label-primary');
    }

    function loadConversation(url, options) {
        options = options || {};

        if (threadPanel) {
            threadPanel.innerHTML = skeletonThreadHtml();
        }

        return fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Gagal memuat percakapan.');
                }
                return response.json();
            })
            .then(function (data) {
                if (threadPanel) {
                    threadPanel.innerHTML = data.thread_html;
                }
                if (aiPanelBody) {
                    aiPanelBody.innerHTML = data.ai_panel_html;
                }

                if (inboxApp) {
                    inboxApp.dataset.activeConversationId = data.conversation_id || '';
                }

                if (data.conversation_id) {
                    markActiveInList(data.conversation_id);
                    // Opening a conversation must NOT mark it read
                    // (claude.txt Task 3) — reflect whatever is_read the
                    // server actually reports, never assume true just
                    // because it was opened.
                    setListItemReadState(data.conversation_id, !!data.is_read);
                }

                if (window.initComposer) {
                    window.initComposer();
                }
                if (window.initAiToolbar) {
                    window.initAiToolbar();
                }
                initTooltips(threadPanel);
                initTooltips(aiPanelBody);

                if (options.pushState !== false) {
                    window.history.pushState({ inboxUrl: url }, '', url);
                }
            })
            .catch(function () {
                // Jalur aman: kalau AJAX gagal (mis. network error), tetap
                // arahkan ke navigasi penuh biasa alih-alih membiarkan UI diam.
                window.location.href = url;
            });
    }

    document.addEventListener('click', function (event) {
        const link = event.target.closest('.js-conversation-link');
        if (!link) {
            return;
        }

        event.preventDefault();
        loadConversation(link.getAttribute('href'));
    });

    window.addEventListener('popstate', function () {
        loadConversation(window.location.href, { pushState: false });
    });

    // Tombol Reply di toolbar & tombol collapse AI panel dirender ulang
    // setiap swap thread, jadi dipasang lewat event delegation di document
    // (bukan addEventListener langsung ke elemen) supaya tidak perlu
    // dipasang ulang manual tiap kali.
    document.addEventListener('click', function (event) {
        if (event.target.closest('#btn-toolbar-reply')) {
            const bodyEl = document.getElementById('composer-body');
            if (bodyEl) {
                bodyEl.focus();
            }
        }

        if (event.target.closest('#btn-toolbar-generate')) {
            // Delegates to whichever of the composer's Generate/Regenerate
            // buttons is currently visible — the toolbar shortcut never
            // triggers its own AI call, it just reuses the composer's.
            const regenerateBtn = document.getElementById('btn-regenerate');
            const generateBtn = document.getElementById('btn-generate');
            const target = (regenerateBtn && !regenerateBtn.classList.contains('d-none')) ? regenerateBtn : generateBtn;
            if (target) {
                target.click();
            }
        }

        if (event.target.closest('#btn-toggle-ai-panel')) {
            if (aiPanelWrapper) {
                const collapsed = aiPanelWrapper.classList.toggle('ai-panel-collapsed');
                localStorage.setItem(AI_PANEL_COLLAPSE_KEY, collapsed ? '1' : '0');
            }
        }
    });

    if (aiPanelWrapper && localStorage.getItem(AI_PANEL_COLLAPSE_KEY) === '1') {
        aiPanelWrapper.classList.add('ai-panel-collapsed');
    }

    // Contact Details "Search fields and folders" — client-side filter atas
    // baris/section accordion yang sudah dirender, tidak ada request baru.
    // Delegated di document (bukan langsung ke #contactFieldSearch) karena
    // panel ini dirender ulang tiap ganti percakapan (lihat aiPanelBody.innerHTML di atas).
    document.addEventListener('input', function (event) {
        if (!event.target.matches('#contactFieldSearch')) {
            return;
        }

        const term = event.target.value.trim().toLowerCase();
        const groups = document.querySelectorAll('#contactFieldsAccordion [data-field-group]');

        groups.forEach(function (group) {
            const rows = group.querySelectorAll('[data-field-row]');
            let visibleCount = 0;

            rows.forEach(function (row) {
                const matches = term === '' || row.textContent.toLowerCase().includes(term);
                row.classList.toggle('d-none', !matches);
                if (matches) {
                    visibleCount++;
                }
            });

            group.classList.toggle('d-none', visibleCount === 0);
        });
    });

    initTooltips(document);

    /**
     * Load More (claude.txt Task 2): pages forward through GHL's own
     * /conversations/search cursor (startAfterDate/startAfter) one click at
     * a time — appends the next page's rows after whatever is already
     * loaded, never replaces the list, never asks GHL for an unrealistic
     * limit in one shot. Reuses the same JSON list endpoint inbox-polling.js
     * polls, so there's only one server-side pagination code path.
     */
    const loadMoreWrap = document.getElementById('conversationListLoadMore');
    const loadMoreBtn = document.getElementById('btnLoadMoreConversations');

    function cssEscape(value) {
        return window.CSS && window.CSS.escape ? window.CSS.escape(String(value)) : String(value);
    }

    if (loadMoreBtn && loadMoreWrap && conversationList && inboxApp && inboxApp.dataset.listPollUrl) {
        loadMoreBtn.addEventListener('click', function () {
            const startAfterDate = loadMoreWrap.dataset.startAfterDate;
            const startAfter = loadMoreWrap.dataset.startAfter;

            if (!startAfterDate || !startAfter) {
                loadMoreWrap.classList.add('d-none');
                return;
            }

            const url = new URL(inboxApp.dataset.listPollUrl, window.location.origin);
            url.searchParams.set('startAfterDate', startAfterDate);
            url.searchParams.set('startAfter', startAfter);

            loadMoreBtn.disabled = true;
            const originalLabel = loadMoreBtn.textContent;
            loadMoreBtn.textContent = 'Loading...';

            fetch(url.toString(), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            })
                .then(function (response) { return response.ok ? response.json() : null; })
                .then(function (data) {
                    if (!data || !Array.isArray(data.items)) {
                        return;
                    }

                    // Append at the bottom, in the order GHL returned them,
                    // so latest-activity ordering from the first page carries
                    // straight through — and skip anything already in the
                    // DOM instead of duplicating it.
                    data.items.forEach(function (item) {
                        const existing = conversationList.querySelector(
                            '.conversation-item[data-conversation-id="' + cssEscape(item.id) + '"]'
                        );
                        if (!existing) {
                            conversationList.insertAdjacentHTML('beforeend', item.html);
                        }
                    });

                    if (data.nextCursor && data.nextCursor.startAfterDate && data.nextCursor.startAfter) {
                        loadMoreWrap.dataset.startAfterDate = data.nextCursor.startAfterDate;
                        loadMoreWrap.dataset.startAfter = data.nextCursor.startAfter;
                    } else {
                        loadMoreWrap.classList.add('d-none');
                    }
                })
                .catch(function () {
                    // Silent: agent can just click Load More again.
                })
                .finally(function () {
                    loadMoreBtn.disabled = false;
                    loadMoreBtn.textContent = originalLabel;
                });
        });
    }

    /**
     * Toggle bintang (starred) — dipakai dari list maupun toolbar thread.
     */
    window.toggleStar = function (event, el, id) {
        event.preventDefault();
        event.stopPropagation();

        fetch(starUrl(id), {
            method: 'PUT',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
            },
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                el.querySelectorAll('i').forEach(function (icon) {
                    icon.classList.toggle('bxs-star', data.is_starred);
                    icon.classList.toggle('text-warning', data.is_starred);
                    icon.classList.toggle('bx-star', !data.is_starred);
                });
            });
    };

    /**
     * Toggle status baca — dipakai tombol "Mark Read" di toolbar untuk
     * menandai balik percakapan sebagai belum dibaca.
     */
    window.toggleRead = function (event, el, id) {
        event.preventDefault();
        event.stopPropagation();

        fetch(readUrl(id), {
            method: 'PUT',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
            },
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                el.querySelectorAll('i').forEach(function (icon) {
                    icon.classList.toggle('bx-envelope', !data.is_read);
                    icon.classList.toggle('bx-envelope-open', data.is_read);
                });

                setListItemReadState(id, data.is_read);
            });
    };

    /**
     * Cerminkan status baca ke titik unread & ketebalan nama di conversation
     * list, tanpa perlu refetch seluruh list.
     */
    function setListItemReadState(id, isRead) {
        if (!conversationList) {
            return;
        }

        const li = conversationList.querySelector('.conversation-item[data-conversation-id="' + id + '"]');
        if (!li) {
            return;
        }

        const nameEl = li.querySelector('.email-list-item-username');
        let dot = li.querySelector('[title="Belum dibaca"]');

        if (isRead) {
            if (dot) {
                dot.remove();
            }
            if (nameEl) {
                nameEl.classList.remove('fw-bold');
                nameEl.classList.add('fw-normal');
            }
        } else {
            if (!dot && nameEl) {
                dot = document.createElement('span');
                dot.className = 'bg-primary rounded-circle me-2 flex-shrink-0';
                dot.style.width = '8px';
                dot.style.height = '8px';
                dot.title = 'Belum dibaca';
                nameEl.parentNode.insertBefore(dot, nameEl);
            }
            if (nameEl) {
                nameEl.classList.remove('fw-normal');
                nameEl.classList.add('fw-bold');
            }
        }
    }
})();

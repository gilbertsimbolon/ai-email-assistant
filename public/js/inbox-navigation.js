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

        // Membuka percakapan otomatis menandainya sudah dibaca di server
        // (InboxController::resolveActiveConversation) — cerminkan itu di
        // list tanpa perlu refetch seluruh list.
        setListItemReadState(conversationId, true);
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

                if (data.conversation_id) {
                    markActiveInList(data.conversation_id);
                }

                if (window.initComposer) {
                    window.initComposer();
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

    initTooltips(document);

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

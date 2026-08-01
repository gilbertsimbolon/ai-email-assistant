(function () {
    'use strict';

    // Browser polling fallback for realtime (claude.txt section 10-14) — no
    // GHL webhook is configured for this Private Integration, so this is
    // the only way a new message/conversation shows up without the agent
    // hitting F5. Kept deliberately simple: list poll patches/prepends only
    // the rows that changed, thread poll appends only messages not already
    // in the DOM (dedup by data-message-id) — never location.reload().

    const inboxApp = document.getElementById('inboxApp');

    if (!inboxApp || !inboxApp.dataset.listPollUrl) {
        // gmail-inbox (or any page without polling wired up) doesn't have
        // these data attributes — nothing to do here.
        return;
    }

    const LIST_POLL_MS = 7000;
    const MESSAGES_POLL_MS = 5000;

    const conversationList = document.getElementById('conversationList');
    const chatHistory = document.getElementById('chatHistory');

    function isVisible() {
        return document.visibilityState === 'visible';
    }

    function pollList() {
        if (!isVisible() || !conversationList) {
            return;
        }

        fetch(inboxApp.dataset.listPollUrl, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(function (data) {
                if (!data || !Array.isArray(data.items)) {
                    return;
                }

                const activeId = inboxApp.dataset.activeConversationId || '';

                data.items.forEach(function (item) {
                    const existing = conversationList.querySelector(
                        '.conversation-item[data-conversation-id="' + cssEscape(item.id) + '"]'
                    );

                    if (existing) {
                        // Never clobber the row the agent currently has open
                        // — its highlighted state is managed by
                        // inbox-navigation.js, not this poll.
                        if (item.id === activeId) {
                            return;
                        }

                        existing.outerHTML = item.html;
                    } else {
                        conversationList.insertAdjacentHTML('afterbegin', item.html);
                    }
                });
            })
            .catch(function () {
                // Silent: a missed poll tick just tries again next interval.
            });
    }

    function pollActiveMessages() {
        const activeId = inboxApp.dataset.activeConversationId;

        if (!activeId || !isVisible() || !chatHistory) {
            return;
        }

        fetch(inboxApp.dataset.messagesPollUrlTemplate.replace('__ID__', activeId), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(function (data) {
                if (!data || !data.success || !Array.isArray(data.messages)) {
                    return;
                }

                let appended = false;

                data.messages.forEach(function (message) {
                    const alreadyShown = chatHistory.querySelector(
                        '[data-message-id="' + cssEscape(message.id) + '"]'
                    );

                    if (!alreadyShown) {
                        chatHistory.insertAdjacentHTML('beforeend', message.html);
                        appended = true;
                    }
                });

                if (appended) {
                    chatHistory.scrollTop = chatHistory.scrollHeight;
                }
            })
            .catch(function () {
                // Silent: try again next interval.
            });
    }

    function cssEscape(value) {
        return window.CSS && window.CSS.escape ? window.CSS.escape(String(value)) : String(value);
    }

    setInterval(pollList, LIST_POLL_MS);
    setInterval(pollActiveMessages, MESSAGES_POLL_MS);
})();

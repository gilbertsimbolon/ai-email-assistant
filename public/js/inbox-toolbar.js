(function () {
    "use strict";

    var TOOL_URL_KEYS = {
        summarize: "summarizeUrl",
        translate: "translateUrl",
        "detect-intent": "detectIntentUrl",
        "extract-info": "extractInfoUrl",
        sentiment: "sentimentUrl",
    };

    var LANGUAGE_LABELS = {
        en: "English",
        id: "Indonesian",
        ja: "Japanese",
        zh: "Chinese",
        es: "Spanish",
        fr: "French",
        de: "German",
    };

    var DEFAULT_LANGUAGE = "en";

    var lastRequest = {};
    var activeRequests = {};
    var toolbar = null;

    /*
    |--------------------------------------------------------------------------
    | DOM HELPERS
    |--------------------------------------------------------------------------
    */

    function getElement(selector) {
        return document.querySelector(selector);
    }

    function csrfToken() {
        var meta = getElement('meta[name="csrf-token"]');

        return meta ? meta.getAttribute("content") : "";
    }

    function urlForTool(tool) {
        var key = TOOL_URL_KEYS[tool];

        if (!toolbar || !key) {
            return null;
        }

        return toolbar.dataset[key] || null;
    }

    function modalFor(tool) {
        return document.getElementById("modal-" + tool);
    }

    /*
    |--------------------------------------------------------------------------
    | VALUE HELPERS
    |--------------------------------------------------------------------------
    */

    function displayValue(value, fallback) {
        if (
            value === null ||
            value === undefined ||
            value === ""
        ) {
            return fallback || "-";
        }

        if (Array.isArray(value)) {
            return value.length
                ? value.join(", ")
                : fallback || "-";
        }

        if (typeof value === "object") {
            try {
                return JSON.stringify(value);
            } catch (error) {
                return fallback || "-";
            }
        }

        return String(value);
    }

    function safeString(value) {
        return displayValue(value, "");
    }

    function normalizeStatus(value) {
        var status = safeString(value);

        if (!status) {
            return "-";
        }

        return status;
    }

    /*
    |--------------------------------------------------------------------------
    | MODAL STATE
    |--------------------------------------------------------------------------
    */

    function setModalState(
        modalEl,
        state,
        errorMessage
    ) {
        if (!modalEl) {
            return;
        }

        var loading =
            modalEl.querySelector(
                ".ai-tool-loading"
            );

        var result =
            modalEl.querySelector(
                ".ai-tool-result"
            );

        var error =
            modalEl.querySelector(
                ".ai-tool-error"
            );

        var copyBtn =
            modalEl.querySelector(
                ".ai-tool-copy"
            );

        var regenerateBtn =
            modalEl.querySelector(
                ".ai-tool-regenerate"
            );

        if (loading) {
            loading.classList.toggle(
                "d-none",
                state !== "loading"
            );
        }

        if (error) {
            error.classList.toggle(
                "d-none",
                state !== "error"
            );

            if (state === "error") {
                error.textContent =
                    errorMessage ||
                    "Gagal memproses permintaan.";
            }
        }

        if (result) {
            result.classList.toggle(
                "d-none",
                state !== "result"
            );
        }

        if (copyBtn) {
            copyBtn.classList.toggle(
                "d-none",
                state !== "result"
            );
        }

        if (regenerateBtn) {
            regenerateBtn.classList.toggle(
                "d-none",
                state !== "result"
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FIELD RENDERING
    |--------------------------------------------------------------------------
    */

    function fieldRow(
        label,
        value
    ) {
        var wrap =
            document.createElement("div");

        wrap.className = "mb-3";

        var labelEl =
            document.createElement("label");

        labelEl.className =
            "form-label text-muted small fw-bold mb-0 text-uppercase";

        labelEl.textContent =
            label;

        var valueEl =
            document.createElement("p");

        valueEl.className = "mb-0";

        valueEl.textContent =
            displayValue(value);

        wrap.appendChild(labelEl);
        wrap.appendChild(valueEl);

        return wrap;
    }

    function renderFields(
        container,
        fields
    ) {
        if (!container) {
            return;
        }

        container.innerHTML = "";

        fields.forEach(function (field) {
            container.appendChild(
                fieldRow(
                    field[0],
                    field[1]
                )
            );
        });
    }

    /*
    |--------------------------------------------------------------------------
    | SUMMARIZE
    |--------------------------------------------------------------------------
    */

    function renderSummarize(
        container,
        data
    ) {
        data = data || {};

        renderFields(
            container,
            [
                [
                    "Conversation Summary",
                    data.conversation_summary,
                ],
                [
                    "Customer Problem",
                    data.customer_problem,
                ],
                [
                    "Current Status",
                    data.current_status,
                ],
                [
                    "Pending Questions",
                    data.pending_questions,
                ],
                [
                    "Suggested Action",
                    data.suggested_action,
                ],
                [
                    "Risk Level",
                    data.risk_level,
                ],
                [
                    "Timeline",
                    data.timeline,
                ],
                [
                    "Important Notes",
                    data.important_notes,
                ],
                [
                    "Estimated Intent",
                    data.estimated_intent,
                ],
                [
                    "Recommended Reply",
                    data.recommended_reply,
                ],
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | TRANSLATE
    |--------------------------------------------------------------------------
    */

    function renderTranslate(
        container,
        data,
        language
    ) {
        if (!container) {
            return;
        }

        data = data || {};

        language =
            language ||
            DEFAULT_LANGUAGE;

        container.innerHTML = "";

        var label =
            document.createElement("label");

        label.className =
            "form-label text-muted small fw-bold mb-2 text-uppercase";

        label.textContent =
            "Translated to " +
            (
                LANGUAGE_LABELS[language]
                || language
                || ""
            );

        var body =
            document.createElement("p");

        body.className = "mb-0";
        body.style.whiteSpace =
            "pre-wrap";

        body.textContent =
            displayValue(
                data.translated_text
            );

        container.appendChild(label);
        container.appendChild(body);
    }

    /*
    |--------------------------------------------------------------------------
    | DETECT INTENT
    |--------------------------------------------------------------------------
    */

    function renderDetectIntent(
        container,
        data
    ) {
        data = data || {};

        renderFields(
            container,
            [
                [
                    "Intent",
                    data.intent,
                ],
                [
                    "Confidence Score",
                    data.confidence_score,
                ],
                [
                    "Matched SOP",
                    data.matched_sop,
                ],
                [
                    "Matched Knowledge",
                    Array.isArray(
                        data.matched_knowledge
                    )
                        ? data.matched_knowledge.join(", ")
                        : data.matched_knowledge,
                ],
                [
                    "Matched Template",
                    data.matched_template,
                ],
                [
                    "Reasoning",
                    data.reasoning,
                ],
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE TEXT ELEMENT
    |--------------------------------------------------------------------------
    */

    function createTextElement(
        tag,
        className,
        value
    ) {
        var element =
            document.createElement(tag);

        if (className) {
            element.className =
                className;
        }

        element.textContent =
            displayValue(value);

        return element;
    }

    /*
    |--------------------------------------------------------------------------
    | PAYMENT CARD
    |--------------------------------------------------------------------------
    */

    function createPaymentCard(
        payment,
        index
    ) {
        var card =
            document.createElement("div");

        card.className =
            "card border shadow-none mb-3";

        var body =
            document.createElement("div");

        body.className =
            "card-body py-3";

        /*
        |--------------------------------------------------------------------------
        | Header
        |--------------------------------------------------------------------------
        */

        var header =
            document.createElement("div");

        header.className =
            "d-flex justify-content-between align-items-start mb-3";

        var headerLeft =
            document.createElement("div");

        var title =
            document.createElement("div");

        title.className =
            "fw-bold";

        title.textContent =
            "Payment #" +
            (index + 1);

        var type =
            document.createElement("small");

        type.className =
            "text-muted";

        type.textContent =
            displayValue(
                payment.type,
                "payment"
            );

        headerLeft.appendChild(title);
        headerLeft.appendChild(type);

        var statusBadge =
            document.createElement("span");

        statusBadge.className =
            "badge bg-label-success";

        statusBadge.textContent =
            normalizeStatus(
                payment.payment_status
                || payment.status
            );

        header.appendChild(headerLeft);
        header.appendChild(statusBadge);

        /*
        |--------------------------------------------------------------------------
        | Data
        |--------------------------------------------------------------------------
        */

        var row =
            document.createElement("div");

        row.className =
            "row g-3";

        var products = [];

        if (
            Array.isArray(
                payment.products
            )
        ) {
            products =
                payment.products;
        }

        if (
            !products.length &&
            Array.isArray(
                payment.items
            )
        ) {
            products =
                payment.items
                    .map(function (item) {
                        return item &&
                            item.name
                            ? item.name
                            : null;
                    })
                    .filter(Boolean);
        }

        var productText =
            products.length
                ? products.join(", ")
                : "-";

        var amount =
            payment.formatted_amount;

        if (!amount) {
            amount =
                payment.amount !== null &&
                payment.amount !== undefined &&
                payment.amount !== ""
                    ? (
                          payment.currency
                              ? String(
                                    payment.currency
                                ).toUpperCase() +
                                " " +
                                String(
                                    payment.amount
                                )
                              : String(
                                    payment.amount
                                )
                      )
                    : "-";
        }

        var paymentMethod =
            payment.payment_method
            || payment.paymentProviderType
            || payment.payment_provider_type
            || "-";

        var orderId =
            payment.order_id
            || payment.transaction_id
            || "-";

        /*
        |--------------------------------------------------------------------------
        | Fields
        |--------------------------------------------------------------------------
        */

        var fields = [
            [
                "Product",
                productText,
                "fw-semibold",
            ],
            [
                "Amount",
                amount,
                "fw-semibold",
            ],
            [
                "Purchase Date",
                payment.purchase_date,
                "",
            ],
            [
                "Payment Method",
                paymentMethod,
                "",
            ],
            [
                "Receipt Number",
                payment.receipt_number,
                "",
            ],
            [
                "Order / Transaction ID",
                orderId,
                "text-break small",
            ],
        ];

        fields.forEach(
            function (field) {
                var col =
                    document.createElement(
                        "div"
                    );

                col.className =
                    "col-md-6";

                var label =
                    document.createElement(
                        "div"
                    );

                label.className =
                    "text-muted small";

                label.textContent =
                    field[0];

                var value =
                    document.createElement(
                        "div"
                    );

                value.className =
                    field[2] || "";

                value.textContent =
                    displayValue(
                        field[1]
                    );

                col.appendChild(label);
                col.appendChild(value);

                row.appendChild(col);
            }
        );

        body.appendChild(header);
        body.appendChild(row);

        card.appendChild(body);

        return card;
    }

    /*
    |--------------------------------------------------------------------------
    | EXTRACT INFORMATION
    |--------------------------------------------------------------------------
    */

    function renderExtractInfo(
        container,
        data
    ) {
        if (!container) {
            return;
        }

        data = data || {};

        var fields = [
            [
                "Customer Name",
                data.customer_name,
            ],
            [
                "Email",
                data.email,
            ],
            [
                "Phone",
                data.phone,
            ],
            [
                "Contact ID",
                data.contact_id,
            ],
            [
                "Conversation ID",
                data.conversation_id,
            ],
            [
                "Channel",
                data.channel,
            ],
            [
                "Company",
                data.company_name,
            ],
            [
                "Product",
                data.product,
            ],
            [
                "Purchase Date",
                data.purchase_date,
            ],
            [
                "Purchase Price",
                data.purchase_price,
            ],
            [
                "Receipt Number",
                data.receipt_number,
            ],
            [
                "Tags",
                Array.isArray(data.tags)
                    ? data.tags.join(", ")
                    : data.tags,
            ],
        ];

        /*
        |--------------------------------------------------------------------------
        | CUSTOM FIELDS
        |--------------------------------------------------------------------------
        */

        if (
            Array.isArray(
                data.custom_fields
            )
        ) {
            data.custom_fields.forEach(
                function (field) {
                    if (
                        !field ||
                        typeof field !==
                            "object"
                    ) {
                        return;
                    }

                    var label =
                        field.key
                        || field.name
                        || field.id
                        || "Custom Field";

                    var value =
                        field.value;

                    if (
                        Array.isArray(value)
                    ) {
                        value =
                            value.join(", ");
                    }

                    fields.push([
                        label,
                        value,
                    ]);
                }
            );
        }

        renderFields(
            container,
            fields
        );

        /*
        |--------------------------------------------------------------------------
        | PAYMENT HISTORY
        |--------------------------------------------------------------------------
        */

        var payments =
            Array.isArray(
                data.payments
            )
                ? data.payments
                : [];

        if (!payments.length) {
            return;
        }

        var title =
            document.createElement("div");

        title.className =
            "border-top pt-3 mt-3 mb-3";

        var titleWrapper =
            document.createElement("div");

        titleWrapper.className =
            "d-flex align-items-center justify-content-between";

        var titleLeft =
            document.createElement("div");

        var titleText =
            document.createElement("div");

        titleText.className =
            "fw-bold";

        titleText.textContent =
            "Payment History";

        var subtitle =
            document.createElement("small");

        subtitle.className =
            "text-muted";

        subtitle.textContent =
            payments.length +
            " payment record" +
            (
                payments.length > 1
                    ? "s"
                    : ""
            );

        titleLeft.appendChild(
            titleText
        );

        titleLeft.appendChild(
            subtitle
        );

        var countBadge =
            document.createElement("span");

        countBadge.className =
            "badge bg-label-primary";

        countBadge.textContent =
            String(payments.length);

        titleWrapper.appendChild(
            titleLeft
        );

        titleWrapper.appendChild(
            countBadge
        );

        title.appendChild(
            titleWrapper
        );

        container.appendChild(title);

        payments.forEach(
            function (payment, index) {
                if (
                    !payment ||
                    typeof payment !==
                        "object"
                ) {
                    return;
                }

                container.appendChild(
                    createPaymentCard(
                        payment,
                        index
                    )
                );
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SENTIMENT
    |--------------------------------------------------------------------------
    */

    function renderSentiment(
        container,
        data
    ) {
        data = data || {};

        renderFields(
            container,
            [
                [
                    "Emotion",
                    data.emotion,
                ],
                [
                    "Frustration Level",
                    data.frustration_level,
                ],
                [
                    "Urgency",
                    data.urgency,
                ],
                [
                    "Customer Satisfaction",
                    data.customer_satisfaction,
                ],
                [
                    "Risk Score",
                    data.risk_score,
                ],
                [
                    "Priority",
                    data.priority,
                ],
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | RENDERER MAP
    |--------------------------------------------------------------------------
    */

    var RENDERERS = {
        summarize: renderSummarize,
        translate: renderTranslate,
        "detect-intent": renderDetectIntent,
        "extract-info": renderExtractInfo,
        sentiment: renderSentiment,
    };

    /*
    |--------------------------------------------------------------------------
    | SHOW MODAL
    |--------------------------------------------------------------------------
    */

    function showModal(
        modalEl
    ) {
        if (
            !modalEl ||
            typeof bootstrap ===
                "undefined"
        ) {
            return;
        }

        bootstrap.Modal
            .getOrCreateInstance(
                modalEl
            )
            .show();
    }

    /*
    |--------------------------------------------------------------------------
    | RUN TOOL
    |--------------------------------------------------------------------------
    */

    function runTool(
        tool,
        options
    ) {
        options = options || {};

        var modalEl =
            modalFor(tool);

        var url =
            urlForTool(tool);

        if (!modalEl || !url) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Prevent duplicate requests
        |--------------------------------------------------------------------------
        */

        if (
            activeRequests[tool]
        ) {
            return;
        }

        var language =
            options.language
            || (
                lastRequest[tool]
                    ? lastRequest[tool]
                          .language
                    : null
            )
            || DEFAULT_LANGUAGE;

        lastRequest[tool] = {
            language: language,
        };

        showModal(modalEl);

        setModalState(
            modalEl,
            "loading"
        );

        activeRequests[tool] = true;

        var body = {
            force_refresh:
                !!options.forceRefresh,
        };

        if (
            tool === "translate"
        ) {
            body.language =
                language;
        }

        fetch(url, {
            method: "POST",

            headers: {
                "X-CSRF-TOKEN":
                    csrfToken(),

                Accept:
                    "application/json",

                "Content-Type":
                    "application/json",
            },

            body: JSON.stringify(body),
        })
            .then(
                function (response) {
                    return response
                        .text()
                        .then(
                            function (text) {
                                var data = {};

                                try {
                                    data =
                                        text
                                            ? JSON.parse(
                                                  text
                                              )
                                            : {};
                                } catch (
                                    parseError
                                ) {
                                    throw new Error(
                                        "Server mengembalikan response yang tidak valid."
                                    );
                                }

                                if (
                                    !response.ok
                                ) {
                                    throw new Error(
                                        data.message
                                        || "Gagal memproses permintaan."
                                    );
                                }

                                return data;
                            }
                        );
                }
            )
            .then(
                function (data) {
                    var renderer =
                        RENDERERS[tool];

                    if (
                        typeof renderer ===
                        "function"
                    ) {
                        renderer(
                            modalEl.querySelector(
                                ".ai-tool-result"
                            ),
                            data.result || {},
                            language
                        );
                    }

                    setModalState(
                        modalEl,
                        "result"
                    );
                }
            )
            .catch(
                function (error) {
                    console.error(
                        "Inbox AI Tool Error:",
                        error
                    );

                    setModalState(
                        modalEl,
                        "error",
                        error.message
                            || "Gagal memproses permintaan."
                    );
                }
            )
            .finally(
                function () {
                    activeRequests[tool] =
                        false;
                }
            );
    }

    /*
    |--------------------------------------------------------------------------
    | THREAD TEXT
    |--------------------------------------------------------------------------
    */

    function threadText() {
        var chatHistory =
            document.getElementById(
                "chatHistory"
            );

        return chatHistory
            ? chatHistory.innerText.trim()
            : "";
    }

    /*
    |--------------------------------------------------------------------------
    | COPY HELPER
    |--------------------------------------------------------------------------
    */

    function copyText(text) {
        if (
            navigator.clipboard &&
            typeof navigator.clipboard
                .writeText === "function"
        ) {
            return navigator.clipboard.writeText(
                text
            );
        }

        return new Promise(
            function (resolve, reject) {
                var textarea =
                    document.createElement(
                        "textarea"
                    );

                textarea.value =
                    text;

                textarea.style.position =
                    "fixed";

                textarea.style.opacity =
                    "0";

                document.body.appendChild(
                    textarea
                );

                textarea.select();

                try {
                    document.execCommand(
                        "copy"
                    );

                    document.body.removeChild(
                        textarea
                    );

                    resolve();
                } catch (error) {
                    document.body.removeChild(
                        textarea
                    );

                    reject(error);
                }
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | TOAST
    |--------------------------------------------------------------------------
    */

    function showToast(
        title,
        icon
    ) {
        if (
            typeof Swal ===
            "undefined"
        ) {
            return;
        }

        Swal.fire({
            toast: true,
            position: "top-end",
            icon: icon || "success",
            title: title,
            timer: 1200,
            showConfirmButton: false,
        });
    }

    /*
    |--------------------------------------------------------------------------
    | COPY EMAIL
    |--------------------------------------------------------------------------
    */

    function copyEmail() {
        var text =
            threadText();

        if (!text) {
            showToast(
                "Tidak ada isi conversation.",
                "warning"
            );

            return;
        }

        copyText(text)
            .then(function () {
                showToast(
                    "Email disalin",
                    "success"
                );
            })
            .catch(function () {
                showToast(
                    "Gagal menyalin email.",
                    "error"
                );
            });
    }

    /*
    |--------------------------------------------------------------------------
    | DOWNLOAD EMAIL
    |--------------------------------------------------------------------------
    */

    function downloadEmail() {
        var text =
            threadText();

        if (!text) {
            showToast(
                "Tidak ada isi conversation.",
                "warning"
            );

            return;
        }

        var blob =
            new Blob(
                [text],
                {
                    type: "text/plain;charset=utf-8",
                }
            );

        var objectUrl =
            URL.createObjectURL(
                blob
            );

        var link =
            document.createElement(
                "a"
            );

        link.href =
            objectUrl;

        link.download =
            "email-thread-" +
            (
                toolbar
                    ? toolbar.dataset
                          .conversationId
                        || "export"
                    : "export"
            ) +
            ".txt";

        document.body.appendChild(
            link
        );

        link.click();

        document.body.removeChild(
            link
        );

        setTimeout(
            function () {
                URL.revokeObjectURL(
                    objectUrl
                );
            },
            100
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PRINT
    |--------------------------------------------------------------------------
    */

    function printEmail() {
        window.print();
    }

    /*
    |--------------------------------------------------------------------------
    | INITIALIZE AI TOOLBAR
    |--------------------------------------------------------------------------
    */

    function initAiToolbar() {
        toolbar =
            document.getElementById(
                "ai-toolbar"
            );

        if (!toolbar) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | AI TOOL TRIGGERS
        |--------------------------------------------------------------------------
        */

        toolbar
            .querySelectorAll(
                ".ai-tool-trigger"
            )
            .forEach(
                function (el) {
                    /*
                    | Prevent duplicate listeners
                    */

                    if (
                        el.dataset
                            .aiBound === "1"
                    ) {
                        return;
                    }

                    el.dataset.aiBound =
                        "1";

                    el.addEventListener(
                        "click",
                        function (
                            event
                        ) {
                            event.preventDefault();

                            runTool(
                                el.dataset
                                    .tool,
                                {
                                    language:
                                        el.dataset
                                            .language
                                            || DEFAULT_LANGUAGE,

                                    forceRefresh:
                                        false,
                                }
                            );
                        }
                    );
                }
            );

        /*
        |--------------------------------------------------------------------------
        | MODALS
        |--------------------------------------------------------------------------
        */

        document
            .querySelectorAll(
                ".ai-tool-modal"
            )
            .forEach(
                function (modalEl) {
                    if (
                        modalEl.dataset
                            .aiBound === "1"
                    ) {
                        return;
                    }

                    modalEl.dataset.aiBound =
                        "1";

                    var tool =
                        modalEl.dataset
                            .tool;

                    /*
                    |--------------------------------------------------------------------------
                    | COPY AI RESULT
                    |--------------------------------------------------------------------------
                    */

                    var copyBtn =
                        modalEl.querySelector(
                            ".ai-tool-copy"
                        );

                    if (copyBtn) {
                        copyBtn.addEventListener(
                            "click",
                            function () {
                                var result =
                                    modalEl.querySelector(
                                        ".ai-tool-result"
                                    );

                                var text =
                                    result
                                        ? result.innerText
                                        : "";

                                if (!text) {
                                    showToast(
                                        "Tidak ada hasil untuk disalin.",
                                        "warning"
                                    );

                                    return;
                                }

                                copyText(
                                    text
                                )
                                    .then(
                                        function () {
                                            showToast(
                                                "Disalin",
                                                "success"
                                            );
                                        }
                                    )
                                    .catch(
                                        function () {
                                            showToast(
                                                "Gagal menyalin.",
                                                "error"
                                            );
                                        }
                                    );
                            }
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | REGENERATE
                    |--------------------------------------------------------------------------
                    */

                    var regenerateBtn =
                        modalEl.querySelector(
                            ".ai-tool-regenerate"
                        );

                    if (
                        regenerateBtn
                    ) {
                        regenerateBtn.addEventListener(
                            "click",
                            function () {
                                var language =
                                    lastRequest[
                                        tool
                                    ]
                                        ? lastRequest[
                                              tool
                                          ].language
                                        : DEFAULT_LANGUAGE;

                                runTool(
                                    tool,
                                    {
                                        language:
                                            language,

                                        forceRefresh:
                                            true,
                                    }
                                );
                            }
                        );
                    }
                }
            );

        /*
        |--------------------------------------------------------------------------
        | COPY EMAIL
        |--------------------------------------------------------------------------
        */

        var btnCopyEmail =
            document.getElementById(
                "btn-copy-email"
            );

        if (
            btnCopyEmail &&
            btnCopyEmail.dataset
                .aiBound !== "1"
        ) {
            btnCopyEmail.dataset.aiBound =
                "1";

            btnCopyEmail.addEventListener(
                "click",
                function (event) {
                    event.preventDefault();

                    copyEmail();
                }
            );
        }

        /*
        |--------------------------------------------------------------------------
        | DOWNLOAD EMAIL
        |--------------------------------------------------------------------------
        */

        var btnDownloadEmail =
            document.getElementById(
                "btn-download-email"
            );

        if (
            btnDownloadEmail &&
            btnDownloadEmail.dataset
                .aiBound !== "1"
        ) {
            btnDownloadEmail.dataset.aiBound =
                "1";

            btnDownloadEmail.addEventListener(
                "click",
                function (event) {
                    event.preventDefault();

                    downloadEmail();
                }
            );
        }

        /*
        |--------------------------------------------------------------------------
        | PRINT EMAIL
        |--------------------------------------------------------------------------
        */

        var btnPrintEmail =
            document.getElementById(
                "btn-print-email"
            );

        if (
            btnPrintEmail &&
            btnPrintEmail.dataset
                .aiBound !== "1"
        ) {
            btnPrintEmail.dataset.aiBound =
                "1";

            btnPrintEmail.addEventListener(
                "click",
                function (event) {
                    event.preventDefault();

                    printEmail();
                }
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PUBLIC API
    |--------------------------------------------------------------------------
    |
    | inbox-navigation.js can call:
    |
    | window.initAiToolbar()
    |
    | after replacing conversation-thread.blade.php.
    |
    */

    window.initAiToolbar =
        initAiToolbar;

    /*
    |--------------------------------------------------------------------------
    | INITIAL LOAD
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        "DOMContentLoaded",
        initAiToolbar
    );
})();
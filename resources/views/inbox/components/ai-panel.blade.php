@if ($activeConversation)

    @php
        $contactDetails = $contactDetails ?? null;

        /*
        |--------------------------------------------------------------------------
        | CONTACT BASIC DATA
        |--------------------------------------------------------------------------
        */

        $displayName =
            $contactDetails?->fullName()
            ?? $activeConversation->contact_name
            ?? '-';

        $displayEmail =
            $contactDetails?->email
            ?? $activeConversation->contact_email
            ?? null;

        $displayPhone =
            $contactDetails?->phone
            ?? $activeConversation->contact_phone
            ?? null;

        /*
        |--------------------------------------------------------------------------
        | ATTACHMENTS / DRAFTS
        |--------------------------------------------------------------------------
        */

        $allAttachments = $activeConversation->messages->flatMap(
            fn ($m) => collect($m->attachments ?? [])
                ->map(fn ($a) => [
                    'message' => $m,
                    'attachment' => $a,
                ])
        );

        $draftHistory = $activeConversation->drafts->sortByDesc('version');

        /*
        |--------------------------------------------------------------------------
        | ADDITIONAL CONTACT FIELDS
        |--------------------------------------------------------------------------
        */

        $extraFields = collect([
            'Company' => $contactDetails?->companyName,
            'Address' => $contactDetails?->address1,
            'City' => $contactDetails?->city,
            'State' => $contactDetails?->state,
            'Postal Code' => $contactDetails?->postalCode,
            'Country' => $contactDetails?->country,
            'Website' => $contactDetails?->website,
            'Timezone' => $contactDetails?->timezone,
            'Source' => $contactDetails?->source,
            'Date Added' => $contactDetails?->dateAdded,
            'Date Updated' => $contactDetails?->dateUpdated,
        ])->filter(fn ($value) => filled($value));

        /*
        |--------------------------------------------------------------------------
        | PAYMENT HISTORY
        |--------------------------------------------------------------------------
        |
        | Controller/service harus mengirim:
        |
        | $paymentHistory = [...]
        |
        | Setiap item dapat berupa:
        | order / transaction
        |
        */

        $paymentHistory = $paymentHistory ?? [];

        /*
        |--------------------------------------------------------------------------
        | PAYMENT HELPERS
        |--------------------------------------------------------------------------
        */

        $formatPaymentAmount = function ($payment) {
            if (
                isset($payment['formatted_amount'])
                && filled($payment['formatted_amount'])
            ) {
                return $payment['formatted_amount'];
            }

            $amount = $payment['amount'] ?? null;
            $currency = $payment['currency'] ?? null;

            if ($amount === null || $amount === '') {
                return '-';
            }

            return $currency
                ? strtoupper($currency) . ' ' . $amount
                : $amount;
        };

        $paymentStatus = function ($payment) {
            return $payment['payment_status']
                ?? $payment['status']
                ?? '-';
        };

        $paymentProduct = function ($payment) {
            if (filled($payment['product'] ?? null)) {
                return $payment['product'];
            }

            if (!empty($payment['products'])) {
                return implode(', ', $payment['products']);
            }

            if (!empty($payment['items'])) {
                return collect($payment['items'])
                    ->map(fn ($item) => $item['name'] ?? null)
                    ->filter()
                    ->implode(', ');
            }

            return '-';
        };

        $paymentMethod = function ($payment) {
            $method =
                $payment['payment_method']
                ?? $payment['payment_provider_type']
                ?? null;

            if (is_array($method)) {
                $brand = data_get($method, 'card.brand');
                $last4 = data_get($method, 'card.last4');

                if ($brand && $last4) {
                    return strtoupper($brand) . ' •••• ' . $last4;
                }

                return json_encode($method);
            }

            return filled($method)
                ? $method
                : '-';
        };

        $paymentId = function ($payment) {
            return $payment['order_id']
                ?? $payment['transaction_id']
                ?? '-';
        };

        $paymentReceipt = function ($payment) {
            return $payment['receipt_number']
                ?? null;
        };

        $paymentDate = function ($payment) {
            return $payment['purchase_date']
                ?? $payment['created_at']
                ?? '-';
        };

        /*
        |--------------------------------------------------------------------------
        | PHONE
        |--------------------------------------------------------------------------
        */

        $phoneCode = null;
        $phoneRest = $displayPhone;

        if (
            $displayPhone
            && preg_match(
                '/^(\+\d{1,3})[\s-]?(.*)$/',
                $displayPhone,
                $m
            )
        ) {
            $phoneCode = $m[1];
            $phoneRest = $m[2];
        }
    @endphp


    <div class="p-3 overflow-auto flex-grow-1">

        {{-- ================================================================
             PROFILE
        ================================================================= --}}

        <div class="d-flex flex-column align-items-center text-center mb-4">

            <div class="avatar avatar-lg mb-2">
                <span class="avatar-initial rounded-circle bg-label-primary fs-4">
                    {{ strtoupper(substr($displayName ?: 'P', 0, 1)) }}
                </span>
            </div>

            <p class="mb-0 fw-semibold text-break">
                {{ $displayName }}
            </p>

            @if ($displayEmail)
                <p class="mb-0 text-muted small text-break">
                    {{ $displayEmail }}
                </p>
            @endif

        </div>


        {{-- ================================================================
             OWNER / FOLLOWERS
        ================================================================= --}}

        <div class="row g-2 mb-4">

            <div class="col-6">
                <label class="form-label text-muted small fw-bold mb-1">
                    OWNER
                </label>

                <select class="form-select form-select-sm" disabled>
                    <option>
                        {{ $contactDetails?->assignedTo ?? 'Belum ditugaskan' }}
                    </option>
                </select>
            </div>

            <div class="col-6">
                <label class="form-label text-muted small fw-bold mb-1">
                    FOLLOWERS
                </label>

                <select class="form-select form-select-sm" disabled>
                    <option>-</option>
                </select>
            </div>

        </div>


        {{-- ================================================================
             TAGS
        ================================================================= --}}

        <div class="mb-4">

            <label class="form-label text-muted small fw-bold mb-2 d-block">
                TAGS

                @if ($contactDetails)
                    ({{ count($contactDetails->tags ?? []) }})
                @endif
            </label>

            @if (!$contactDetails)

                <p class="text-muted small mb-0">
                    Tags belum tersedia.
                </p>

            @elseif (empty($contactDetails->tags))

                <p class="text-muted small mb-0">
                    Tidak ada tags.
                </p>

            @else

                <div class="d-flex flex-wrap gap-1">

                    @foreach ($contactDetails->tags as $tag)

                        <span class="badge bg-label-primary rounded-pill">
                            {{ $tag }}
                        </span>

                    @endforeach

                </div>

            @endif

        </div>


        {{-- ================================================================
             TABS
        ================================================================= --}}

        <ul
            class="nav nav-pills nav-sm mb-3 contact-details-tabs"
            role="tablist"
        >

            <li class="nav-item" role="presentation">

                <button
                    class="nav-link active"
                    data-bs-toggle="tab"
                    data-bs-target="#tab-all-fields"
                    type="button"
                    role="tab"
                >
                    All fields
                </button>

            </li>

            <li class="nav-item" role="presentation">

                <button
                    class="nav-link"
                    data-bs-toggle="tab"
                    data-bs-target="#tab-dnd"
                    type="button"
                    role="tab"
                >
                    DND
                </button>

            </li>

            <li class="nav-item" role="presentation">

                <button
                    class="nav-link"
                    data-bs-toggle="tab"
                    data-bs-target="#tab-actions"
                    type="button"
                    role="tab"
                >
                    Actions
                </button>

            </li>

        </ul>


        <div class="tab-content">


            {{-- ============================================================
                 ALL FIELDS
            ============================================================= --}}

            <div
                class="tab-pane fade show active"
                id="tab-all-fields"
                role="tabpanel"
            >

                {{-- Search --}}

                <div class="input-group input-group-merge mb-3">

                    <span class="input-group-text bg-transparent border-end-0">
                        <i class="bx bx-search"></i>
                    </span>

                    <input
                        type="text"
                        id="contactFieldSearch"
                        class="form-control border-start-0 ps-0 form-control-sm"
                        placeholder="Search fields and folders"
                    >

                </div>


                <div
                    class="accordion"
                    id="contactFieldsAccordion"
                >


                    {{-- ====================================================
                         CONTACT
                    ===================================================== --}}

                    <div
                        class="accordion-item"
                        data-field-group
                    >

                        <h2 class="accordion-header">

                            <button
                                class="accordion-button"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#accordion-contact"
                            >
                                Contact
                            </button>

                        </h2>


                        <div
                            id="accordion-contact"
                            class="accordion-collapse collapse show"
                            data-bs-parent="#contactFieldsAccordion"
                        >

                            <div class="accordion-body p-3">


                                {{-- First Name --}}

                                <div
                                    class="mb-3"
                                    data-field-row
                                >

                                    <label class="form-label text-muted small fw-bold mb-1">
                                        First Name
                                    </label>

                                    <p class="form-control-plaintext small mb-0 text-break">
                                        {{ $contactDetails?->firstName ?? '-' }}
                                    </p>

                                </div>


                                {{-- Last Name --}}

                                <div
                                    class="mb-3"
                                    data-field-row
                                >

                                    <label class="form-label text-muted small fw-bold mb-1">
                                        Last Name
                                    </label>

                                    <p class="form-control-plaintext small mb-0 text-break">
                                        {{ $contactDetails?->lastName ?? '-' }}
                                    </p>

                                </div>


                                {{-- Email --}}

                                <div
                                    class="mb-3"
                                    data-field-row
                                >

                                    <label class="form-label text-muted small fw-bold mb-1">
                                        Email
                                    </label>

                                    <div class="d-flex align-items-center gap-1">

                                        <p class="form-control-plaintext small mb-0 text-break flex-grow-1">
                                            {{ $displayEmail ?? '-' }}
                                        </p>

                                        @if ($displayEmail)

                                            <a
                                                href="mailto:{{ $displayEmail }}"
                                                class="btn btn-icon btn-sm btn-outline-secondary flex-shrink-0"
                                                title="Kirim email"
                                            >
                                                <i class="bx bx-envelope"></i>
                                            </a>

                                        @endif

                                    </div>

                                </div>


                                {{-- Phone --}}

                                <div
                                    class="mb-3"
                                    data-field-row
                                >

                                    <label class="form-label text-muted small fw-bold mb-1">
                                        Phone
                                    </label>

                                    @if ($displayPhone)

                                        <p class="form-control-plaintext small mb-0 text-break">
                                            {{ $displayPhone }}
                                        </p>

                                    @else

                                        <p class="form-control-plaintext small mb-0">
                                            -
                                        </p>

                                    @endif

                                </div>


                                {{-- Date of Birth --}}

                                <div
                                    class="mb-0"
                                    data-field-row
                                >

                                    <label class="form-label text-muted small fw-bold mb-1">
                                        Date of Birth
                                    </label>

                                    <p class="form-control-plaintext small mb-0 text-break">
                                        {{ $contactDetails?->dateOfBirth ?? '-' }}
                                    </p>

                                </div>

                            </div>

                        </div>

                    </div>


                    {{-- ====================================================
                         IDENTIFIERS
                    ===================================================== --}}

                    @if ($activeConversation->contact_id)

                        <div
                            class="accordion-item"
                            data-field-group
                        >

                            <h2 class="accordion-header">

                                <button
                                    class="accordion-button collapsed"
                                    type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#accordion-identifiers"
                                >
                                    Identifiers
                                </button>

                            </h2>


                            <div
                                id="accordion-identifiers"
                                class="accordion-collapse collapse"
                                data-bs-parent="#contactFieldsAccordion"
                            >

                                <div class="accordion-body p-3">

                                    <div
                                        class="mb-0"
                                        data-field-row
                                    >

                                        <label class="form-label text-muted small fw-bold mb-1">
                                            Contact ID
                                        </label>

                                        <div class="d-flex align-items-center gap-1">

                                            <code class="small text-break flex-grow-1">
                                                {{ $activeConversation->contact_id }}
                                            </code>

                                            <button
                                                type="button"
                                                class="btn btn-icon btn-sm btn-outline-secondary flex-shrink-0"
                                                onclick="navigator.clipboard.writeText('{{ $activeConversation->contact_id }}')"
                                                title="Salin Contact ID"
                                            >
                                                <i class="bx bx-copy"></i>
                                            </button>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    @endif


                    {{-- ====================================================
                         ADDITIONAL FIELDS
                    ===================================================== --}}

                    @if ($extraFields->isNotEmpty())

                        <div
                            class="accordion-item"
                            data-field-group
                        >

                            <h2 class="accordion-header">

                                <button
                                    class="accordion-button collapsed"
                                    type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#accordion-additional"
                                >
                                    Additional Fields
                                </button>

                            </h2>


                            <div
                                id="accordion-additional"
                                class="accordion-collapse collapse"
                                data-bs-parent="#contactFieldsAccordion"
                            >

                                <div class="accordion-body p-3">

                                    @foreach ($extraFields as $label => $value)

                                        <div
                                            class="mb-3"
                                            data-field-row
                                        >

                                            <label class="form-label text-muted small fw-bold mb-1">
                                                {{ $label }}
                                            </label>

                                            <p class="form-control-plaintext small mb-0 text-break">
                                                {{ $value }}
                                            </p>

                                        </div>

                                    @endforeach

                                </div>

                            </div>

                        </div>

                    @endif


                    {{-- ====================================================
                         CUSTOM FIELDS
                    ===================================================== --}}

                    @if ($contactDetails && count($contactDetails->customFields ?? []) > 0)

                        <div
                            class="accordion-item"
                            data-field-group
                        >

                            <h2 class="accordion-header">

                                <button
                                    class="accordion-button collapsed"
                                    type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#accordion-custom"
                                >
                                    Custom Fields
                                </button>

                            </h2>


                            <div
                                id="accordion-custom"
                                class="accordion-collapse collapse"
                                data-bs-parent="#contactFieldsAccordion"
                            >

                                <div class="accordion-body p-3">

                                    @foreach ($contactDetails->customFields as $field)

                                        <div
                                            class="mb-3"
                                            data-field-row
                                        >

                                            <label class="form-label text-muted small fw-bold mb-1 text-break">
                                                {{ $field['key'] ?? $field['id'] ?? 'Custom Field' }}
                                            </label>

                                            <p class="form-control-plaintext small mb-0 text-break">

                                                {{
                                                    is_scalar($field['value'] ?? null)
                                                        ? ($field['value'] ?? '-')
                                                        : json_encode($field['value'] ?? null)
                                                }}

                                            </p>

                                        </div>

                                    @endforeach

                                </div>

                            </div>

                        </div>

                    @endif


                    {{-- ====================================================
                         PAYMENTS / ORDERS
                    ===================================================== --}}

                    <div
                        class="accordion-item"
                        data-field-group
                    >

                        <h2 class="accordion-header">

                            <button
                                class="accordion-button collapsed"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#accordion-payments"
                            >

                                <span>
                                    Payments

                                    @if (count($paymentHistory))
                                        <span class="badge bg-label-primary ms-1">
                                            {{ count($paymentHistory) }}
                                        </span>
                                    @endif

                                </span>

                            </button>

                        </h2>


                        <div
                            id="accordion-payments"
                            class="accordion-collapse collapse"
                            data-bs-parent="#contactFieldsAccordion"
                        >

                            <div class="accordion-body p-2">


                                @if (empty($paymentHistory))

                                    <div class="text-center py-3">

                                        <i class="bx bx-receipt fs-3 text-muted"></i>

                                        <p class="text-muted small mb-0 mt-1">
                                            No payment records found.
                                        </p>

                                    </div>

                                @else

                                    @foreach ($paymentHistory as $index => $payment)

                                        @php
                                            $status = strtolower(
                                                (string) $paymentStatus($payment)
                                            );

                                            $statusClass = match ($status) {
                                                'paid',
                                                'completed',
                                                'succeeded',
                                                'success' => 'success',

                                                'pending',
                                                'processing' => 'warning',

                                                'failed',
                                                'cancelled',
                                                'canceled',
                                                'refunded' => 'danger',

                                                default => 'secondary',
                                            };

                                            $product = $paymentProduct($payment);
                                            $amount = $formatPaymentAmount($payment);
                                            $method = $paymentMethod($payment);
                                            $paymentIdValue = $paymentId($payment);
                                            $receipt = $paymentReceipt($payment);
                                            $date = $paymentDate($payment);

                                            $type = ucfirst(
                                                $payment['type'] ?? 'payment'
                                            );
                                        @endphp


                                        <div
                                            class="border rounded p-3 mb-2"
                                            data-payment-record
                                        >

                                            {{-- Payment Header --}}

                                            <div
                                                class="d-flex justify-content-between align-items-start gap-2 mb-3"
                                            >

                                                <div class="min-w-0">

                                                    <div class="fw-semibold small">
                                                        Payment #{{ $index + 1 }}
                                                    </div>

                                                    <div class="text-muted small">
                                                        {{ $type }}
                                                    </div>

                                                </div>


                                                <span
                                                    class="badge bg-label-{{ $statusClass }} flex-shrink-0"
                                                >
                                                    {{ ucfirst($paymentStatus($payment)) }}
                                                </span>

                                            </div>


                                            {{-- Product --}}

                                            <div class="mb-3">

                                                <div class="text-muted text-uppercase fw-bold small mb-1">
                                                    Product
                                                </div>

                                                <div class="small text-break">
                                                    {{ $product }}
                                                </div>

                                            </div>


                                            {{-- Amount --}}

                                            <div class="mb-3">

                                                <div class="text-muted text-uppercase fw-bold small mb-1">
                                                    Amount
                                                </div>

                                                <div class="fw-semibold small">
                                                    {{ $amount }}
                                                </div>

                                            </div>


                                            {{-- Purchase Date --}}

                                            <div class="mb-3">

                                                <div class="text-muted text-uppercase fw-bold small mb-1">
                                                    Purchase Date
                                                </div>

                                                <div class="small text-break">
                                                    {{ $date }}
                                                </div>

                                            </div>


                                            {{-- Payment Method --}}

                                            <div class="mb-3">

                                                <div class="text-muted text-uppercase fw-bold small mb-1">
                                                    Payment Method
                                                </div>

                                                <div class="small text-break">
                                                    {{ $method }}
                                                </div>

                                            </div>


                                            {{-- Receipt --}}

                                            @if ($receipt)

                                                <div class="mb-3">

                                                    <div class="text-muted text-uppercase fw-bold small mb-1">
                                                        Receipt
                                                    </div>

                                                    <div class="d-flex align-items-center gap-1">

                                                        <code class="small text-break flex-grow-1">
                                                            {{ $receipt }}
                                                        </code>

                                                        <button
                                                            type="button"
                                                            class="btn btn-icon btn-sm btn-outline-secondary flex-shrink-0"
                                                            onclick="navigator.clipboard.writeText('{{ $receipt }}')"
                                                            title="Copy receipt"
                                                        >
                                                            <i class="bx bx-copy"></i>
                                                        </button>

                                                    </div>

                                                </div>

                                            @endif


                                            {{-- Order / Transaction ID --}}

                                            @if ($paymentIdValue !== '-')

                                                <div>

                                                    <div class="text-muted text-uppercase fw-bold small mb-1">
                                                        {{ ($payment['type'] ?? '') === 'order'
                                                            ? 'Order ID'
                                                            : 'Transaction ID' }}
                                                    </div>

                                                    <div class="d-flex align-items-center gap-1">

                                                        <code class="small text-break flex-grow-1">
                                                            {{ $paymentIdValue }}
                                                        </code>

                                                        <button
                                                            type="button"
                                                            class="btn btn-icon btn-sm btn-outline-secondary flex-shrink-0"
                                                            onclick="navigator.clipboard.writeText('{{ $paymentIdValue }}')"
                                                            title="Copy ID"
                                                        >
                                                            <i class="bx bx-copy"></i>
                                                        </button>

                                                    </div>

                                                </div>

                                            @endif


                                            {{-- Order Extra Details --}}

                                            @if (($payment['type'] ?? null) === 'order')

                                                @if (
                                                    isset($payment['subtotal'])
                                                    || isset($payment['discount'])
                                                    || isset($payment['tax'])
                                                    || isset($payment['shipping'])
                                                )

                                                    <div class="border-top mt-3 pt-3">

                                                        <div class="text-muted text-uppercase fw-bold small mb-2">
                                                            Order Details
                                                        </div>


                                                        @if (isset($payment['subtotal']))

                                                            <div class="d-flex justify-content-between gap-2 mb-1">

                                                                <span class="text-muted small">
                                                                    Subtotal
                                                                </span>

                                                                <span class="small text-end">
                                                                    {{ $payment['subtotal'] }}
                                                                </span>

                                                            </div>

                                                        @endif


                                                        @if (isset($payment['discount']))

                                                            <div class="d-flex justify-content-between gap-2 mb-1">

                                                                <span class="text-muted small">
                                                                    Discount
                                                                </span>

                                                                <span class="small text-end">
                                                                    {{ $payment['discount'] }}
                                                                </span>

                                                            </div>

                                                        @endif


                                                        @if (isset($payment['tax']))

                                                            <div class="d-flex justify-content-between gap-2 mb-1">

                                                                <span class="text-muted small">
                                                                    Tax
                                                                </span>

                                                                <span class="small text-end">
                                                                    {{ $payment['tax'] }}
                                                                </span>

                                                            </div>

                                                        @endif


                                                        @if (isset($payment['shipping']))

                                                            <div class="d-flex justify-content-between gap-2">

                                                                <span class="text-muted small">
                                                                    Shipping
                                                                </span>

                                                                <span class="small text-end">
                                                                    {{ $payment['shipping'] }}
                                                                </span>

                                                            </div>

                                                        @endif

                                                    </div>

                                                @endif

                                            @endif

                                        </div>

                                    @endforeach

                                @endif

                            </div>

                        </div>

                    </div>


                </div>

            </div>


            {{-- ============================================================
                 DND
            ============================================================= --}}

            <div
                class="tab-pane fade"
                id="tab-dnd"
                role="tabpanel"
            >

                @if ($contactDetails?->dnd)

                    <span class="badge bg-label-danger">

                        <i class="bx bx-bell-off me-1"></i>

                        Do Not Disturb aktif

                    </span>

                    <p class="text-muted small mt-2 mb-0">
                        Kontak ini telah memilih untuk tidak menerima
                        pesan/panggilan dari channel yang didukung GHL.
                    </p>

                @else

                    <p class="text-muted small mb-0">
                        Do Not Disturb tidak aktif untuk kontak ini.
                    </p>

                @endif

            </div>


            {{-- ============================================================
                 ACTIONS
            ============================================================= --}}

            <div
                class="tab-pane fade"
                id="tab-actions"
                role="tabpanel"
            >


                {{-- AI Analysis --}}

                <div class="card bg-label-primary border-0 mb-3">

                    <div class="card-body p-3">

                        <h6 class="fw-bold text-primary mb-2">
                            <i class="bx bx-brain me-1"></i>
                            AI Analysis
                        </h6>

                        <div id="ai-analysis-card">

                            @include(
                                'inbox.components.analysis-card',
                                [
                                    'analysis' => $activeConversation->analysis,
                                ]
                            )

                        </div>

                    </div>

                </div>


                {{-- Attachments --}}

                @if ($allAttachments->isNotEmpty())

                    <div class="mb-3">

                        <label class="form-label text-muted small fw-bold mb-1">
                            ATTACHMENTS
                            ({{ $allAttachments->count() }})
                        </label>

                        <div class="d-flex flex-column gap-1">

                            @foreach ($allAttachments as $pair)

                                @include(
                                    'inbox.components.attachment',
                                    [
                                        'message' => $pair['message'],
                                        'attachment' => $pair['attachment'],
                                    ]
                                )

                            @endforeach

                        </div>

                    </div>

                @endif


                {{-- Draft History --}}

                @if ($draftHistory->isNotEmpty())

                    <div class="mb-3">

                        <label class="form-label text-muted small fw-bold mb-1">
                            RIWAYAT DRAFT
                        </label>

                        <ul class="list-unstyled small mb-0">

                            @foreach ($draftHistory as $draft)

                                <li
                                    class="d-flex justify-content-between align-items-center border-bottom py-2 gap-2"
                                >

                                    <span>
                                        v{{ $draft->version }}
                                    </span>

                                    <span
                                        class="badge bg-label-{{
                                            $draft->status === \App\Enums\DraftStatus::Active
                                                ? 'primary'
                                                : 'secondary'
                                        }}"
                                    >
                                        {{ ucfirst($draft->status->value) }}
                                    </span>

                                    <span class="text-muted text-nowrap">
                                        {{ $draft->created_at->format('d M, H:i') }}
                                    </span>

                                </li>

                            @endforeach

                        </ul>

                    </div>

                @endif


            </div>

        </div>

    </div>

@else

    <div
        class="d-flex align-items-center justify-content-center h-100 text-muted p-4"
    >

        <p class="mb-0 small text-center">
            Pilih percakapan untuk melihat Contact Details.
        </p>

    </div>

@endif
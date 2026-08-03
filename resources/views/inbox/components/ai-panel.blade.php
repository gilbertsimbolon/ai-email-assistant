@if ($activeConversation)
    @php
        $contactDetails = $contactDetails ?? null;

        $displayName = $contactDetails?->fullName() ?? $activeConversation->contact_name;
        $displayEmail = $contactDetails?->email ?? $activeConversation->contact_email;
        $displayPhone = $contactDetails?->phone ?? $activeConversation->contact_phone;

        $allAttachments = $activeConversation->messages
            ->flatMap(fn ($m) => collect($m->attachments ?? [])->map(fn ($a) => ['message' => $m, 'attachment' => $a]));

        $draftHistory = $activeConversation->drafts->sortByDesc('version');

        // Only fields GHL actually returned are shown — never fabricate a
        // placeholder for a field that simply isn't in the response.
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

        // Best-effort calling-code split for the "country code selector"
        // look — GHL only stores one phone string, never a separate code,
        // so this is purely a display split of the real number, not a
        // fabricated/editable field.
        $phoneCode = null;
        $phoneRest = $displayPhone;
        if ($displayPhone && preg_match('/^(\+\d{1,3})[\s-]?(.*)$/', $displayPhone, $m)) {
            $phoneCode = $m[1];
            $phoneRest = $m[2];
        }
    @endphp

    <div class="p-3 overflow-auto flex-grow-1">

        {{-- Profile Summary --}}
        <div class="d-flex flex-column align-items-center text-center mb-3">
            <div class="avatar avatar-lg mb-2">
                <span class="avatar-initial rounded-circle bg-label-primary fs-4">
                    {{ strtoupper(substr($displayName ?? $displayEmail ?? 'P', 0, 1)) }}
                </span>
            </div>
            <p class="mb-0 fw-semibold">{{ $displayName ?? '-' }}</p>
            <p class="mb-0 text-muted small">{{ $displayEmail ?? '-' }}</p>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-6">
                <label class="form-label text-muted small fw-bold mb-0">OWNER</label>
                <select class="form-select form-select-sm" disabled>
                    <option>{{ $contactDetails?->assignedTo ?? 'Belum ditugaskan' }}</option>
                </select>
            </div>
            <div class="col-6">
                <label class="form-label text-muted small fw-bold mb-0">FOLLOWERS</label>
                <select class="form-select form-select-sm" disabled>
                    <option>-</option>
                </select>
            </div>
        </div>

        {{-- Tags --}}
        <div class="mb-3">
            <label class="form-label text-muted small fw-bold mb-2 d-block">
                TAGS @if ($contactDetails) ({{ count($contactDetails->tags) }}) @endif
            </label>
            @if (! $contactDetails)
                <p class="text-muted small mb-0">Tags belum tersedia.</p>
            @elseif (empty($contactDetails->tags))
                <p class="text-muted small mb-0">Tidak ada tags.</p>
            @else
                <div class="d-flex flex-wrap gap-1">
                    @foreach ($contactDetails->tags as $tag)
                        <span class="badge bg-label-primary rounded-pill">{{ $tag }}</span>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Tab Navigation --}}
        <ul class="nav nav-pills nav-sm mb-3 contact-details-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-all-fields" type="button" role="tab">All fields</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-dnd" type="button" role="tab">DND</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-actions" type="button" role="tab">Actions</button>
            </li>
        </ul>

        <div class="tab-content">

            {{-- Tab: All Fields --}}
            <div class="tab-pane fade show active" id="tab-all-fields" role="tabpanel">

                <div class="input-group input-group-merge mb-3">
                    <span class="input-group-text bg-transparent border-end-0"><i class="bx bx-search"></i></span>
                    <input type="text" id="contactFieldSearch" class="form-control border-start-0 ps-0 form-control-sm"
                           placeholder="Search fields and folders">
                </div>

                <div class="accordion" id="contactFieldsAccordion">
                    <div class="accordion-item" data-field-group>
                        <h2 class="accordion-header">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#accordion-contact">
                                Contact
                            </button>
                        </h2>
                        <div id="accordion-contact" class="accordion-collapse collapse show" data-bs-parent="#contactFieldsAccordion">
                            <div class="accordion-body">
                                <div class="row g-2 mb-2" data-field-row>
                                    <label class="col-5 col-form-label text-muted small">First Name</label>
                                    <div class="col-7"><p class="form-control-plaintext form-control-sm mb-0 small text-truncate">{{ $contactDetails?->firstName ?? '-' }}</p></div>
                                </div>
                                <div class="row g-2 mb-2" data-field-row>
                                    <label class="col-5 col-form-label text-muted small">Last Name</label>
                                    <div class="col-7"><p class="form-control-plaintext form-control-sm mb-0 small text-truncate">{{ $contactDetails?->lastName ?? '-' }}</p></div>
                                </div>
                                <div class="row g-2 mb-2 align-items-center" data-field-row>
                                    <label class="col-5 col-form-label text-muted small">Email</label>
                                    <div class="col-7 d-flex align-items-center gap-1 overflow-hidden">
                                        <p class="form-control-plaintext form-control-sm mb-0 small text-truncate flex-grow-1">{{ $displayEmail ?? '-' }}</p>
                                        @if ($displayEmail)
                                            <a href="mailto:{{ $displayEmail }}" class="btn btn-icon btn-sm btn-outline-secondary flex-shrink-0" title="Kirim email">
                                                <i class="bx bx-envelope"></i>
                                            </a>
                                        @endif
                                    </div>
                                </div>
                                <div class="row g-2 mb-2 align-items-center" data-field-row>
                                    <label class="col-5 col-form-label text-muted small">Phone</label>
                                    <div class="col-7">
                                        @if ($displayPhone)
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text" title="Country code (dari nomor tersimpan)">{{ $phoneCode ?? '+?' }}</span>
                                                <p class="form-control form-control-sm form-control-plaintext mb-0 small text-truncate ps-2">{{ $phoneRest }}</p>
                                            </div>
                                        @else
                                            <p class="form-control-plaintext form-control-sm mb-0 small">-</p>
                                        @endif
                                    </div>
                                </div>
                                <div class="row g-2" data-field-row>
                                    <label class="col-5 col-form-label text-muted small">Date of Birth</label>
                                    <div class="col-7"><p class="form-control-plaintext form-control-sm mb-0 small text-truncate">{{ $contactDetails?->dateOfBirth ?? '-' }}</p></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if ($activeConversation->contact_id)
                        <div class="accordion-item" data-field-group>
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#accordion-identifiers">
                                    Identifiers
                                </button>
                            </h2>
                            <div id="accordion-identifiers" class="accordion-collapse collapse" data-bs-parent="#contactFieldsAccordion">
                                <div class="accordion-body">
                                    <div class="row g-2 align-items-center" data-field-row>
                                        <label class="col-5 col-form-label text-muted small">Contact ID</label>
                                        <div class="col-7 d-flex align-items-center gap-1 overflow-hidden">
                                            <code class="small text-truncate flex-grow-1">{{ $activeConversation->contact_id }}</code>
                                            <button type="button" class="btn btn-icon btn-sm btn-outline-secondary flex-shrink-0"
                                                    onclick="navigator.clipboard.writeText('{{ $activeConversation->contact_id }}')" title="Salin Contact ID">
                                                <i class="bx bx-copy"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($extraFields->isNotEmpty())
                        <div class="accordion-item" data-field-group>
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#accordion-additional">
                                    Additional Fields
                                </button>
                            </h2>
                            <div id="accordion-additional" class="accordion-collapse collapse" data-bs-parent="#contactFieldsAccordion">
                                <div class="accordion-body">
                                    @foreach ($extraFields as $label => $value)
                                        <div class="row g-2 mb-2" data-field-row>
                                            <label class="col-5 col-form-label text-muted small">{{ $label }}</label>
                                            <div class="col-7"><p class="form-control-plaintext form-control-sm mb-0 small text-truncate">{{ $value }}</p></div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($contactDetails && count($contactDetails->customFields) > 0)
                        <div class="accordion-item" data-field-group>
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#accordion-custom">
                                    Custom Fields
                                </button>
                            </h2>
                            <div id="accordion-custom" class="accordion-collapse collapse" data-bs-parent="#contactFieldsAccordion">
                                <div class="accordion-body">
                                    @foreach ($contactDetails->customFields as $field)
                                        <div class="row g-2 mb-2" data-field-row>
                                            <label class="col-5 col-form-label text-muted small text-truncate">{{ $field['key'] ?? $field['id'] }}</label>
                                            <div class="col-7"><p class="form-control-plaintext form-control-sm mb-0 small text-truncate">{{ is_scalar($field['value']) ? $field['value'] : json_encode($field['value']) }}</p></div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Tab: DND --}}
            <div class="tab-pane fade" id="tab-dnd" role="tabpanel">
                @if ($contactDetails?->dnd)
                    <span class="badge bg-label-danger"><i class="bx bx-bell-off me-1"></i>Do Not Disturb aktif</span>
                    <p class="text-muted small mt-2 mb-0">Kontak ini telah memilih untuk tidak menerima pesan/panggilan dari channel yang didukung GHL.</p>
                @else
                    <p class="text-muted small mb-0">Do Not Disturb tidak aktif untuk kontak ini.</p>
                @endif
            </div>

            {{-- Tab: Actions --}}
            <div class="tab-pane fade" id="tab-actions" role="tabpanel">

                {{-- AI Analysis --}}
                <div class="card bg-label-primary border-0 mb-3">
                    <div class="card-body p-3">
                        <h6 class="fw-bold text-primary mb-2"><i class="bx bx-brain me-1"></i> AI Analysis</h6>

                        <div id="ai-analysis-card">
                            @include('inbox.components.analysis-card', ['analysis' => $activeConversation->analysis])
                        </div>
                    </div>
                </div>

                @if ($allAttachments->isNotEmpty())
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold mb-0">ATTACHMENTS ({{ $allAttachments->count() }})</label>
                        <div class="d-flex flex-column gap-1 mt-1">
                            @foreach ($allAttachments as $pair)
                                @include('inbox.components.attachment', ['message' => $pair['message'], 'attachment' => $pair['attachment']])
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($draftHistory->isNotEmpty())
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold mb-0">RIWAYAT DRAFT</label>
                        <ul class="list-unstyled small mb-0 mt-1">
                            @foreach ($draftHistory as $draft)
                                <li class="d-flex justify-content-between border-bottom py-1">
                                    <span>v{{ $draft->version }}</span>
                                    <span class="badge bg-label-{{ $draft->status === \App\Enums\DraftStatus::Active ? 'primary' : 'secondary' }}">
                                        {{ ucfirst($draft->status->value) }}
                                    </span>
                                    <span class="text-muted">{{ $draft->created_at->format('d M, H:i') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>

    </div>
@else
    <div class="d-flex align-items-center justify-content-center h-100 text-muted p-4">
        <p class="mb-0 small">Pilih percakapan untuk melihat Contact Details.</p>
    </div>
@endif

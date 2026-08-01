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
            'Assigned User' => $contactDetails?->assignedTo,
            'Date Added' => $contactDetails?->dateAdded,
            'Date Updated' => $contactDetails?->dateUpdated,
        ])->filter(fn ($value) => filled($value));
    @endphp

    <div class="p-3 overflow-auto flex-grow-1">

        {{-- Contact --}}
        <div class="d-flex align-items-center mb-3">
            <div class="avatar avatar-sm me-2">
                <span class="avatar-initial rounded-circle bg-label-primary">
                    {{ strtoupper(substr($displayName ?? $displayEmail ?? 'P', 0, 1)) }}
                </span>
            </div>
            <div class="overflow-hidden">
                <p class="mb-0 fw-semibold text-truncate">{{ $displayName ?? '-' }}</p>
                <p class="mb-0 text-muted small text-truncate">{{ $displayEmail ?? '-' }}</p>
            </div>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-6">
                <label class="form-label text-muted small fw-bold mb-0">PHONE</label>
                <p class="mb-0 small">{{ $displayPhone ?? '-' }}</p>
            </div>
            @if ($contactDetails?->dateOfBirth)
                <div class="col-6">
                    <label class="form-label text-muted small fw-bold mb-0">DATE OF BIRTH</label>
                    <p class="mb-0 small">{{ $contactDetails->dateOfBirth }}</p>
                </div>
            @endif
        </div>

        @if ($activeConversation->contact_id)
            <div class="mb-3">
                <label class="form-label text-muted small fw-bold mb-0">CONTACT ID</label>
                <div class="d-flex align-items-center gap-2">
                    <code class="small text-truncate">{{ $activeConversation->contact_id }}</code>
                    <button type="button" class="btn btn-icon btn-sm btn-outline-secondary flex-shrink-0"
                            onclick="navigator.clipboard.writeText('{{ $activeConversation->contact_id }}')" title="Salin Contact ID">
                        <i class="bx bx-copy"></i>
                    </button>
                </div>
            </div>
        @endif

        @if ($contactDetails?->dnd)
            <div class="mb-3">
                <span class="badge bg-label-danger"><i class="bx bx-bell-off me-1"></i>Do Not Disturb</span>
            </div>
        @endif

        {{-- Tags --}}
        <div class="border-top pt-3 mb-3">
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
                        <span class="badge bg-label-primary">{{ $tag }}</span>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- All fields (Company, Address, DND, dsb — hanya field yang tersedia dari GHL) --}}
        @if ($extraFields->isNotEmpty())
            <div class="border-top pt-3 mb-3">
                <label class="form-label text-muted small fw-bold mb-2 d-block">ALL FIELDS</label>
                <dl class="row mb-0 small">
                    @foreach ($extraFields as $label => $value)
                        <dt class="col-5 text-muted fw-normal">{{ $label }}</dt>
                        <dd class="col-7 text-truncate mb-1">{{ $value }}</dd>
                    @endforeach
                </dl>
            </div>
        @endif

        @if ($contactDetails && count($contactDetails->customFields) > 0)
            <div class="border-top pt-3 mb-3">
                <label class="form-label text-muted small fw-bold mb-2 d-block">CUSTOM FIELDS</label>
                <dl class="row mb-0 small">
                    @foreach ($contactDetails->customFields as $field)
                        <dt class="col-5 text-muted fw-normal text-truncate">{{ $field['key'] ?? $field['id'] }}</dt>
                        <dd class="col-7 text-truncate mb-1">{{ is_scalar($field['value']) ? $field['value'] : json_encode($field['value']) }}</dd>
                    @endforeach
                </dl>
            </div>
        @endif

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
@else
    <div class="d-flex align-items-center justify-content-center h-100 text-muted p-4">
        <p class="mb-0 small">Pilih percakapan untuk melihat Contact Details.</p>
    </div>
@endif

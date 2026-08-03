{{-- Toolbar percakapan: identitas + status di baris atas, action icons di baris bawah.
     Reply All/Forward/Archive/Delete/Labels belum punya backend pendukung — ditampilkan
     nonaktif dengan tooltip "Segera hadir" (pola sama seperti badge "Soon" di sidebar). --}}
<div class="conversation-toolbar border-bottom bg-white flex-shrink-0">

    <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 px-4 py-3">
        <div class="d-flex align-items-center overflow-hidden">
            <a href="{{ route('inbox.index') }}" class="btn btn-icon btn-sm btn-outline-secondary d-lg-none me-2 flex-shrink-0" title="Kembali ke daftar">
                <i class="bx bx-arrow-back"></i>
            </a>

            <div class="avatar avatar-sm me-2 flex-shrink-0">
                <span class="avatar-initial rounded-circle bg-label-primary text-primary fw-bold">
                    {{ strtoupper(substr($activeConversation->contact_name ?? $activeConversation->contact_email ?? 'P', 0, 2)) }}
                </span>
            </div>

            <div class="overflow-hidden">
                <span class="fw-bold fs-5">{{ $activeConversation->contact_name ?? ($activeConversation->contact_email ?? 'Pelanggan') }}</span>
                <div class="text-muted small mt-1 text-truncate">
                    <i class="bx bx-envelope me-1"></i>{{ $activeConversation->contact_email ?? 'Tidak ada email' }}
                    @if ($activeConversation->contact_phone)
                        &middot; <i class="bx bx-phone me-1"></i>{{ $activeConversation->contact_phone }}
                    @endif
                    &middot; {{ $activeConversation->subject ?: '(Tanpa subjek)' }}
                </div>
            </div>
        </div>

        {{-- Quick actions: Telepon, Bintang, Email, Delete, Collapse --}}
        <div class="d-flex align-items-center gap-1 flex-shrink-0">
            <a href="{{ $activeConversation->contact_phone ? 'tel:'.$activeConversation->contact_phone : 'javascript:void(0);' }}"
               class="btn btn-icon btn-sm btn-outline-secondary {{ $activeConversation->contact_phone ? '' : 'disabled' }}"
               title="{{ $activeConversation->contact_phone ?: 'Tidak ada nomor telepon' }}">
                <i class="bx bx-phone"></i>
            </a>

            <a href="javascript:void(0);" class="btn btn-icon btn-sm btn-outline-secondary" title="Bintang" onclick="toggleStar(event, this, {{ $activeConversation->id }})">
                <i class="bx {{ $activeConversation->is_starred ? 'bxs-star text-warning' : 'bx-star' }}"></i>
            </a>

            <a href="{{ $activeConversation->contact_email ? 'mailto:'.$activeConversation->contact_email : 'javascript:void(0);' }}"
               class="btn btn-icon btn-sm btn-outline-secondary {{ $activeConversation->contact_email ? '' : 'disabled' }}"
               title="{{ $activeConversation->contact_email ?: 'Tidak ada email' }}">
                <i class="bx bx-envelope"></i>
            </a>

            <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" disabled data-bs-toggle="tooltip" data-bs-placement="top" title="Segera hadir">
                <i class="bx bx-trash"></i>
            </button>

            <button type="button" class="btn btn-icon btn-sm btn-outline-secondary d-xl-none" data-bs-toggle="offcanvas" data-bs-target="#infoOffcanvas" title="Info percakapan">
                <i class="bx bx-info-circle"></i>
            </button>

            <button type="button" id="btn-toggle-ai-panel" class="btn btn-icon btn-sm btn-outline-secondary d-none d-xl-inline-flex" title="Sembunyikan/Tampilkan AI Panel (Collapse)">
                <i class="bx bx-sidebar"></i>
            </button>
        </div>
    </div>

    <div class="d-flex align-items-center px-4 pb-2">
        <form action="{{ route('inbox.status.update', $activeConversation) }}" method="POST" class="mb-0">
            @csrf
            @method('PUT')
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" title="Ubah status percakapan">
                @foreach (\App\Enums\ConversationStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected($activeConversation->status === $status)>
                        {{ ucwords(str_replace('_', ' ', $status->value)) }}
                    </option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="d-flex align-items-center gap-1 px-4 pb-2 flex-wrap">
        <button type="button" id="btn-toolbar-reply" class="btn btn-sm btn-primary">
            <i class="bx bx-reply me-1"></i> Reply
        </button>

        <div class="dropdown">
            <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bx bx-bulb me-1"></i> AI Actions
            </button>
            <ul class="dropdown-menu">
                <li>
                    <a class="dropdown-item" href="javascript:void(0);" id="btn-toolbar-generate">
                        <i class="bx bx-magic-wand me-2"></i>Generate / Regenerate Reply
                    </a>
                </li>
            </ul>
        </div>

        <a href="javascript:void(0);" class="btn btn-icon btn-sm btn-outline-secondary" title="Tandai belum dibaca" onclick="toggleRead(event, this, {{ $activeConversation->id }})">
            <i class="bx bx-envelope-open"></i>
        </a>

        <div class="dropdown ms-auto">
            <button type="button" class="btn btn-icon btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="More">
                <i class="bx bx-dots-vertical-rounded"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item disabled" href="javascript:void(0);"><i class="bx bx-reply-all me-2"></i>Reply All <span class="badge bg-label-secondary ms-1">Soon</span></a></li>
                <li><a class="dropdown-item disabled" href="javascript:void(0);"><i class="bx bx-share me-2"></i>Forward <span class="badge bg-label-secondary ms-1">Soon</span></a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item disabled" href="javascript:void(0);"><i class="bx bx-archive-in me-2"></i>Archive <span class="badge bg-label-secondary ms-1">Soon</span></a></li>
                <li><a class="dropdown-item disabled" href="javascript:void(0);"><i class="bx bx-trash me-2"></i>Delete <span class="badge bg-label-secondary ms-1">Soon</span></a></li>
                <li><a class="dropdown-item disabled" href="javascript:void(0);"><i class="bx bx-purchase-tag-alt me-2"></i>Labels <span class="badge bg-label-secondary ms-1">Soon</span></a></li>
            </ul>
        </div>
    </div>
</div>

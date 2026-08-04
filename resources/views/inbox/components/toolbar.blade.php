{{-- Conversation Toolbar --}}
<div class="conversation-toolbar border-bottom bg-white flex-shrink-0">

    @php
        $toolbarName =
            $contactDetails?->fullName()
            ?: $activeConversation->contact_name
            ?: $activeConversation->contact_email
            ?: 'Pelanggan';

        $toolbarEmail =
            $contactDetails?->email
            ?: $activeConversation->contact_email;
    @endphp

    <div class="d-flex align-items-center justify-content-between px-4 py-2 gap-2">

        {{-- =========================================================
             LEFT
        ========================================================== --}}
        <div class="d-flex align-items-center overflow-hidden flex-grow-1">

            {{-- Back mobile --}}
            <a href="{{ route('inbox.index') }}"
                class="btn btn-icon btn-sm btn-outline-secondary d-lg-none me-2 flex-shrink-0"
                title="Kembali ke daftar">

                <i class="bx bx-arrow-back"></i>
            </a>

            {{-- Avatar --}}
            <div class="avatar avatar-sm me-2 flex-shrink-0">
                <span class="avatar-initial rounded-circle bg-label-primary text-primary fw-bold">
                    {{ strtoupper(substr($toolbarName, 0, 2)) }}
                </span>
            </div>

            {{-- Name --}}
            <div class="overflow-hidden flex-grow-1">

                <div
                    class="fw-semibold text-dark text-truncate"
                    title="{{ $toolbarName }}"
                >
                    {{ $toolbarName }}
                </div>

            </div>

        </div>


        {{-- =========================================================
             QUICK ACTIONS
        ========================================================== --}}
        <div class="d-flex align-items-center gap-1 flex-shrink-0">

            {{-- Star --}}
            <button
                type="button"
                class="btn btn-icon btn-sm btn-outline-secondary"
                title="Bintang"
                onclick="toggleStar(event, this, {{ $activeConversation->id }})"
            >
                <i class="bx {{ $activeConversation->is_starred ? 'bxs-star text-warning' : 'bx-star' }}"></i>
            </button>


            {{-- Status --}}
            <form
                action="{{ route('inbox.status.update', $activeConversation) }}"
                method="POST"
                class="mb-0"
            >
                @csrf
                @method('PUT')

                <select
                    name="status"
                    class="form-select form-select-sm"
                    onchange="this.form.submit()"
                    title="Ubah status percakapan"
                >
                    @foreach (\App\Enums\ConversationStatus::cases() as $status)
                        <option
                            value="{{ $status->value }}"
                            @selected($activeConversation->status === $status)
                        >
                            {{ ucwords(str_replace('_', ' ', $status->value)) }}
                        </option>
                    @endforeach
                </select>
            </form>


            {{-- Mark Read / Unread --}}
            <button
                type="button"
                class="btn btn-icon btn-sm btn-outline-secondary"
                title="Tandai belum dibaca"
                onclick="toggleRead(event, this, {{ $activeConversation->id }})"
            >
                <i class="bx bx-envelope-open"></i>
            </button>

        </div>

    </div>

</div>
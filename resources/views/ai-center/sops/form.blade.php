@extends('layouts.app')

@section('title', ($sop->exists ? 'Edit' : 'Tambah').' SOP | AI Center')

@section('content')
    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">{{ $sop->exists ? 'Edit' : 'Tambah' }} SOP</h5></div>
                <div class="card-body">
                    @if (session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    <form action="{{ $sop->exists ? route('ai-center.sops.update', $sop) : route('ai-center.sops.store') }}" method="POST">
                        @csrf
                        @if ($sop->exists) @method('PUT') @endif

                        <div class="mb-6">
                            <label class="form-label">Nama SOP</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                value="{{ old('name', $sop->name) }}" required>
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-3 mb-6">
                                <label class="form-label">Kategori</label>
                                <select name="category_id" class="form-select">
                                    <option value="">-</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" {{ (int) old('category_id', $sop->category_id) === $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-6">
                                <label class="form-label">Intent</label>
                                <select name="intent_id" class="form-select">
                                    <option value="">-</option>
                                    @foreach ($intents as $intent)
                                        <option value="{{ $intent->id }}" {{ (int) old('intent_id', $sop->intent_id) === $intent->id ? 'selected' : '' }}>
                                            {{ $intent->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-6">
                                <label class="form-label">Priority</label>
                                <select name="priority" class="form-select">
                                    @foreach (\App\Enums\AiCenter\PriorityLevel::cases() as $level)
                                        <option value="{{ $level->value }}" {{ old('priority', $sop->priority?->value ?? 'medium') === $level->value ? 'selected' : '' }}>
                                            {{ $level->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-6">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    @foreach (\App\Enums\AiCenter\PublishStatus::cases() as $status)
                                        <option value="{{ $status->value }}" {{ old('status', $sop->status?->value ?? 'draft') === $status->value ? 'selected' : '' }}>
                                            {{ ucfirst($status->value) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="mb-6">
                            <label class="form-label">Deskripsi</label>
                            <textarea name="description" class="form-control" rows="2">{{ old('description', $sop->description) }}</textarea>
                        </div>

                        <div class="mb-6">
                            <label class="form-label">Channel (kosongkan untuk semua channel)</label>
                            <div>
                                @foreach ($channelOptions as $channel)
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="channels[]" value="{{ $channel->value }}"
                                            id="channel-{{ $channel->value }}"
                                            {{ in_array($channel->value, old('channels', $sop->channels ?? []), true) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="channel-{{ $channel->value }}">{{ $channel->label() }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-6">
                                <label class="form-label">Workflow</label>
                                <select name="workflow_id" class="form-select">
                                    <option value="">- Tanpa Workflow -</option>
                                    @foreach ($workflows as $workflow)
                                        <option value="{{ $workflow->id }}" {{ (int) old('workflow_id', $sop->workflow_id) === $workflow->id ? 'selected' : '' }}>
                                            {{ $workflow->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 mb-6">
                                <label class="form-label">Reply Template Default</label>
                                <select name="reply_template_id" class="form-select">
                                    <option value="">- Tanpa Template -</option>
                                    @foreach ($replyTemplates as $template)
                                        <option value="{{ $template->id }}" {{ (int) old('reply_template_id', $sop->reply_template_id) === $template->id ? 'selected' : '' }}>
                                            {{ $template->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="mb-6">
                            <label class="form-label d-flex justify-content-between">
                                Trigger (contoh kalimat pemicu)
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addTriggerRow()">+ Tambah</button>
                            </label>
                            <div id="triggers-rows">
                                @forelse (old('triggers', $sop->relationLoaded('triggers') ? $sop->triggers->pluck('phrase')->all() : []) ?: [''] as $phrase)
                                    <div class="input-group mb-2">
                                        <input type="text" name="triggers[]" class="form-control" value="{{ $phrase }}" placeholder="mis. customer meminta refund">
                                        <button type="button" class="btn btn-outline-danger" onclick="this.closest('.input-group').remove()">&times;</button>
                                    </div>
                                @endforelse
                            </div>
                        </div>

                        <div class="mb-6">
                            <label class="form-label">Knowledge Base</label>
                            <div>
                                @forelse ($knowledgeBases as $kb)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="knowledge_base_ids[]" value="{{ $kb->id }}"
                                            id="kb-{{ $kb->id }}"
                                            {{ in_array($kb->id, old('knowledge_base_ids', $sop->relationLoaded('knowledgeBases') ? $sop->knowledgeBases->pluck('id')->all() : []), true) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="kb-{{ $kb->id }}">{{ $kb->title }}</label>
                                    </div>
                                @empty
                                    <small class="text-body">Belum ada Knowledge Base.</small>
                                @endforelse
                            </div>
                        </div>

                        <div class="mb-6">
                            <label class="form-label">Forbidden Actions</label>
                            <div>
                                @forelse ($forbiddenActions as $forbidden)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="forbidden_action_ids[]" value="{{ $forbidden->id }}"
                                            id="forbidden-{{ $forbidden->id }}"
                                            {{ in_array($forbidden->id, old('forbidden_action_ids', $sop->relationLoaded('forbiddenActions') ? $sop->forbiddenActions->pluck('id')->all() : []), true) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="forbidden-{{ $forbidden->id }}">{{ $forbidden->label }}</label>
                                    </div>
                                @empty
                                    <small class="text-body">Belum ada Forbidden Action.</small>
                                @endforelse
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            <a href="{{ route('ai-center.sops.index') }}" class="btn btn-outline-secondary">Batal</a>
                        </div>
                    </form>
                </div>
            </div>

            @if ($sop->exists)
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Rule Builder</h5>
                        <a href="{{ route('ai-center.sops.rules.create', $sop) }}" class="btn btn-primary btn-sm">+ Tambah Rule</a>
                    </div>
                    <div class="card-body">
                        <div class="accordion" id="rules-accordion">
                            @forelse ($sop->rules as $rule)
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                            data-bs-target="#rule-{{ $rule->id }}">
                                            #{{ $rule->order + 1 }} — {{ $rule->name ?: 'Rule tanpa nama' }}
                                        </button>
                                    </h2>
                                    <div id="rule-{{ $rule->id }}" class="accordion-collapse collapse" data-bs-parent="#rules-accordion">
                                        <div class="accordion-body">
                                            <p class="mb-2"><strong>IF</strong>
                                                @forelse ($rule->conditions as $condition)
                                                    {{ $loop->first ? '' : strtoupper($condition->boolean_operator->value) }}
                                                    {{ $condition->field->label() }} {{ $condition->operator->label() }} {{ $condition->value }}
                                                @empty
                                                    <em>selalu (tanpa kondisi)</em>
                                                @endforelse
                                            </p>
                                            <p class="mb-2"><strong>THEN</strong>
                                                {{ $rule->actions->map(fn ($a) => $a->action_type->label())->implode(', ') ?: '-' }}
                                            </p>
                                            @if ($rule->tone)
                                                <p class="mb-2"><strong>Tone:</strong> {{ $rule->tone->label() }}</p>
                                            @endif
                                            @if ($rule->escalation_target)
                                                <p class="mb-2"><strong>Escalate:</strong> {{ $rule->escalation_target->label() }}</p>
                                            @endif

                                            <a href="{{ route('ai-center.sops.rules.edit', $rule) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                            <form action="{{ route('ai-center.sops.rules.destroy', $rule) }}" method="POST" class="d-inline"
                                                onsubmit="return confirm('Hapus rule ini?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <p class="text-body mb-0">Belum ada Rule. SOP tanpa Rule akan selalu menghasilkan Generate Reply biasa.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <script>
        function addTriggerRow() {
            const container = document.getElementById('triggers-rows');
            const row = document.createElement('div');
            row.className = 'input-group mb-2';
            row.innerHTML = '<input type="text" name="triggers[]" class="form-control">' +
                '<button type="button" class="btn btn-outline-danger" onclick="this.closest(\'.input-group\').remove()">&times;</button>';
            container.appendChild(row);
        }
    </script>
@endsection

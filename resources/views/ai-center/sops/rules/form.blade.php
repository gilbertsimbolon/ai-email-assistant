@extends('layouts.app')

@section('title', ($rule->exists ? 'Edit' : 'Tambah') . ' Rule | AI Center')

@section('content')
    <div class="row">
        <div class="col-md-9">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bx bx-git-branch me-1 text-primary"></i>
                        {{ $rule->exists ? 'Edit' : 'Tambah' }} Rule — SOP: {{ $sop->name }}</h5>
                </div>
                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form
                        action="{{ $rule->exists ? route('ai-center.rules.update', $rule) : route('ai-center.sops.rules.store', $sop) }}"
                        method="POST">
                        @csrf
                        @if ($rule->exists)
                            @method('PUT')
                        @endif

                        <div class="row">
                            <div class="col-md-6 mb-6">
                                <label class="form-label">Nama Rule (opsional)</label>
                                <input type="text" name="name" class="form-control"
                                    value="{{ old('name', $rule->name) }}">
                            </div>
                            <div class="col-md-3 mb-6">
                                <label class="form-label">Tone</label>
                                <select name="tone" class="form-select">
                                    <option value="">-</option>
                                    @foreach ($tones as $tone)
                                        <option value="{{ $tone->value }}"
                                            {{ old('tone', $rule->tone?->value) === $tone->value ? 'selected' : '' }}>
                                            {{ $tone->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-6">
                                <label class="form-label">Escalation Target</label>
                                <select name="escalation_target" class="form-select">
                                    <option value="">-</option>
                                    @foreach ($escalationTargets as $target)
                                        <option value="{{ $target->value }}"
                                            {{ old('escalation_target', $rule->escalation_target?->value) === $target->value ? 'selected' : '' }}>
                                            {{ $target->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <hr>

                        <h6 class="d-flex justify-content-between align-items-center">
                            IF (Conditions)
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addConditionRow()">+
                                Tambah Condition</button>
                        </h6>
                        <div id="conditions-rows">
                            @forelse (old('conditions', $rule->relationLoaded('conditions') ? $rule->conditions->map(fn ($c) => ['field' => $c->field->value, 'operator' => $c->operator->value, 'value' => $c->value, 'boolean_operator' => $c->boolean_operator->value])->all() : []) as $condition)
                                <div class="row condition-row mb-2">
                                    <div class="col-md-2">
                                        <select name="conditions[{{ $loop->index }}][boolean_operator]"
                                            class="form-select form-select-sm">
                                            @foreach ($booleanOperators as $op)
                                                <option value="{{ $op->value }}"
                                                    {{ ($condition['boolean_operator'] ?? 'and') === $op->value ? 'selected' : '' }}>
                                                    {{ strtoupper($op->value) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <select name="conditions[{{ $loop->index }}][field]"
                                            class="form-select form-select-sm">
                                            @foreach ($fields as $field)
                                                <option value="{{ $field->value }}"
                                                    {{ ($condition['field'] ?? '') === $field->value ? 'selected' : '' }}>
                                                    {{ $field->label() }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <select name="conditions[{{ $loop->index }}][operator]"
                                            class="form-select form-select-sm">
                                            @foreach ($operators as $operator)
                                                <option value="{{ $operator->value }}"
                                                    {{ ($condition['operator'] ?? '') === $operator->value ? 'selected' : '' }}>
                                                    {{ $operator->label() }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="text" name="conditions[{{ $loop->index }}][value]"
                                            class="form-control form-control-sm" value="{{ $condition['value'] ?? '' }}">
                                    </div>
                                    <div class="col-md-1">
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                            onclick="this.closest('.condition-row').remove()">&times;</button>
                                    </div>
                                </div>
                            @empty
                                <p class="text-body">Belum ada condition — rule ini akan selalu cocok (catch-all).</p>
                            @endforelse
                        </div>

                        <hr>

                        <h6 class="d-flex justify-content-between align-items-center">
                            THEN (Actions)
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addActionRow()">+ Tambah
                                Action</button>
                        </h6>
                        <div id="actions-rows">
                            @forelse (old('actions', $rule->relationLoaded('actions') ? $rule->actions->map(fn ($a) => ['action_type' => $a->action_type->value, 'template_id' => $a->payload['template_id'] ?? null])->all() : []) as $action)
                                <div class="row action-row mb-2">
                                    <div class="col-md-5">
                                        <select name="actions[{{ $loop->index }}][action_type]"
                                            class="form-select form-select-sm">
                                            @foreach ($actionTypes as $type)
                                                <option value="{{ $type->value }}"
                                                    {{ ($action['action_type'] ?? '') === $type->value ? 'selected' : '' }}>
                                                    {{ $type->label() }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-5">
                                        <select name="actions[{{ $loop->index }}][template_id]"
                                            class="form-select form-select-sm">
                                            <option value="">- Reply Template (jika relevan) -</option>
                                            @foreach ($replyTemplates as $template)
                                                <option value="{{ $template->id }}"
                                                    {{ (int) ($action['template_id'] ?? 0) === $template->id ? 'selected' : '' }}>
                                                    {{ $template->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                            onclick="this.closest('.action-row').remove()">&times;</button>
                                    </div>
                                </div>
                            @empty
                                <p class="text-body">Belum ada action.</p>
                            @endforelse
                        </div>

                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            <a href="{{ route('ai-center.sops.edit', $sop) }}" class="btn btn-outline-secondary">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        const conditionFields = @json(collect($fields)->map(fn($f) => ['value' => $f->value, 'label' => $f->label()]));
        const conditionOperators = @json(collect($operators)->map(fn($o) => ['value' => $o->value, 'label' => $o->label()]));
        const booleanOperators = @json(collect($booleanOperators)->map(fn($o) => $o->value));
        const actionTypes = @json(collect($actionTypes)->map(fn($a) => ['value' => $a->value, 'label' => $a->label()]));
        const replyTemplates = @json(collect($replyTemplates)->map(fn($t) => ['id' => $t->id, 'name' => $t->name]));

        let conditionIndex = document.querySelectorAll('.condition-row').length;
        let actionIndex = document.querySelectorAll('.action-row').length;

        function optionsHtml(items, valueKey, labelKey) {
            return items.map(i => `<option value="${i[valueKey]}">${i[labelKey]}</option>`).join('');
        }

        function addConditionRow() {
            const row = document.createElement('div');
            row.className = 'row condition-row mb-2';
            row.innerHTML = `
                <div class="col-md-2"><select name="conditions[${conditionIndex}][boolean_operator]" class="form-select form-select-sm">${booleanOperators.map(o => `<option value="${o}">${o.toUpperCase()}</option>`).join('')}</select></div>
                <div class="col-md-3"><select name="conditions[${conditionIndex}][field]" class="form-select form-select-sm">${optionsHtml(conditionFields, 'value', 'label')}</select></div>
                <div class="col-md-3"><select name="conditions[${conditionIndex}][operator]" class="form-select form-select-sm">${optionsHtml(conditionOperators, 'value', 'label')}</select></div>
                <div class="col-md-3"><input type="text" name="conditions[${conditionIndex}][value]" class="form-control form-control-sm"></div>
                <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.condition-row').remove()">&times;</button></div>
            `;
            document.getElementById('conditions-rows').appendChild(row);
            conditionIndex++;
        }

        function addActionRow() {
            const row = document.createElement('div');
            row.className = 'row action-row mb-2';
            row.innerHTML = `
                <div class="col-md-5"><select name="actions[${actionIndex}][action_type]" class="form-select form-select-sm">${optionsHtml(actionTypes, 'value', 'label')}</select></div>
                <div class="col-md-5"><select name="actions[${actionIndex}][template_id]" class="form-select form-select-sm"><option value="">- Reply Template (jika relevan) -</option>${replyTemplates.map(t => `<option value="${t.id}">${t.name}</option>`).join('')}</select></div>
                <div class="col-md-2"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.action-row').remove()">&times;</button></div>
            `;
            document.getElementById('actions-rows').appendChild(row);
            actionIndex++;
        }
    </script>
@endsection

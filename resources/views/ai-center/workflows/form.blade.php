@extends('layouts.app')

@section('title', ($workflow->exists ? 'Edit' : 'Tambah').' Workflow | AI Center')

@section('content')
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">{{ $workflow->exists ? 'Edit' : 'Tambah' }} Workflow</h5></div>
                <div class="card-body">
                    @if (session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ $workflow->exists ? route('ai-center.workflows.update', $workflow) : route('ai-center.workflows.store') }}"
                        method="POST">
                        @csrf
                        @if ($workflow->exists) @method('PUT') @endif

                        <div class="mb-6">
                            <label class="form-label">Nama Workflow</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                value="{{ old('name', $workflow->name) }}" required>
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-6">
                            <label class="form-label">Deskripsi</label>
                            <textarea name="description" class="form-control" rows="2">{{ old('description', $workflow->description) }}</textarea>
                        </div>

                        <div class="mb-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                @foreach (\App\Enums\AiCenter\PublishStatus::cases() as $status)
                                    <option value="{{ $status->value }}" {{ old('status', $workflow->status?->value ?? 'draft') === $status->value ? 'selected' : '' }}>
                                        {{ ucfirst($status->value) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            <a href="{{ route('ai-center.workflows.index') }}" class="btn btn-outline-secondary">Batal</a>
                        </div>
                    </form>
                </div>
            </div>

            @if ($workflow->exists)
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">Tambah Step</h5></div>
                    <div class="card-body">
                        <form action="{{ route('ai-center.workflows.nodes.store', $workflow) }}" method="POST">
                            @csrf

                            <div class="mb-4">
                                <label class="form-label">Dari Step</label>
                                <select name="from_node_id" class="form-select" required>
                                    @foreach ($workflow->nodes as $node)
                                        <option value="{{ $node->id }}">{{ $node->label }} ({{ $node->type->label() }})</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Cabang (hanya jika "Dari Step" adalah Condition)</label>
                                <select name="branch" class="form-select">
                                    <option value="default">Default</option>
                                    <option value="yes">Yes</option>
                                    <option value="no">No</option>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Tipe Step Baru</label>
                                <select name="type" class="form-select">
                                    <option value="condition">Condition</option>
                                    <option value="action">Action</option>
                                    <option value="end">End</option>
                                    <option value="intent_detection">Intent Detection</option>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Label</label>
                                <input type="text" name="label" class="form-control" placeholder="mis. Purchase > 14 Hari?" required>
                            </div>

                            <p class="text-body small mb-2">Isi berikut hanya jika tipe = Condition:</p>
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <select name="field" class="form-select form-select-sm">
                                        @foreach (\App\Enums\AiCenter\ConditionField::cases() as $field)
                                            <option value="{{ $field->value }}">{{ $field->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <select name="operator" class="form-select form-select-sm">
                                        @foreach (\App\Enums\AiCenter\ConditionOperator::cases() as $operator)
                                            <option value="{{ $operator->value }}">{{ $operator->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <input type="text" name="value" class="form-control form-control-sm" placeholder="nilai">
                                </div>
                            </div>

                            <p class="text-body small mb-2">Isi berikut hanya jika tipe = Action:</p>
                            <div class="mb-4">
                                <select name="action_type" class="form-select form-select-sm">
                                    @foreach (\App\Enums\AiCenter\AiAction::cases() as $action)
                                        <option value="{{ $action->value }}">{{ $action->label() }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary">Tambah Step</button>
                        </form>
                    </div>
                </div>
            @endif
        </div>

        @if ($workflow->exists)
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">Alur Workflow</h5></div>
                    <div class="card-body">
                        <ul class="list-group">
                            @foreach ($workflow->nodes as $node)
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="badge bg-label-primary me-1">{{ $node->type->label() }}</span>
                                            <strong>{{ $node->label }}</strong>
                                            @if ($node->type->value === 'condition' && $node->config)
                                                <div class="small text-body">
                                                    {{ $node->config['field'] ?? '' }} {{ $node->config['operator'] ?? '' }} {{ $node->config['value'] ?? '' }}
                                                </div>
                                            @endif
                                        </div>
                                        <form action="{{ route('ai-center.workflows.nodes.destroy', [$workflow, $node]) }}" method="POST"
                                            onsubmit="return confirm('Hapus step ini?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">&times;</button>
                                        </form>
                                    </div>
                                    @foreach ($node->outgoingEdges as $edge)
                                        <div class="small text-primary ms-3 mt-1">
                                            <i class="icon-base bx bx-down-arrow-alt"></i>
                                            [{{ strtoupper($edge->branch->value) }}] &rarr; {{ $edge->toNode?->label }}
                                        </div>
                                    @endforeach
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection

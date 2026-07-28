@extends('layouts.app')

@section('title', ($intent->exists ? 'Edit' : 'Tambah').' Intent | AI Center')

@section('content')
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ $intent->exists ? 'Edit' : 'Tambah' }} Intent</h5>
                </div>
                <div class="card-body">
                    <form action="{{ $intent->exists ? route('ai-center.intents.update', $intent) : route('ai-center.intents.store') }}"
                        method="POST">
                        @csrf
                        @if ($intent->exists) @method('PUT') @endif

                        <div class="mb-6">
                            <label class="form-label">Nama Intent</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                value="{{ old('name', $intent->name) }}" required>
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-6">
                                <label class="form-label">Kategori</label>
                                <select name="category_id" class="form-select">
                                    <option value="">- Tanpa Kategori -</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}"
                                            {{ (int) old('category_id', $intent->category_id) === $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 mb-6">
                                <label class="form-label">Priority</label>
                                <select name="priority" class="form-select">
                                    @foreach (\App\Enums\AiCenter\PriorityLevel::cases() as $level)
                                        <option value="{{ $level->value }}"
                                            {{ old('priority', $intent->priority?->value ?? 'medium') === $level->value ? 'selected' : '' }}>
                                            {{ $level->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 mb-6">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    @foreach (\App\Enums\AiCenter\PublishStatus::cases() as $status)
                                        <option value="{{ $status->value }}"
                                            {{ old('status', $intent->status?->value ?? 'draft') === $status->value ? 'selected' : '' }}>
                                            {{ ucfirst($status->value) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="mb-6">
                            <label class="form-label">Deskripsi</label>
                            <textarea name="description" class="form-control" rows="2">{{ old('description', $intent->description) }}</textarea>
                        </div>

                        <div class="mb-6">
                            <label class="form-label d-flex justify-content-between">
                                Keywords
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addRow('keywords-rows', 'keywords[]', '')">+ Tambah</button>
                            </label>
                            <div id="keywords-rows">
                                @forelse (old('keywords', $intent->keywords->pluck('keyword')->all() ?: ['']) as $keyword)
                                    <div class="input-group mb-2">
                                        <input type="text" name="keywords[]" class="form-control" value="{{ $keyword }}" placeholder="mis. refund">
                                        <button type="button" class="btn btn-outline-danger" onclick="this.closest('.input-group').remove()">&times;</button>
                                    </div>
                                @endforelse
                            </div>
                        </div>

                        <div class="mb-6">
                            <label class="form-label d-flex justify-content-between">
                                Contoh Kalimat (Examples)
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addRow('examples-rows', 'examples[]', '')">+ Tambah</button>
                            </label>
                            <div id="examples-rows">
                                @forelse (old('examples', $intent->examples->pluck('example_text')->all() ?: ['']) as $example)
                                    <div class="input-group mb-2">
                                        <input type="text" name="examples[]" class="form-control" value="{{ $example }}" placeholder="mis. saya mau minta uang kembali">
                                        <button type="button" class="btn btn-outline-danger" onclick="this.closest('.input-group').remove()">&times;</button>
                                    </div>
                                @endforelse
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            <a href="{{ route('ai-center.intents.index') }}" class="btn btn-outline-secondary">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function addRow(containerId, inputName, value) {
            const container = document.getElementById(containerId);
            const row = document.createElement('div');
            row.className = 'input-group mb-2';
            row.innerHTML = `<input type="text" name="${inputName}" class="form-control" value="${value}">` +
                `<button type="button" class="btn btn-outline-danger" onclick="this.closest('.input-group').remove()">&times;</button>`;
            container.appendChild(row);
        }
    </script>
@endsection

@extends('layouts.app')

@section('title', ($replyTemplate->exists ? 'Edit' : 'Tambah').' Reply Template | AI Center')

@section('content')
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">{{ $replyTemplate->exists ? 'Edit' : 'Tambah' }} Reply Template</h5></div>
                <div class="card-body">
                    @error('body')
                        <div class="alert alert-danger">{{ $message }}</div>
                    @enderror

                    <form action="{{ $replyTemplate->exists ? route('ai-center.reply-templates.update', $replyTemplate) : route('ai-center.reply-templates.store') }}"
                        method="POST">
                        @csrf
                        @if ($replyTemplate->exists) @method('PUT') @endif

                        <div class="row">
                            <div class="col-md-6 mb-6">
                                <label class="form-label">Nama Template</label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                    value="{{ old('name', $replyTemplate->name) }}" required>
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3 mb-6">
                                <label class="form-label">Kategori</label>
                                <input type="text" name="category" class="form-control" placeholder="mis. Closing"
                                    value="{{ old('category', $replyTemplate->category) }}">
                            </div>
                            <div class="col-md-3 mb-6">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    @foreach (\App\Enums\AiCenter\PublishStatus::cases() as $status)
                                        <option value="{{ $status->value }}"
                                            {{ old('status', $replyTemplate->status?->value ?? 'draft') === $status->value ? 'selected' : '' }}>
                                            {{ ucfirst($status->value) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="mb-6">
                            <label class="form-label">Subject (opsional)</label>
                            <input type="text" name="subject" class="form-control" value="{{ old('subject', $replyTemplate->subject) }}">
                        </div>

                        <div class="mb-6">
                            <label class="form-label d-flex justify-content-between align-items-center">
                                <span>Isi Template</span>
                                <span class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        Insert Variable
                                    </button>
                                    <ul class="dropdown-menu">
                                        @foreach ($variables as $variable)
                                            <li>
                                                <a class="dropdown-item" href="javascript:void(0)" onclick="insertVariable('{{ $variable->key }}')">
                                                    {{ '{{' }}{{ $variable->key }}{{ '}}' }} — {{ $variable->label }}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </span>
                            </label>
                            <textarea id="body" name="body" class="form-control" rows="10" required>{{ old('body', $replyTemplate->body) }}</textarea>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            <a href="{{ route('ai-center.reply-templates.index') }}" class="btn btn-outline-secondary">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function insertVariable(key) {
            const textarea = document.getElementById('body');
            const token = `{{ '{{' }}${key}{{ '}}' }}`;
            const start = textarea.selectionStart ?? textarea.value.length;
            const end = textarea.selectionEnd ?? textarea.value.length;
            textarea.value = textarea.value.slice(0, start) + token + textarea.value.slice(end);
            textarea.focus();
        }
    </script>
@endsection

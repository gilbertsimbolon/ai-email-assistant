@extends('layouts.app')

@section('title', ($knowledgeBase->exists ? 'Edit' : 'Tambah').' Knowledge Base | AI Center')

@section('content')
    <div class="row">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><h5 class="mb-0">{{ $knowledgeBase->exists ? 'Edit' : 'Tambah' }} Knowledge Base</h5></div>
                <div class="card-body">
                    <form action="{{ $knowledgeBase->exists ? route('ai-center.knowledge-bases.update', $knowledgeBase) : route('ai-center.knowledge-bases.store') }}"
                        method="POST">
                        @csrf
                        @if ($knowledgeBase->exists) @method('PUT') @endif

                        <div class="mb-6">
                            <label class="form-label">Judul</label>
                            <input type="text" name="title" class="form-control @error('title') is-invalid @enderror"
                                value="{{ old('title', $knowledgeBase->title) }}" required>
                            @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-6">
                                <label class="form-label">Tipe</label>
                                <select name="type" class="form-select">
                                    @foreach (\App\Enums\AiCenter\KnowledgeBaseType::cases() as $type)
                                        <option value="{{ $type->value }}"
                                            {{ old('type', $knowledgeBase->type?->value ?? 'faq') === $type->value ? 'selected' : '' }}>
                                            {{ $type->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-6">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    @foreach (\App\Enums\AiCenter\PublishStatus::cases() as $status)
                                        <option value="{{ $status->value }}"
                                            {{ old('status', $knowledgeBase->status?->value ?? 'draft') === $status->value ? 'selected' : '' }}>
                                            {{ ucfirst($status->value) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-6">
                                <label class="form-label">Urutan</label>
                                <input type="number" name="sort_order" class="form-control" min="0"
                                    value="{{ old('sort_order', $knowledgeBase->sort_order ?? 0) }}">
                            </div>
                        </div>

                        <div class="mb-6">
                            <label class="form-label">Konten</label>
                            <textarea name="content" class="form-control @error('content') is-invalid @enderror" rows="10" required>{{ old('content', $knowledgeBase->content) }}</textarea>
                            @error('content') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            <a href="{{ route('ai-center.knowledge-bases.index') }}" class="btn btn-outline-secondary">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

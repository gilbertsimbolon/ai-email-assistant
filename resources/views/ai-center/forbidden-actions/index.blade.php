@extends('layouts.app')

@section('title', 'Forbidden Actions | AI Center')

@section('content')
    <div class="row">
        <div class="col-md-5">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Tambah Forbidden Action</h5></div>
                <div class="card-body">
                    @if (session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    <form action="{{ route('ai-center.forbidden-actions.store') }}" method="POST">
                        @csrf
                        <div class="mb-4">
                            <label class="form-label">Label</label>
                            <input type="text" name="label" class="form-control @error('label') is-invalid @enderror"
                                value="{{ old('label') }}" placeholder="mis. Menjanjikan Refund" required>
                            @error('label') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Deskripsi (opsional)</label>
                            <textarea name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Tambah</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Daftar Forbidden Actions</h5></div>
                <div class="card-body">
                    <ul class="list-group">
                        @forelse ($forbiddenActions as $forbidden)
                            <li class="list-group-item d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-semibold">{{ $forbidden->label }}</div>
                                    @if ($forbidden->description)
                                        <small class="text-body">{{ $forbidden->description }}</small>
                                    @endif
                                </div>
                                <form action="{{ route('ai-center.forbidden-actions.destroy', $forbidden) }}" method="POST"
                                    onsubmit="return confirm('Hapus forbidden action ini?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                </form>
                            </li>
                        @empty
                            <li class="list-group-item text-body">Belum ada Forbidden Action.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection

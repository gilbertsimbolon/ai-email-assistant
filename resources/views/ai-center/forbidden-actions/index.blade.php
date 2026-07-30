@extends('layouts.app')

@section('title', 'Forbidden Actions | AI Center')

@section('content')
    <div class="row g-4">
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><h5 class="mb-0"><i class="bx bx-block me-1 text-danger"></i> Tambah Forbidden Action</h5></div>
                <div class="card-body">
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
            <div class="card shadow-sm">
                <div class="card-header bg-white"><h5 class="mb-0">Daftar Forbidden Actions</h5></div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse ($forbiddenActions as $forbidden)
                            <li class="list-group-item d-flex justify-content-between align-items-start px-4 py-3">
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
                            <li class="list-group-item text-body text-center py-5">
                                <i class="bx bx-block display-4 mb-2 d-block text-muted"></i>
                                Belum ada Forbidden Action.
                            </li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection

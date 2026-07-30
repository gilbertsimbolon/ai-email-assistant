@extends('layouts.app')

@section('title', 'AI Models | AI Center')

@section('content')
    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center bg-white">
            <h5 class="mb-0"><i class="bx bx-chip me-1 text-primary"></i> AI Models</h5>
            <a href="{{ route('ai-center.ai-models.create') }}" class="btn btn-primary btn-sm">
                <i class="icon-base bx bx-plus me-1"></i> Tambah AI Model
            </a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Provider</th>
                            <th>Model</th>
                            <th>Status</th>
                            <th>Default</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($aiModels as $aiModel)
                            <tr>
                                <td class="fw-semibold">{{ $aiModel->name }}</td>
                                <td>{{ $aiModel->provider->label() }}</td>
                                <td>{{ $aiModel->model }}</td>
                                <td>
                                    <span class="badge {{ $aiModel->status->value === 'published' ? 'bg-label-success' : 'bg-label-secondary' }}">
                                        {{ ucfirst($aiModel->status->value) }}
                                    </span>
                                    @unless ($aiModel->enabled)
                                        <span class="badge bg-label-warning">Nonaktif</span>
                                    @endunless
                                </td>
                                <td>
                                    @if ($aiModel->is_default)
                                        <span class="badge bg-label-primary">Default</span>
                                    @else
                                        <form action="{{ route('ai-center.ai-models.set-default', $aiModel) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">Jadikan Default</button>
                                        </form>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('ai-center.ai-models.edit', $aiModel) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                        <form action="{{ route('ai-center.ai-models.destroy', $aiModel) }}" method="POST" class="d-inline"
                                            onsubmit="return confirm('Hapus AI Model ini?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-body py-5">
                                    <i class="bx bx-chip display-4 mb-2 d-block text-muted"></i>
                                    Belum ada AI Model.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

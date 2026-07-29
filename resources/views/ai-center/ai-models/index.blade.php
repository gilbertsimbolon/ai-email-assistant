@extends('layouts.app')

@section('title', 'AI Models | AI Center')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">AI Models</h5>
            <a href="{{ route('ai-center.ai-models.create') }}" class="btn btn-primary btn-sm">
                <i class="icon-base bx bx-plus me-1"></i> Tambah AI Model
            </a>
        </div>
        <div class="card-body">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="table-responsive">
                <table class="table">
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
                                <td>{{ $aiModel->name }}</td>
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
                                    <a href="{{ route('ai-center.ai-models.edit', $aiModel) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                    <form action="{{ route('ai-center.ai-models.destroy', $aiModel) }}" method="POST" class="d-inline"
                                        onsubmit="return confirm('Hapus AI Model ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-body">Belum ada AI Model.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

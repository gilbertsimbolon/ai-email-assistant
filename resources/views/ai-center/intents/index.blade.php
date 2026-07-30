@extends('layouts.app')

@section('title', 'Intent Builder | AI Center')

@section('content')
    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center bg-white">
            <h5 class="mb-0"><i class="bx bx-target-lock me-1 text-primary"></i> Intent Builder</h5>
            <a href="{{ route('ai-center.intents.create') }}" class="btn btn-primary btn-sm">
                <i class="icon-base bx bx-plus me-1"></i> Tambah Intent
            </a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Kategori</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Keywords</th>
                            <th>SOP</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($intents as $intent)
                            <tr>
                                <td class="fw-semibold">{{ $intent->name }}</td>
                                <td>{{ $intent->category?->name ?? '-' }}</td>
                                <td><span class="badge bg-label-primary">{{ $intent->priority->label() }}</span></td>
                                <td>
                                    <span class="badge {{ $intent->status->value === 'published' ? 'bg-label-success' : 'bg-label-secondary' }}">
                                        {{ ucfirst($intent->status->value) }}
                                    </span>
                                </td>
                                <td>{{ $intent->keywords_count }}</td>
                                <td>{{ $intent->sops_count }}</td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('ai-center.intents.edit', $intent) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                        <form action="{{ route('ai-center.intents.destroy', $intent) }}" method="POST" class="d-inline"
                                            onsubmit="return confirm('Hapus intent ini?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-body py-5">
                                    <i class="bx bx-target-lock display-4 mb-2 d-block text-muted"></i>
                                    Belum ada Intent.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

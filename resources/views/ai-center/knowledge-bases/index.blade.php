@extends('layouts.app')

@section('title', 'Knowledge Base | AI Center')

@section('content')
    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center bg-white">
            <h5 class="mb-0"><i class="bx bx-library me-1 text-primary"></i> Knowledge Base</h5>
            <a href="{{ route('ai-center.knowledge-bases.create') }}" class="btn btn-primary btn-sm">
                <i class="icon-base bx bx-plus me-1"></i> Tambah
            </a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Judul</th>
                            <th>Tipe</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($knowledgeBases as $kb)
                            <tr>
                                <td class="fw-semibold">{{ $kb->title }}</td>
                                <td><span class="badge bg-label-info">{{ $kb->type->label() }}</span></td>
                                <td>
                                    <span class="badge {{ $kb->status->value === 'published' ? 'bg-label-success' : 'bg-label-secondary' }}">
                                        {{ ucfirst($kb->status->value) }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('ai-center.knowledge-bases.edit', $kb) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                        <form action="{{ route('ai-center.knowledge-bases.destroy', $kb) }}" method="POST" class="d-inline"
                                            onsubmit="return confirm('Hapus Knowledge Base ini?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-body py-5">
                                    <i class="bx bx-library display-4 mb-2 d-block text-muted"></i>
                                    Belum ada Knowledge Base.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

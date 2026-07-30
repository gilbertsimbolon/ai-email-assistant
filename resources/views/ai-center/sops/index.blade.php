@extends('layouts.app')

@section('title', 'SOP Builder | AI Center')

@section('content')
    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center bg-white">
            <h5 class="mb-0"><i class="bx bx-book-content me-1 text-primary"></i> SOP Builder</h5>
            <a href="{{ route('ai-center.sops.create') }}" class="btn btn-primary btn-sm">
                <i class="icon-base bx bx-plus me-1"></i> Tambah SOP
            </a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Intent</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Rules</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sops as $sop)
                            <tr>
                                <td class="fw-semibold">{{ $sop->name }}</td>
                                <td>{{ $sop->intent?->name ?? '-' }}</td>
                                <td><span class="badge bg-label-primary">{{ $sop->priority->label() }}</span></td>
                                <td>
                                    <span class="badge {{ $sop->status->value === 'published' ? 'bg-label-success' : 'bg-label-secondary' }}">
                                        {{ ucfirst($sop->status->value) }}
                                    </span>
                                </td>
                                <td>{{ $sop->rules_count }}</td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('ai-center.sops.edit', $sop) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                        <form action="{{ route('ai-center.sops.destroy', $sop) }}" method="POST" class="d-inline"
                                            onsubmit="return confirm('Hapus SOP ini?');">
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
                                    <i class="bx bx-book-content display-4 mb-2 d-block text-muted"></i>
                                    Belum ada SOP.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

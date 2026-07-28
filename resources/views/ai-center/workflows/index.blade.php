@extends('layouts.app')

@section('title', 'Workflow Builder | AI Center')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Workflow Builder</h5>
            <a href="{{ route('ai-center.workflows.create') }}" class="btn btn-primary btn-sm">
                <i class="icon-base bx bx-plus me-1"></i> Tambah Workflow
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
                            <th>Status</th>
                            <th>Jumlah Step</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($workflows as $workflow)
                            <tr>
                                <td>{{ $workflow->name }}</td>
                                <td>
                                    <span class="badge {{ $workflow->status->value === 'published' ? 'bg-label-success' : 'bg-label-secondary' }}">
                                        {{ ucfirst($workflow->status->value) }}
                                    </span>
                                </td>
                                <td>{{ $workflow->nodes_count }}</td>
                                <td class="text-end">
                                    <a href="{{ route('ai-center.workflows.edit', $workflow) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                    <form action="{{ route('ai-center.workflows.destroy', $workflow) }}" method="POST" class="d-inline"
                                        onsubmit="return confirm('Hapus workflow ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-body">Belum ada Workflow.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

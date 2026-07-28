@extends('layouts.app')

@section('title', 'Reply Templates | AI Center')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Reply Templates</h5>
            <a href="{{ route('ai-center.reply-templates.create') }}" class="btn btn-primary btn-sm">
                <i class="icon-base bx bx-plus me-1"></i> Tambah
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
                            <th>Kategori</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($replyTemplates as $template)
                            <tr>
                                <td>{{ $template->name }}</td>
                                <td>{{ $template->category ?? '-' }}</td>
                                <td>
                                    <span class="badge {{ $template->status->value === 'published' ? 'bg-label-success' : 'bg-label-secondary' }}">
                                        {{ ucfirst($template->status->value) }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('ai-center.reply-templates.edit', $template) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                    <form action="{{ route('ai-center.reply-templates.destroy', $template) }}" method="POST" class="d-inline"
                                        onsubmit="return confirm('Hapus template ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-body">Belum ada Reply Template.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

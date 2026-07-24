@extends('layouts.app')

@section('title', 'Inbox | AI Email Assistant')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Inbox</h5>

            <div class="btn-group">
                <a href="{{ route('inbox.index') }}"
                    class="btn btn-sm {{ $status ? 'btn-outline-primary' : 'btn-primary' }}">Semua</a>
                @foreach (\App\Enums\ConversationStatus::cases() as $case)
                    <a href="{{ route('inbox.index', ['status' => $case->value]) }}"
                        class="btn btn-sm {{ $status === $case->value ? 'btn-primary' : 'btn-outline-primary' }}">
                        {{ ucwords(str_replace('_', ' ', $case->value)) }}
                    </a>
                @endforeach
            </div>
        </div>

        <div class="table-responsive text-nowrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kontak</th>
                        <th>Channel</th>
                        <th>Subjek</th>
                        <th>Prioritas</th>
                        <th>Status</th>
                        <th>Pesan Terakhir</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($conversations as $conversation)
                        <tr>
                            <td>
                                <div>{{ $conversation->contact_name ?? $conversation->contact_email ?? '-' }}</div>
                                <small class="text-muted">{{ $conversation->contact_email }}</small>
                            </td>
                            <td class="text-capitalize">{{ $conversation->channel->value }}</td>
                            <td>{{ $conversation->subject ?? '-' }}</td>
                            <td>
                                @if ($conversation->analysis)
                                    <span class="badge bg-label-{{ match($conversation->analysis->priority->value) {
                                        'high' => 'danger',
                                        'medium' => 'warning',
                                        default => 'secondary',
                                    } }}">
                                        {{ ucfirst($conversation->analysis->priority->value) }}
                                    </span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-label-{{ match($conversation->status->value) {
                                    'pending_review' => 'warning',
                                    'replied' => 'success',
                                    default => 'secondary',
                                } }}">
                                    {{ ucwords(str_replace('_', ' ', $conversation->status->value)) }}
                                </span>
                            </td>
                            <td>{{ optional($conversation->last_message_at)->diffForHumans() ?? '-' }}</td>
                            <td>
                                <a href="{{ route('inbox.show', $conversation) }}" class="btn btn-sm btn-primary">
                                    Buka
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted">Belum ada percakapan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card-body">
            {{ $conversations->links() }}
        </div>
    </div>
@endsection

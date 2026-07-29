@extends('layouts.app')

@section('title', 'Gmail Analytics | Reports')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Gmail Analytics</h4>
        <div class="btn-group btn-group-sm">
            @foreach (['pdf' => ['PDF', 'danger'], 'excel' => ['Excel', 'success'], 'csv' => ['CSV', 'secondary']] as $format => $meta)
                <a href="{{ route('reports.export', ['report' => 'gmail-accounts', 'format' => $format]) }}"
                   class="btn btn-outline-{{ $meta[1] }}">{{ $meta[0] }}</a>
            @endforeach
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover" id="gmailAccountsTable">
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>Owner</th>
                        <th>Jumlah Email</th>
                        <th>Sync Terakhir</th>
                        <th>Status</th>
                        <th>History ID</th>
                        <th>Last Error</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($accounts as $account)
                        <tr>
                            <td>{{ $account['email'] }}</td>
                            <td>{{ $account['owner'] ?? '-' }}</td>
                            <td>{{ number_format($account['conversation_count']) }}</td>
                            <td>{{ optional($account['last_synced_at'])->diffForHumans() ?? 'Belum pernah' }}</td>
                            <td>
                                <span class="badge bg-label-{{ $account['status'] === 'connected' ? 'success' : ($account['status'] === 'error' ? 'danger' : 'secondary') }}">
                                    {{ ucfirst($account['status']) }}
                                </span>
                            </td>
                            <td>{{ $account['history_id'] ?? '-' }}</td>
                            <td class="text-danger">{{ $account['last_error'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">Belum ada akun Gmail yang terhubung.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@2/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.jsdelivr.net/npm/datatables.net@2/js/dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@2/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(function () {
            $('#gmailAccountsTable').DataTable();
        });
    </script>
@endpush

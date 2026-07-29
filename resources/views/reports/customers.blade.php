@extends('layouts.app')

@section('title', 'Customer Analytics | Reports')

@section('content')
    <h4 class="mb-4">Customer Analytics</h4>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-auto flex-grow-1">
                    <label class="form-label small mb-0 d-block">Cari nama / email</label>
                    <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="Cari customer...">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bx bx-search"></i> Cari</button>
                </div>
                <div class="col-auto ms-auto">
                    <div class="btn-group btn-group-sm">
                        @foreach (['pdf' => ['PDF', 'danger'], 'excel' => ['Excel', 'success'], 'csv' => ['CSV', 'secondary']] as $format => $meta)
                            <a href="{{ route('reports.export', array_merge(request()->query(), ['report' => 'customers', 'format' => $format])) }}"
                               class="btn btn-outline-{{ $meta[1] }}">{{ $meta[0] }}</a>
                        @endforeach
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h6 class="mb-0">Top Customer</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Email</th>
                        <th>Jumlah Email</th>
                        <th>Jumlah Ticket</th>
                        <th>Last Contact</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $row)
                        <tr>
                            <td>{{ $row->contact_name ?? '-' }}</td>
                            <td>{{ $row->contact_email }}</td>
                            <td>{{ number_format($row->email_count) }}</td>
                            <td>{{ number_format($row->ticket_count) }}</td>
                            <td>{{ optional($row->last_contact)->diffForHumans() ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">Belum ada data customer.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">
            {{ $customers->links() }}
        </div>
    </div>
@endsection

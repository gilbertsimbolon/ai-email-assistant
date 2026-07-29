@extends('layouts.app')

@section('title', 'Content Analytics | Reports')

@section('content')
    <h4 class="mb-4">Content &amp; Workflow Analytics</h4>

    <x-reports.filter-bar :period="$period" />

    <div class="card">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs" id="contentTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-sops" data-tab-key="sops" type="button">SOP</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-knowledge" data-tab-key="knowledge" type="button">Knowledge Base</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-templates" data-tab-key="reply-templates" type="button">Reply Template</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-workflows" data-tab-key="workflows" type="button">Workflow</button>
                </li>
            </ul>
        </div>

        <div class="card-body">
            <div class="d-flex justify-content-end mb-3">
                <div class="btn-group btn-group-sm">
                    @foreach (['pdf' => ['PDF', 'danger'], 'excel' => ['Excel', 'success'], 'csv' => ['CSV', 'secondary']] as $format => $meta)
                        <a id="export-{{ $format }}"
                           href="{{ route('reports.export', array_merge(request()->query(), ['report' => 'content', 'format' => $format, 'tab' => 'sops'])) }}"
                           class="btn btn-outline-{{ $meta[1] }} js-export-link" data-format="{{ $format }}">Export {{ $meta[0] }} (tab aktif)</a>
                    @endforeach
                </div>
            </div>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="tab-sops">
                    <table class="table table-hover datatable">
                        <thead>
                            <tr><th>SOP</th><th>Jumlah Digunakan</th><th>Avg Success Rate</th><th>Last Used</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($sops as $row)
                                <tr>
                                    <td>{{ $row['name'] }}</td>
                                    <td>{{ number_format($row['usage_count']) }}</td>
                                    <td>{{ $row['success_rate'] }}%</td>
                                    <td>{{ optional($row['last_used'])->diffForHumans() ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">Belum ada data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="tab-pane fade" id="tab-knowledge">
                    <table class="table table-hover datatable">
                        <thead>
                            <tr><th>Knowledge</th><th>Type</th><th>Jumlah Penggunaan</th><th>Last Used</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($knowledge as $row)
                                <tr>
                                    <td>{{ $row['title'] }}</td>
                                    <td>{{ $row['type'] }}</td>
                                    <td>{{ number_format($row['usage_count']) }}</td>
                                    <td>{{ optional($row['last_used'])->diffForHumans() ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">Belum ada data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="tab-pane fade" id="tab-templates">
                    <table class="table table-hover datatable">
                        <thead>
                            <tr><th>Template</th><th>Digunakan</th><th>Diubah Agent</th><th>Success Rate</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($reply_templates as $row)
                                <tr>
                                    <td>{{ $row['name'] }}</td>
                                    <td>{{ number_format($row['usage_count']) }}</td>
                                    <td>{{ number_format($row['edited_by_agent']) }}</td>
                                    <td>{{ $row['success_rate'] }}%</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">Belum ada data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="tab-pane fade" id="tab-workflows">
                    <table class="table table-hover datatable">
                        <thead>
                            <tr><th>Workflow</th><th>Jumlah Dijalankan</th><th>Success</th><th>Failed</th><th>Avg Duration</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($workflows as $row)
                                <tr>
                                    <td>{{ $row['name'] }}</td>
                                    <td>{{ number_format($row['run_count']) }}</td>
                                    <td>{{ number_format($row['success']) }}</td>
                                    <td>{{ number_format($row['failed']) }}</td>
                                    <td>{{ $row['avg_duration_ms'] }} ms</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">Belum ada data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@2/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.jsdelivr.net/npm/datatables.net@2/js/dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@2/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(function () {
            // DataTables miscalculates column widths if initialized while its
            // tab-pane is hidden, so only the active tab initializes eagerly —
            // the rest initialize lazily on shown.bs.tab.
            function initTable(pane) {
                const $table = $(pane).find('.datatable');
                if ($table.length && !$.fn.DataTable.isDataTable($table[0])) {
                    $table.DataTable();
                }
            }

            initTable('#tab-sops');

            // Export links are rendered with tab=sops server-side; keep their
            // `tab` query param in sync with whichever tab is actually active.
            $('#contentTabs button').on('shown.bs.tab', function (e) {
                initTable($(e.target).data('bs-target'));

                const tabKey = $(e.target).data('tab-key');
                $('.js-export-link').each(function () {
                    const url = new URL(this.href);
                    url.searchParams.set('tab', tabKey);
                    this.href = url.toString();
                });
            });
        });
    </script>
@endpush

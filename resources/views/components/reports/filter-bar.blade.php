@props(['period', 'exportReport' => null])

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small mb-0 d-block">Periode</label>
                <select name="period" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach (['day' => 'Hari', 'week' => 'Minggu', 'month' => 'Bulan', 'year' => 'Tahun', 'custom' => 'Custom Range'] as $value => $label)
                        <option value="{{ $value }}" {{ ($period['period'] ?? 'month') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            @if (($period['period'] ?? null) === 'custom')
                <div class="col-auto">
                    <label class="form-label small mb-0 d-block">Dari</label>
                    <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm">
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-0 d-block">Sampai</label>
                    <input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm">
                </div>
            @endif

            {{ $slot }}

            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary"><i class="bx bx-filter-alt"></i> Terapkan</button>
            </div>

            @if ($exportReport)
                <div class="col-auto ms-auto">
                    <div class="btn-group">
                        @foreach (['pdf' => ['PDF', 'danger'], 'excel' => ['Excel', 'success'], 'csv' => ['CSV', 'secondary']] as $format => $meta)
                            <a href="{{ route('reports.export', array_merge(request()->query(), ['report' => $exportReport, 'format' => $format])) }}"
                               class="btn btn-sm btn-outline-{{ $meta[1] }}">{{ $meta[0] }}</a>
                        @endforeach
                    </div>
                </div>
            @endif
        </form>
    </div>
</div>

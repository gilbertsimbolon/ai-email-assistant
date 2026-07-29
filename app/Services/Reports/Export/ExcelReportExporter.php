<?php

namespace App\Services\Reports\Export;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExcelReportExporter
{
    public function download(array $headings, Collection $rows, string $filename, string $format = 'xlsx'): BinaryFileResponse
    {
        $writerType = $format === 'csv' ? ExcelFormat::CSV : ExcelFormat::XLSX;

        return Excel::download(new GenericArrayExport($headings, $rows), $filename, $writerType);
    }
}

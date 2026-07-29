<?php

namespace App\Services\Reports\Export;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class PdfReportExporter
{
    public function download(string $title, array $headings, Collection $rows, string $filename): Response
    {
        return Pdf::loadView('reports.export.pdf', [
            'title' => $title,
            'headings' => $headings,
            'rows' => $rows,
            'generatedAt' => now(),
        ])->download($filename);
    }
}

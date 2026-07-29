<?php

namespace App\Services\Reports\Export;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * One reusable Excel/CSV export shape for every Reports table: a flat list
 * of indexed-array rows plus a headings row, fed by whichever report
 * service built the on-screen table.
 */
class GenericArrayExport implements FromCollection, WithHeadings
{
    public function __construct(
        protected array $headings,
        protected Collection $rows,
    ) {
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function collection(): Collection
    {
        return $this->rows;
    }
}

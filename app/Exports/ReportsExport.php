<?php

namespace App\Exports;

use App\Models\UserInvoice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMapping;

class ReportsExport implements FromArray, ShouldQueue, WithMapping
{
    use Exportable;

    public function __construct(
        public array $data
    ) {
        // code...
    }

    public function array(): array
    {
        return $this->data;
    }

    /**
     * @var UserInvoice
     */
    public function map($data): array
    {
        return array_values($data);
    }
}

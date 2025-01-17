<?php

namespace App\Exports;

use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class LocationInvoiceExport implements FromCollection, ShouldQueue, WithHeadings
{
    public function __construct($invoices)
    {
        $this->invoices = $invoices;
    }

    public function collection()
    {
        return $this->invoices;
    }

    public function headings(): array
    {
        return [
            'Code',
            'Location',
            'Description',
            'Status',
            'Due on',
            'Sent on',
            'Outstanding',
            'Amount',
            'AmountInRands',
        ];
    }
}

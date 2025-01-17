<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PaymentExport implements FromArray, WithHeadings
{
    public function __construct($payments)
    {
        $this->payments = $payments;
    }

    public function array(): array
    {
        return $this->payments;
    }

    public function headings(): array
    {
        return [
            'Invoice #',
            'Member',
            'Date',
            'Type',
            'Amount',
        ];
    }
}

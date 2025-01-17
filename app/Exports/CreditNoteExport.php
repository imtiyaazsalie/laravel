<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CreditNoteExport implements FromArray, WithHeadings
{
    public function __construct($creditNotes)
    {
        $this->creditNotes = $creditNotes;
    }

    public function array(): array
    {
        return $this->creditNotes;
    }

    public function headings(): array
    {
        return [
            'id',
            'code',
            'Member',
            'description',
            'status',
            'Due on',
            'Sent on',
            'Amount',
        ];
    }
}

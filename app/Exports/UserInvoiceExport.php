<?php

namespace App\Exports;

use App\Models\TenantUser;
use App\Services\InvoiceService;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class UserInvoiceExport implements FromArray, WithHeadings
{
    use Exportable;

    public function __construct(private readonly TenantUser $tenantUser)
    {
    }

    public function array(): array
    {
        $data = [];
        $invoices = (new InvoiceService())->getInvoices(request(), $this->tenantUser, true)
            ->without(['location', 'invoiceItems'])
            ->get();

        foreach ($invoices as $invoice) {
            $data[] = [
                'Code' => $invoice->code,
                'Member' => $invoice?->invoice_member_name,
                'Description' => $invoice->description,
                'Status' => $invoice->status->name,
                'Due on' => $invoice->due_on->format('Y-m-d'),
                'Sent on' => $invoice->sent_on ? $invoice->sent_on->format('Y-m-d') : null,
                'Outstanding' => number_format($invoice->outstanding_amount, 2, '.', ''),
                'Amount' => number_format($invoice->amount, 2, '.', ''),
                'VAT' => number_format(
                    $invoice->invoiceItems->transform(fn ($item) => $item->vat_amount)->sum(),
                    2,
                    '.',
                    ''
                ),
            ];
        }

        return $data;
    }

    public function headings(): array
    {
        return [
            'Code',
            'Member',
            'Description',
            'Status',
            'Due on',
            'Sent on',
            'Outstanding',
            'Amount',
            'VAT',
        ];
    }
}

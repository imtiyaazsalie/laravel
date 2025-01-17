<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Stripe\Invoice;

class LocationExport implements FromCollection, WithHeadings, WithMapping
{
    use Exportable;

    public function __construct(public Collection $locations)
    {
        $this->locations = $locations;
    }

    public function collection()
    {
        return $this->locations;
    }

    public function headings(): array
    {
        return [
            'Tenant name',
            'Location name',
            'Health Provider',
            'Total active users',
            'Total bookings',
        ];
    }

    /**
     * @param  Invoice  $invoice
     */
    public function map($location): array
    {
        return [
            $location->tenant_name,
            $location->location_name,
            $location->health_provider_name,
            $location->total_active_users,
            $location->total_bookings,
        ];
    }
}

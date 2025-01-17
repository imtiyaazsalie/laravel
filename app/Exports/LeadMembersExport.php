<?php

namespace App\Exports;

use App\Models\LeadMember;
use App\Services\LeadService;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Spatie\QueryBuilder\QueryBuilder;

class LeadMembersExport implements FromQuery, WithHeadings, WithMapping
{
    use Exportable;

    public function query(): QueryBuilder
    {
        return (new LeadService())->getLeadMembersQueryBuilder();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'Email',
            'Mobile',
            'Location',
            'Last Contacted',
            'Next Follow Up',
            'Referer',
            'Source',
            'From',
            'Captured',
        ];
    }

    /**
     * @param  LeadMember  $row
     */
    public function map($row): array
    {
        return [
            $row->getKey(),
            $row->user->full_name,
            $row->user->email,
            $row->user->mobile,
            $row->location?->name,
            $row->last_contacted_date,
            $row->next_follow_up_date,
            $row->referredBy?->full_name ?? '',
            $row->source,
            $row->type == 'referral' ? 'Admin' : ucfirst($row->type),
            $row->capturedBy?->full_name ?? '',
        ];
    }
}

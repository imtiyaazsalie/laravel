<?php

namespace App\Exports\Admin;

use App\Enums\UserType;
use App\Models\TenantUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class UserExport implements FromQuery, WithHeadings, WithMapping
{
    use Exportable;

    public function query()
    {
        $userGroupIds = match (request()->input('filter.user_group')) {
            'all' => array_merge(UserType::staffUserTypeIds(), [UserType::GYM_MEMBER->value]),
            'staff' => UserType::staffUserTypeIds(),
            'member' => [UserType::GYM_MEMBER->value],
        };

        return QueryBuilder::for(TenantUser::class)
            ->withoutGlobalScopes()
            ->selectRaw('
                DISTINCT
                users.name,
                users.surname,
                users.email,
                users.mobile,
                user_to_box.user_type_id,
                boxes.box_desc as tenant_name,
                ubf.box_facility_name as location_name,
                regions.region_desc as region_name
            ')
            ->join('boxes', 'boxes.box_id', '=', 'user_to_box.box_id')
            ->join('box_facility', 'boxes.box_id', '=', 'box_facility.box_id')
            ->join('users', 'users.user_id', '=', 'user_to_box.user_id')
            ->leftJoin('user_to_facility', function (JoinClause $join) {
                $join->on('user_to_box.user_id', '=', 'user_to_facility.user_id')
                    ->where('user_to_facility.end_date', '>=', today()->toDateString())
                    ->whereIn('user_to_box.user_type_id', [UserType::GYM_MEMBER->value, UserType::BOX_FACILITY_ADMIN->value, UserType::LOCATION_CHECK_IN->value]);
            })
            ->leftJoin('box_facility as ubf', 'user_to_facility.box_facility_id', '=', 'ubf.box_facility_id')
            ->join('regions', 'boxes.region_id', '=', 'regions.region_id')
            ->whereIn('user_to_box.user_type_id', $userGroupIds)
            ->where('user_to_box.end_date', '>=', today()->toDateString())
            ->where('user_to_box.deleted', '=', 0)
            ->where('users.deleted', '=', 0)
            ->whereNotIn('users.user_type_id', [1, 7])
            ->when(request()->input('filter.user_group') === 'member', function (Builder $query) {
                $query->whereRaw('user_to_facility.box_facility_id = box_facility.box_facility_id');
            })
            ->orderBy('boxes.box_desc')
            ->orderBy('users.name')
            ->orderBy('users.surname')
            ->allowedFilters([
                AllowedFilter::exact('tenant_id', 'box_id'),
                AllowedFilter::exact('tenant_status_id', 'boxes.box_status_id'),
                AllowedFilter::exact('location_id', 'user_to_facility.box_facility_id'),
                AllowedFilter::exact('region_id', 'regions.region_id'),
                AllowedFilter::exact('user_status_id', 'user_to_box.user_status_id'),
                AllowedFilter::exact('location_active', 'box_facility.is_active'),
            ]);
    }

    public function headings(): array
    {
        return [
            'Name',
            'Surname',
            'Email',
            'Mobile',
            'User Type',
            'Tenant',
            'Location',
            'Region',
        ];
    }

    public function map($row): array
    {
        return [
            $row->name,
            $row->surname,
            $row->email,
            $row->mobile,
            $row->user_type_id?->toString(),
            $row->tenant_name,
            $row->location_name,
            $row->region_name,
        ];
    }
}

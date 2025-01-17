<?php

namespace App\Http\Controllers\API;

use App\Exports\Admin\UserExport;
use App\Exports\LocationExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Exports\ExportLocationsRequest;
use App\Http\Requests\Admin\Exports\ExportUsersRequest;
use App\Models\Location;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Knuckles\Scribe\Attributes\QueryParam;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ExportController extends Controller
{
    #[QueryParam('filter[tenant_id]', 'integer', required: false)]
    #[QueryParam('filter[location_id]', 'integer', required: false)]
    #[QueryParam('filter[user_status_id]', 'integer', required: false)]
    #[QueryParam('filter[tenant_status_id]', 'integer', required: false)]
    #[QueryParam('filter[region_id]', 'integer', required: false)]
    #[QueryParam('filter[location_active]', 'boolean', required: false)]
    #[QueryParam('filter[user_group]', 'string', required: false)]
    public function users(ExportUsersRequest $request)
    {
        (new UserExport())->store($path = 'admin-exports/users-export.xls', 'tmp');

        return response()->json(['file' => Storage::disk('tmp')->temporaryUrl($path, now()->addMinutes(15))]);
    }

    #[QueryParam('filter[tenant_id]', 'integer', required: false)]
    #[QueryParam('filter[region_id]', 'integer', required: false)]
    public function locations(ExportLocationsRequest $request)
    {
        $startDate = $request->input('filter.start_date');
        $endDate = $request->input('filter.end_date');

        $locations = QueryBuilder::for(Location::class)
            ->join('boxes', 'box_facility.box_id', '=', 'boxes.box_id')
            ->leftJoin('box_facilities_to_health_providers', 'box_facility.box_facility_id', '=', 'box_facilities_to_health_providers.box_facility_id')
            ->leftJoin('health_providers', 'box_facilities_to_health_providers.health_provider_id', '=', 'health_providers.id')
            ->leftJoin('user_to_facility', function ($join) {
                $join->on('box_facility.box_facility_id', '=', 'user_to_facility.box_facility_id')
                    ->whereBetween(DB::raw('CURDATE()'), [DB::raw('user_to_facility.effective_date'), DB::raw('user_to_facility.end_date')]);
            })
            ->leftJoin('users', function ($join) {
                $join->on('user_to_facility.user_id', '=', 'users.user_id')
                    // we might need to change this and join utb too this is to cater for decoupling
                    ->where('users.user_type_id', '=', 11)
                    ->where('users.user_status_id', '=', 2)
                    ->where('users.deleted', '=', 0);
            })
            ->leftJoin(DB::raw("(SELECT
                boxFacility.box_facility_id,
                COUNT(DISTINCT class_bookings.class_booking_id) AS count
            FROM class_bookings
            INNER JOIN classes ON class_bookings.class_id = classes.class_id
            INNER JOIN class_to_dates ON class_bookings.class_to_date_id = class_to_dates.class_to_date_id
            INNER JOIN box_facility AS boxFacility ON classes.box_facility_id = boxFacility.box_facility_id
            WHERE class_to_dates.class_date BETWEEN '{$startDate}' AND '{$endDate}' AND class_bookings.class_booking_status_id = 1
            GROUP BY boxFacility.box_facility_id
            ) AS cb"), function ($join) {
                $join->on('cb.box_facility_id', '=', 'box_facility.box_facility_id');
            })
            ->where('box_facility.is_active', '=', 1)
            ->where('boxes.box_status_id', '=', 1)
            ->groupBy('box_facility.box_facility_id')
            ->orderBy('boxes.box_desc')
            ->orderBy('box_facility.box_facility_name')
            ->selectRaw("boxes.box_desc as 'tenant_name',
            box_facility.box_facility_name as 'location_name',
            health_providers.name as 'health_provider_name',
            COUNT(DISTINCT users.user_id) as 'total_active_users',
            IF(cb.count IS NULL, 0, cb.count) as 'total_bookings'")
            ->allowedFilters([
                AllowedFilter::exact('tenant_id', 'boxes.box_id'),
                AllowedFilter::exact('region_id', 'boxes.region_id'),
            ])
            ->get();

        (new LocationExport($locations))->store($path = 'admin-exports/locations-export.xls', 'tmp');

        return response()->json(['file' => Storage::disk('tmp')->temporaryUrl($path, now()->addMinutes(15))]);
    }
}

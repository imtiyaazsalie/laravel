<?php

namespace App\Services;

use App\Models\ClassDate;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function getUsersForNonAttendance(int $tenantId, ?int $locationId): Collection
    {
        $lastClassAttendedDateSql = '(
            SELECT class_to_dates.class_date
            FROM class_bookings
            LEFT JOIN class_to_dates class_to_dates ON class_to_dates.class_to_date_id = class_bookings.class_to_date_id
            LEFT JOIN classes ON classes.class_id = class_to_dates.class_id
            LEFT JOIN boxes ON boxes.box_id = classes.box_id
            WHERE class_bookings.user_id = users.user_id
            AND class_bookings.class_booking_status_id IN (1,6)
            ORDER BY class_bookings.class_booking_id DESC
            LIMIT 1
        ) as last_attended';

        return User::query()
            ->select('users.*')
            ->addSelect('user_to_box.user_to_box_id as user_tenant_id')
            ->addSelect('user_to_box.user_type_id as user_tenant_type_id')
            ->addSelect(DB::raw($lastClassAttendedDateSql))
            ->addSelect(DB::raw("IF (users.profilepic IS NULL OR users.profilepic = '', NULL, CONCAT('".config('filesystems.public.endpoint')."', users.profilepic)) AS image"))
            ->leftJoin('user_to_box', function ($join) {
                $join->on('user_to_box.user_id', '=', 'users.user_id')->where(function ($query) {
                    $query->whereRaw('CURDATE() between user_to_box.effective_date and user_to_box.end_date');
                });
            })
            ->leftJoin('user_to_facility', function ($join) {
                $join->on('user_to_facility.user_id', '=', 'users.user_id')
                    ->where(function ($query) {
                        $query->whereRaw('CURDATE() between user_to_facility.effective_date and  user_to_facility.end_date');
                    });
            })
            ->where('user_to_box.user_status_id', '=', 2)
            ->where('user_to_box.user_type_id', '=', 4)
            ->where('user_to_box.deleted', '=', false)
            ->where('user_to_box.box_id', '=', $tenantId)
            ->where('user_to_facility.box_facility_id', '=', $locationId)
            ->orderBy('name')
            ->get();
    }

    public function getNonAttendanceUsers(Collection $users, int $weekAgo, Tenant $tenant, Location $location): array
    {
        $usersWithClassBookingsAfterDate = ClassDate::query()
            ->select('class_to_dates.class_to_date_id')
            ->addSelect('class_bookings.user_id')
            ->join('classes', 'class_to_dates.class_id', '=', 'classes.class_id')
            ->join('class_bookings', 'class_to_dates.class_to_date_id', '=', 'class_bookings.class_to_date_id')
            ->whereIn('class_booking_status_id', [1, 6])
            ->whereDate('class_to_dates.class_date', '>=', date('Y-m-d', strtotime("-$weekAgo weeks")))
            ->where('box_id', $tenant->getKey())
            ->when($tenant->limit_inter_facility_bookings == true, function ($query) use ($location) {
                return $query->where('box_facility_id', $location->getKey());
            })
            ->groupBy('class_bookings.user_id')
            ->get()
            ->pluck('user_id')
            ->toArray();

        $nonAttendanceMembers = [];

        foreach ($users as $user) {

            if (! $user->getKey()) {
                continue;
            }

            // If user is in $usersWithClassBookingsAfterDate means that user has attended so skip them
            if (in_array($user->getKey(), $usersWithClassBookingsAfterDate)) {
                continue;
            }

            $nonAttendanceMembers[] = [
                'user_id' => $user->getKey(),
                'user_tenant_id' => $user->user_tenant_id,
                'user_tenant_type_id' => $user->user_tenant_type_id,
                'name' => $user->name,
                'surname' => $user->surname,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'is_high_risk' => $user->is_high_risk,
                'image' => $user->image,
                'notes' => $user->notes,
                'last_attended' => $user->last_attended,
            ];
        }

        return $nonAttendanceMembers;
    }

    public function getWeeksAgoDateRange(int $weeksAgo): array
    {
        $days = ($weeksAgo * 7) - 7;

        if ($weeksAgo > 1) {
            $endDate = date('Y-m-d', strtotime("-$days days"));
        } else {
            $endDate = date('Y-m-d');
        }

        return [
            'start_date' => date('Y-m-d', strtotime("-$weeksAgo weeks")),
            'end_date' => $endDate,
        ];
    }
}

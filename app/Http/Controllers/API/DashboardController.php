<?php

namespace App\Http\Controllers\API;

use App\Enums\ClassBookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\GetContractsExpiringRequest;
use App\Http\Requests\Dashboard\GetNewSignUpsRequest;
use App\Http\Requests\Dashboard\GetNonAttendanceRequest;
use App\Http\Requests\Dashboard\GetScheduleStatsRequest;
use App\Models\ClassBooking;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function getScheduleStats(GetScheduleStatsRequest $request)
    {
        $classBookings = ClassBooking::query()
            ->join('class_to_dates', 'class_bookings.class_to_date_id', '=', 'class_to_dates.class_to_date_id')
            ->join('classes', 'class_to_dates.class_id', '=', 'classes.class_id')
            ->where('classes.box_id', $request->input('filter.tenant_id'))
            ->where('classes.box_facility_id', $request->input('filter.location_id'))
            ->where('class_to_dates.class_date', '=', today()->toDateString())
            ->get();

        return [
            'booked_classes_members' => $classBookings->filter(fn (ClassBooking $classBooking) => $classBooking->status === ClassBookingStatus::BOOKED)->count(),
            'cancelled_classes_members' => $classBookings->filter(fn (ClassBooking $classBooking) => in_array($classBooking->status, ClassBookingStatus::ALL_CANCELLED_STATUSES))->count(),
            'no_show_members' => $classBookings->filter(fn (ClassBooking $classBooking) => $classBooking->status === ClassBookingStatus::NO_SHOW)->count(),
        ];
    }

    public function getNonAttendance(GetNonAttendanceRequest $request): array
    {
        $tenant = Tenant::query()->findOrFail($request->input('filter.tenant_id'));
        $location = Location::query()->findOrFail($request->input('filter.location_id'));

        $users = (new DashboardService())->getUsersForNonAttendance($tenant->getKey(), $location->getKey());

        $nonAttendanceMembersByWeeks = [
            '1' => [],
            '2' => [],
            '3' => [],
            '4' => [],
        ];

        $i = 0;

        foreach ($nonAttendanceMembersByWeeks as $weekNum => $week) {
            $i++;

            // Get non-attendance members for week 1,2,3 and 4.
            $nonAttendanceMembersByWeeks[$weekNum] = (new DashboardService())->getNonAttendanceUsers($users, $weekNum, $tenant, $location);

            // Greater than week one and less or equal to week 4
            if ($i > 1 && $i <= 4) {
                $prevMatch = [];
                $currentMatch = [];

                foreach ($nonAttendanceMembersByWeeks[$i - 1] as $k => $v) {
                    $prevMatch[$v['user_id']] = [$i - 1, $k];
                }

                foreach ($nonAttendanceMembersByWeeks[$i] as $k => $v) {
                    $currentMatch[$v['user_id']] = [
                        $i,
                        $k,
                    ];

                    // Remove this member from the attendance list if they haven't attended a class yet.
                    if ($v['last_attended'] == null) {
                        unset($nonAttendanceMembersByWeeks[$i][$k]);
                    }
                }

                foreach ($prevMatch as $k => $v) {
                    if (array_key_exists($k, $currentMatch)) {
                        unset($nonAttendanceMembersByWeeks[$v[0]][$v[1]]);
                    }
                }
            }
        }

        foreach ($nonAttendanceMembersByWeeks as $index => $nonAttendanceMembersByWeek) {
            $results[$index] = array_values($nonAttendanceMembersByWeek);
        }

        foreach ($results as $outerIndex => $week) {
            foreach ($week as $innerIndex => $member) {
                $results[$outerIndex][$innerIndex]['is_high_risk'] = ! ($member['is_high_risk'] === '0');
            }
        }

        return $results;
    }

    public function getNewSignUps(GetNewSignUpsRequest $request): array
    {
        $gym = Tenant::query()->findOrFail($request->input('filter.tenant_id'));
        $location = Location::query()->findOrFail($request->input('filter.location_id'));

        $signUpMembersByWeeks = [
            '1' => [],
            '2' => [],
            '3' => [],
            '4' => [],
        ];

        foreach ($signUpMembersByWeeks as $weekNum => $week) {

            $dates = (new DashboardService())->getWeeksAgoDateRange($weekNum);

            $lastClassAttendedDateSql = "(SELECT ctd.class_date
            FROM class_bookings cb
            LEFT JOIN class_to_dates ctd ON ctd.class_to_date_id = cb.class_to_date_id
            LEFT JOIN classes c ON c.class_id = ctd.class_id
            LEFT JOIN boxes b ON b.box_id = c.box_id
            WHERE cb.user_id = users.user_id
            AND cb.class_booking_status_id = 1
            AND c.box_id = $gym->box_id
            AND ctd.class_date <= CURDATE()
            ORDER BY cb.class_booking_id DESC
            LIMIT 1) as lastAttended";

            $signUpMembersByWeeks[$weekNum] = User::query()
                ->select(
                    'user_to_box.user_to_box_id as tenant_user_id',
                    'users.user_id',
                    'users.name',
                    'users.surname',
                    'users.email',
                    'users.mobile',
                    'user_to_box.created_on',
                    'user_to_box.high_risk AS is_high_risk',
                    'user_to_box.notes AS notes'
                )
                ->addSelect(DB::raw("IF (users.profilepic IS NULL OR users.profilepic = '', NULL, CONCAT('".config('filesystems.disks.public.endpoint')."', users.profilepic)) AS image"))
                ->addSelect(DB::raw($lastClassAttendedDateSql))
                ->leftJoin('user_to_box', function ($join) {
                    $join->on('user_to_box.user_id', '=', 'users.user_id')->where(function ($query) {
                        $query->where('user_to_box.end_date', '>=', today()->toDateString());
                    });
                })
                ->leftJoin('user_to_facility', function ($join) {
                    $join->on('user_to_facility.user_id', '=', 'users.user_id');
                })
                ->leftJoin('box_facility', 'box_facility.box_facility_id', '=', 'user_to_facility.box_facility_id')
                ->when($location->exists(), function ($query) use ($location) {
                    return $query->where('user_to_facility.box_facility_id', $location->getKey())
                        ->whereRaw('CURDATE() between user_to_facility.effective_date and  user_to_facility.end_date');
                })
                ->where('user_to_box.user_status_id', '=', 2)
                ->where('user_to_box.user_type_id', '=', 4)
                ->where('user_to_box.box_id', '=', $gym->getKey())
                ->where('box_facility.is_active', true)
                ->where('users.deleted', false)
                ->whereBetween('user_to_box.effective_date', [$dates['start_date'], $dates['end_date']])
                ->orderBy('name')
                ->get();
        }

        foreach ($signUpMembersByWeeks as $outerIndex => $week) {
            foreach ($week as $innerIndex => $member) {
                $signUpMembersByWeeks[$outerIndex][$innerIndex]['is_high_risk'] = ! ($member['is_high_risk'] === '0');
            }
        }

        return $signUpMembersByWeeks;
    }

    public function getContractsExpiring(GetContractsExpiringRequest $request): array
    {
        $gym = Tenant::query()->findOrFail($request->input('filter.tenant_id'));
        $location = Location::query()->findOrFail($request->input('filter.location_id'));

        $membersByWeeks = [
            '1' => [],
            '2' => [],
            '3' => [],
            '4' => [],
        ];

        foreach ($membersByWeeks as $weekNum => $week) {

            $date = date('Y-m-d', strtotime("+$weekNum weeks"));

            if ($weekNum > 1) {
                $previousWeek = $weekNum - 1;
                $endDate = date('Y-m-d', strtotime("+$previousWeek weeks"));
            } else {
                $endDate = null;
            }

            $membersByWeeks[$weekNum] = User::query()
                ->select(
                    'user_to_box.user_to_box_id AS tenant_user_id',
                    'users.user_id AS user_id',
                    'users.name',
                    'users.surname',
                    'users.email',
                    'users.mobile',
                    'user_to_box.high_risk AS is_high_risk',
                    'user_to_box.notes',
                    'user_contracts.ending_on',
                )
                ->addSelect(DB::raw("IF (users.profilepic IS NULL OR users.profilepic = '', NULL, CONCAT('".config('filesystems.disks.public.endpoint')."', users.profilepic)) AS image"))
                ->leftJoin('user_to_box', function ($join) {
                    $join->on('user_to_box.user_id', '=', 'users.user_id')->where(function ($query) {
                        $query->whereRaw('CURDATE() between user_to_box.effective_date and user_to_box.end_date');
                    });
                })
                ->leftJoin('user_to_facility', 'user_to_facility.user_id', '=', 'users.user_id')
                ->leftJoin(DB::raw('(SELECT uc1.user_id, uc1.ending_on, uc1.box_facility_id
                 FROM user_contracts as uc1
                 WHERE uc1.ending_on = (
                     SELECT MAX(uc2.ending_on)
                     FROM user_contracts AS uc2
                     WHERE uc2.user_id = uc1.user_id
                     AND uc2.box_facility_id = '.$location->getKey().'
                 )
                 AND uc1.box_facility_id = '.$location->getKey().') AS user_contracts'),
                    'user_contracts.user_id', '=', 'users.user_id')
                ->when($location, function ($query) use ($location) {
                    return $query->where('user_to_facility.box_facility_id', '=', $location->getKey())
                        ->whereRaw('CURDATE() between user_to_facility.effective_date and  user_to_facility.end_date');
                })
                ->when($endDate, function ($query) use ($endDate) {
                    return $query->where('user_contracts.ending_on', '>', $endDate);
                })
                ->where('user_contracts.ending_on', '<=', $date)
                ->where('user_contracts.ending_on', '>=', today()->toDateString())
                ->where('user_to_box.user_status_id', '=', 2)
                ->where('user_to_box.user_type_id', '=', 4)
                ->where('user_to_box.box_id', '=', $gym->getKey())
                ->orderBY('name')
                ->get();
        }

        foreach ($membersByWeeks as $outerIndex => $week) {
            foreach ($week as $innerIndex => $member) {
                $membersByWeeks[$outerIndex][$innerIndex]['is_high_risk'] = ! ($member['is_high_risk'] === '0');
            }
        }

        return $membersByWeeks;
    }
}

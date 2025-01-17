<?php

namespace App\Jobs;

use App\Enums\TenantStatus;
use App\Models\ClassDate;
use App\Services\ClassService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class AutoCancelClassesAndBookingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $minBookedMembersCountSql = 'IF(class_to_dates.min_booked_members_count IS NULL, classes.min_booked_members_count, class_to_dates.min_booked_members_count)';
        $classBookingsCountSql = '(SELECT COUNT(*) FROM class_bookings WHERE class_bookings.class_to_date_id = class_to_dates.class_to_date_id AND class_bookings.class_booking_status_id = 1)';

        $classDates = ClassDate::query()
            ->select('class_to_dates.*')
            ->join('classes', 'class_to_dates.class_id', '=', 'classes.class_id')
            ->join(DB::raw('boxes FORCE INDEX FOR JOIN (`PRIMARY`)'), 'classes.box_id', '=', 'boxes.box_id')
            ->join('box_facility', 'classes.box_facility_id', '=', 'box_facility.box_facility_id')
            ->join('timezones', 'timezones.timezone_id', '=', DB::raw('IF(box_facility.timezone_id IS NULL, boxes.timezone_id, box_facility.timezone_id)'))
            ->where('class_to_dates.is_active', '=', true)
            ->where('boxes.box_status_id', '=', TenantStatus::ACTIVE)
            ->where(function (Builder $query) {
                $query->whereNotNull('classes.min_booked_members_count')
                    ->orWhereNotNull('class_to_dates.min_booked_members_count');
            })
            ->where(function (Builder $query) {
                $query->whereNotNull('classes.auto_cancel_threshold_min')
                    ->orWhereNotNull('class_to_dates.auto_cancel_threshold_min');
            })
            ->whereRaw("$minBookedMembersCountSql > $classBookingsCountSql")
            ->whereRaw('class_to_dates.class_date BETWEEN CURDATE() AND CURDATE() + INTERVAL 7 DAY')
            ->whereRaw("DATE_SUB(CONVERT_TZ(ADDTIME(CONVERT(class_to_dates.class_date, DATETIME), IF(class_to_dates.start_time IS NULL, classes.start_time, class_to_dates.start_time)), timezones.`zone`, 'UTC'), INTERVAL IF(class_to_dates.auto_cancel_threshold_min IS NULL, classes.auto_cancel_threshold_min, class_to_dates.auto_cancel_threshold_min) MINUTE) <= NOW()")
            ->groupBy('class_to_dates.class_to_date_id')
            ->get();

        foreach ($classDates as $classDate) {
            (new ClassService())->deactivateClassDate($classDate, true);
        }
    }
}

<?php

namespace App\Http\Controllers\API\Reports;

use App\Enums\ClassBookingStatus;
use App\Helpers\JsonResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\Attendance\ClassAttendanceDetailsRequest;
use App\Http\Requests\Reports\Attendance\ClassAttendanceOver24HoursRequest;
use App\Http\Requests\Reports\Attendance\ClassAttendanceRequest;
use App\Http\Requests\Reports\Attendance\FirstBookingsRequest;
use App\Http\Requests\Reports\Attendance\MailNonAttendanceMembersRequest;
use App\Http\Requests\Reports\Attendance\MemberAttendanceDetailsRequest;
use App\Http\Requests\Reports\Attendance\MemberAttendanceRequest;
use App\Http\Requests\Reports\Attendance\MemberNonAttendanceRequest;
use App\Http\Requests\Reports\Attendance\OverviewRequest;
use App\Http\Resources\ClassBookingResource;
use App\Http\Resources\ClassResource;
use App\Http\Resources\TenantUserResource;
use App\Models\Location;
use App\Services\CrmService;
use App\Services\ReportsService;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class AttendanceController extends Controller
{
    public function __construct(protected ReportsService $reportsService)
    {
    }

    public function overview(OverviewRequest $request)
    {
        return response()->json($this->reportsService->attendanceOverview($request));
    }

    public function classAttendance(ClassAttendanceRequest $request)
    {
        return $this->reportsService->classAttendance()->map(function ($class) {
            return [
                'class' => new ClassResource($class->withoutRelations()),
                'class_dates_count' => $class->class_date_count,
                'capacity' => (int) $class->capacity,
                'booked' => (int) $class->booked,
                'cancelled' => (int) $class->cancelled,
                'cancelled_after_threshold' => (int) $class->cancelled_after_threshold,
                'cancelled_by_coach' => (int) $class->cancelled_by_coach,
                'all_cancellations' => (int) $class->all_cancellations,
                'no_show' => (int) $class->no_show,
                'checked_in' => (int) $class->checked_in,
            ];
        });
    }

    public function classAttendanceDetails(ClassAttendanceDetailsRequest $request)
    {
        return ClassBookingResource::collection(
            $this->reportsService->classAttendanceDetails(
                ClassBookingStatus::from($request->filter['booking_status_id']),
                Carbon::parse($request->filter['start_date']),
                Carbon::parse($request->filter['end_date']),
            )
        );
    }

    public function memberAttendance(MemberAttendanceRequest $request)
    {
        return JsonResource::collection($this->reportsService->memberAttendance());
    }

    public function memberAttendanceDetails(MemberAttendanceDetailsRequest $request)
    {
        return ClassBookingResource::collection(
            $this->reportsService->memberAttendanceDetails(
                ClassBookingStatus::from($request->filter['booking_status_id']),
                Carbon::parse($request->filter['start_date']),
                Carbon::parse($request->filter['end_date']),
            )
        );
    }

    public function memberNonAttendance(MemberNonAttendanceRequest $request)
    {
        return TenantUserResource::collection($this->reportsService->getNonAttendanceData($request));
    }

    public function mailNonAttendanceMembers(MailNonAttendanceMembersRequest $request): Response
    {
        $crmService = resolve(CrmService::class);

        set_time_limit(300);

        $message = $request->safe()->collect()->get('message');
        $subject = $request->safe()->collect()->get('subject');
        $location = Location::query()->find($request->safe()->collect()->get('location_id'));
        $startDate = $request->safe()->collect()->get('start_date');
        $endDate = $request->safe()->collect()->get('end_date');

        $nonAttendanceMembers = $this->reportsService->getNonAttendanceMembers($location, $startDate, $endDate);

        if (! $nonAttendanceMembers) {
            abort(404, 'No members to send the non-attendance mail to found.');
        }

        foreach ($nonAttendanceMembers as $nonAttendanceMember) {
            // Create a scheduled email
            $crmService->createScheduledEmail(
                content: $message,
                subject: $subject,
                to: $nonAttendanceMember['email'],
                replyTo: $crmService->getReplyTo($location),
                tenant: $location->tenant,
                location: $location
            );
        }

        return response()->noContent();
    }

    public function firstBookings(FirstBookingsRequest $request)
    {
        return response()->json($this->reportsService->firstBookings($request));
    }

    public function classAttendanceOver24Hours(ClassAttendanceOver24HoursRequest $request)
    {
        return response()->json($this->reportsService->classAttendanceOver24Hours($request));
    }
}

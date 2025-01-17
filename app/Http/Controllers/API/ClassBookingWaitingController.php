<?php

namespace App\Http\Controllers\API;

use App\Enums\ClassBookingWaitingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClassBookingWaiting\CreateWaitingListBookingRequest;
use App\Http\Requests\ClassBookingWaiting\LeaveWaitingListRequest;
use App\Http\Requests\ClassBookingWaiting\MessageWaitingListBookingAthleteRequest;
use App\Http\Requests\ClassBookingWaiting\ReadWaitingBookingRequest;
use App\Http\Resources\ClassBookingWaitingResource;
use App\Models\ClassBookingWaitingList;
use App\Models\ClassDate;
use App\Services\ClassBookingsService;
use App\Services\ClassBookingsWaitingService;
use App\Services\ClassService;
use App\Services\CrmService;
use App\Services\TenantUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ClassBookingWaitingController extends Controller
{
    public function __construct(
        public ClassBookingsService $classBookingsService
    ) {
    }

    public function getById(ReadWaitingBookingRequest $request, ClassBookingWaitingList $booking): ClassBookingWaitingResource
    {
        return new ClassBookingWaitingResource(
            $booking->load(['class', 'classDate', 'userPackage', 'user', 'createdBy', 'updatedBy'])
        );
    }

    public function store(CreateWaitingListBookingRequest $request): ClassBookingWaitingResource|JsonResponse
    {
        $user = auth()->user();

        $classDate = ClassDate::find($request->safe()->collect()->get('class_date_id'));
        $userId = $request->safe()->collect()->get('user_id');
        $tenantId = $request->safe()->collect()->get('tenant_id');

        $member = (new TenantUserService())->getCurrentUserTenantForTenant($userId, $tenantId);
        $tenantUser = (new TenantUserService())->getCurrentUserTenantForTenant($user, $tenantId);

        if (! $member) {
            abort(400, 'User does not belong to this tenant.');
        }

        $latestBookingTime = (new ClassService())->getClassDateStartDateTime($classDate);
        $latestBookingTime->subMinutes($classDate->class->booking_threshold);

        if ($latestBookingTime->isPast() && $user->getAuthIdentifier() == $member->user_id) {
            abort(400, 'This booking threshold for this class has passed');
        }

        if ($tenantUser->isMember()) {
            $canAthleteJoinWaitingListForClassDate = (new ClassService())->canAthleteJoinWaitingListForClassDate($classDate, $member, true);
        } else {
            $canAthleteJoinWaitingListForClassDate = (new ClassService())->canCoachBookAthleteOntoWaitingListForClassDate($classDate, $member->user, true);
        }

        if (is_string($canAthleteJoinWaitingListForClassDate)) {
            abort(400, $canAthleteJoinWaitingListForClassDate);
        }

        if ($user->getAuthIdentifier() !== $member->user_id) {
            if (is_string($userPackageOrError = (new ClassService())->canCoachBookAthleteForClassDate($classDate, $member->user, true))) {
                abort(400, $userPackageOrError);
            }
        } else {
            if (is_string($userPackageOrError = (new ClassService())->canAthleteBookForClassDate($classDate, $member->user, true, true))) {
                abort(400, $userPackageOrError);
            }
        }

        // has athlete already joined waiting list?
        $classBookingWaiting = (new ClassBookingsWaitingService())->getWaitingForClassAndDateAndUser($classDate->class, $classDate, $member->user);

        if ($classBookingWaiting instanceof ClassBookingWaitingList) {
            abort(400, 'Already joined waiting list');
        }

        $classBookingWaiting = ClassBookingWaitingList::query()->create([
            'status' => ClassBookingWaitingStatus::WAITING->value,
            'class_id' => $classDate->class->getKey(),
            'class_to_date_id' => $classDate->getKey(),
            'user_id' => $userId,
            'created_by_id' => auth()->user()->getAuthIdentifier(),
            'user_package_id' => $userPackageOrError?->getKey(),
        ]);

        return new ClassBookingWaitingResource($classBookingWaiting);
    }

    public function sendMessageToAthleteOnWaitingList(MessageWaitingListBookingAthleteRequest $request, ClassBookingWaitingList $booking): Response
    {
        /** @var CrmService */
        $crm = resolve(CrmService::class);

        $classDate = $booking->classDate;
        $class = $classDate->class;

        // Send message to class booking (user, lead or non-member)
        (new ClassService())->sendMessageToClassBookingOrClassWaitingBooking($booking, $request->safe()->collect()->get('message'));

        if ($booking->user) {
            $crm->createScheduledPushNotification(
                title: 'Message From Instructor',
                content: $request->safe()->collect()->get('message'),
                user: $booking->user,
                tenant: $class->tenant,
                location: $class->location
            );
        }

        $crm->createScheduledEmailForNotification(
            tenantOrLocation: $booking->class->location,
            context: 'confirmation_member_message',
            recipient: auth()->user(),
            data: [
                'coach_name' => $booking->user->name,
                'coach_surname' => $booking->user->surname,
                'coach_message' => nl2br($request->safe()->collect()->get('message')),
                'class_name' => $classDate->name(),
                'class_time' => $classDate->startTime().' - '.$classDate->endTime(),
                'class_booking_date' => $classDate->buildDateTime()->format('D, d F Y'),
            ]
        );

        return response()->noContent();
    }

    public function leaveWaitingList(LeaveWaitingListRequest $request, ClassBookingWaitingList $booking): ClassBookingWaitingResource
    {
        $cancelResult = (new ClassService())->cancelBookingWaitingForClassDateByUser($booking, $request->user());

        if (! $cancelResult) {
            abort(400, 'Something went wrong while removing athlete from the waiting list.');
        }

        return new ClassBookingWaitingResource($booking);
    }
}

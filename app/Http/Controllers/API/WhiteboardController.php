<?php

namespace App\Http\Controllers\API;

use App\Enums\ClassBookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Whiteboard\WhiteboardRequest;
use App\Http\Requests\Whiteboard\WhiteboardResultsRequest;
use App\Http\Resources\ClassBookingResource;
use App\Http\Resources\UserTenantResource;
use App\Http\Resources\WodCaptureResource;
use App\Models\ClassBooking;
use App\Models\ClassDate;
use App\Models\Injury;
use App\Models\LeadMember;
use App\Models\Location;
use App\Models\Programme;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\Wod;
use App\Models\WodCapture;
use App\Models\WodCaptureExercise;
use App\Services\ClassBookingsService;
use App\Services\TenantUserService;
use App\Services\UserService;
use App\Services\WODCaptureExerciseService;
use App\Services\WODCaptureService;
use App\Services\WODService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class WhiteboardController extends Controller
{
    public function get(WhiteboardRequest $request)
    {
        $programmeId = $request->input('filter.programme_id');

        $boxFacility = Location::query()->find($request->input('filter.location_id'));
        $date = $request->input('filter.date');
        $programme = Programme::query()->find($programmeId);
        $classDate = $request->input('filter.class_date_id') ? ClassDate::query()->find($request->input('filter.class_date_id')) : null;
        $showNoneBookingMembers = $request->input('filter.show_none_booking_members');

        $date = new \DateTime($date);
        $sortBy = $request->input('sort_by', 'A');
        $wod = (new WODService())->getWodForBoxAndDateAndProgramme($boxFacility->tenant, $date, $programme);

        $whiteboardData = [];
        $parts = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P'];

        if ($showNoneBookingMembers) {
            // Get a list all wod captures
            $wodCaptures = (new WODCaptureService())->getWodCapturesByBoxByDateAndProgramme($boxFacility->tenant, $date, $programme);

            /** @var WodCapture $wodCapture */
            foreach ($wodCaptures as $wodCapture) {
                $classBookings = (new ClassBookingsService())->getMemberBookingsForDates($wodCapture->user, $date, $date, [ClassBookingStatus::BOOKED->value]);

                if ($classBookings) {
                    continue;
                }

                // Gym member
                $athlete = $wodCapture->user;
                $userBoxMembership = (new TenantUserService())->getCurrentUserTenantForTenant($athlete, $wodCapture->wod->tenant);

                $data = [
                    'wod_id' => $wod instanceof Wod ? $wod->getKey() : null,
                    'user' => new UserTenantResource($userBoxMembership),
                    'capture_exercises' => [],
                    'sort_value' => 0,
                ];

                $data['user']['is_injured'] = (new UserService())->getLatestInjuryForUser($athlete) instanceof Injury;

                /** @var WodCaptureExercise $wodCaptureExercise */
                $wodCaptureActiveExercises = WodCaptureExercise::query()
                    ->where('is_active', true)
                    ->where('wod_capture_id', $wodCapture->getKey())
                    ->get();

                foreach ($wodCaptureActiveExercises as $wodCaptureExercise) {
                    $data['capture_exercises'][$wodCaptureExercise->exercise_id] = [
                        'id' => $wodCaptureExercise->getKey(),
                        'exercise_id' => $wodCaptureExercise->exercise_id,
                        'wod_capture_id' => $wodCaptureExercise->wod_capture_id,
                        'personal_best' => $wodCaptureExercise->is_pb,
                        'is_rx' => $wodCaptureExercise->is_rx,
                        'score' => $wodCaptureExercise->score,
                        'note' => $wodCaptureExercise->note,
                    ];
                }

                $data['capture_exercises'] = array_values($data['capture_exercises']);

                $whiteboardData[] = $data;
            }
        } else {
            if ($classDate instanceof ClassDate) {
                $classBookings = (new ClassBookingsService())->getBookingsForClassDate($classDate);
            } else {
                $classBookings = (new ClassBookingsService())->getClassBookingsForBoxFacilityAndDate($boxFacility, $date);
            }

            $classBookings->loadMissing('classDate', 'createdBy', 'updatedBy');

            /** @var ClassBooking $classBooking */
            foreach ($classBookings as $classBooking) {
                request()->merge([
                    'internalAppend' => 'withoutTenant,withoutLocations,withoutBookings',
                ]);

                $data = [
                    'wod_id' => $wod instanceof Wod ? $wod->getKey() : null,
                    'class_booking' => new ClassBookingResource(ClassBooking::query()->with('classDate')->find($classBooking->class_booking_id)),
                    'capture_exercises' => [],
                    'sort_value' => 0,
                ];

                // If it is a lead or drop-in member
                if (! empty($classBooking->lead_member_id)) {
                    $leadMember = LeadMember::query()->find($classBooking->lead_member_id);
                    $data['lead_member'] = [
                        'id' => $leadMember->lead_member_id,
                        'name' => $leadMember->first_name,
                        'surname' => $leadMember->last_name,
                        'email' => $leadMember->email_address,
                    ];
                } elseif (! $classBooking->user_id) {
                    $data['non_member'] = [
                        'name' => $classBooking->non_member_name,
                        'email' => $classBooking->non_member_email,
                    ];
                } else {
                    $athlete = User::query()->find($classBooking->user_id);

                    if (! $athlete) {
                        continue;
                    }

                    $userBoxMembership = (new TenantUserService())->getCurrentUserTenantForTenant($athlete, $classBooking->class->tenant);

                    $data['class_booking']['user'] = new UserTenantResource($userBoxMembership);
                    $data['class_booking']['user']['is_injured'] = (new UserService())->getLatestInjuryForUser($athlete) instanceof Injury;

                    $wodCapture = (new WODCaptureService())->getWodCaptureByUserAndDateAndWodProgramme($athlete, $date, $programme);

                    if ($wodCapture instanceof WodCapture) {
                        $wodCaptureExercises = (new WODCaptureExerciseService())->getWodCaptureExercisesByWodCapture($wodCapture);

                        /** @var WodCaptureExercise $wodCaptureExercise */
                        foreach ($wodCaptureExercises as $index => $wodCaptureExercise) {
                            if ((isset($sortBy) && $sortBy === $parts[$index]) || (! isset($sortBy) && $index === 0)) {
                                $data['sort_value'] = $wodCaptureExercise->score;
                            }

                            $data['capture_exercises'][$wodCaptureExercise->exercise_id] = [
                                'id' => $wodCaptureExercise->getKey(),
                                'exercise_id' => $wodCaptureExercise->exercise_id,
                                'wod_capture_id' => $wodCaptureExercise->wod_capture_id,
                                'personal_best' => $wodCaptureExercise->is_pb,
                                'is_rx' => $wodCaptureExercise->is_rx,
                                'score' => $wodCaptureExercise->score,
                                'note' => $wodCaptureExercise->note,
                            ];
                        }

                        $data['capture_exercises'] = array_values($data['capture_exercises']);
                    }
                }

                $whiteboardData[] = $data;
            }
        }

        $sortDirection = $request->get('sort_direction', 'asc');

        usort($whiteboardData, function ($arr1, $arr2) use ($sortDirection) {
            if ((isset($sortDirection) && $sortDirection === 'asc') || ! isset($sortDirection)) {
                return $arr1['sort_value'] > $arr2['sort_value'];
            } else {
                return $arr1['sort_value'] < $arr2['sort_value'];
            }
        });

        return response()->json(collect($whiteboardData)->paginate());
    }

    /**
     * Get whiteboard data
     *
     * @param  Request  $request
     */
    public function oldget(WhiteboardRequest $request)
    {
        $locationId = Arr::get($request->filter, 'location_id');
        $programmeId = Arr::get($request->filter, 'programme_id');
        $classDateId = Arr::get($request->filter, 'class_date_id');
        $date = Arr::get($request->filter, 'date');

        $showNonBookingMembers = filter_var(
            Arr::get($request->filter, 'show_none_booking_members'),
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        );

        $sortBy = Arr::get($request->filter, 'sort_by');

        $location = Location::query()->findOrFail($locationId);
        $programme = Location::query()->findOrFail($programmeId);

        $classDate = $classDateId ? ClassDate::findOrFail($classDateId) : null;

        //get wod by box, programme and date
        $wod = QueryBuilder::for(Wod::class)
            ->where('box_id', $location->tenant_id)
            ->where('programme_id', $programme->getKey())
            ->whereDate('wod_date', $date)
            ->first();

        $whiteboardData = [];
        $parts = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P'];

        if ($showNonBookingMembers) {

            $wodCaptures = QueryBuilder::for(WodCapture::class)
                ->joinRelationship('wod')
                ->with('exercises')
                ->where('wods.box_id', $location->tenant_id)
                ->where('wods.programme_id', $programme->getKey())
                ->whereDate('wods.wod_date', $date)
                ->orderBy('wods.wod_date', 'DESC')
                ->get();

            foreach ($wodCaptures as $capture) {

                $classBookings = ClassBooking::query()
                    ->with(['user.injuries', 'classDate.class', 'createdBy', 'updatedBy'])
                    ->booked()
                    ->between($date, $date)
                    ->where('user_id', $capture->user_id)
                    ->get();

                $tenantUser = TenantUser::query()
                    ->active()
                    ->with('user.injuries')
                    ->where('box_id', $location->tenant_id)
                    ->where('user_id', $capture->user_id)
                    ->first();

                if ($classBookings->isEmpty()) {
                    continue;
                }

                $data = [
                    'wod_id' => $wod?->getKey(),
                    'user_tenant' => $tenantUser ? new UserTenantResource($tenantUser) : null,
                    'capture_exercises' => [],
                    'sort_value' => 0,
                ];

                foreach ($capture->exercises as $captureExercise) {

                    $data['capture_exercises'][$captureExercise->exercise_id] = [
                        'id' => $captureExercise->getKey(),
                        'exercise_id' => $captureExercise->exercise_id,
                        'wod_capture_id' => $capture->getKey(),
                        'is_personal_best' => $captureExercise->is_personal_best,
                        'is_rx' => $captureExercise->is_rx,
                        'score' => $captureExercise->score,
                        'note' => $captureExercise->note,
                    ];

                }

                $data['capture_exercises'] = array_values($data['capture_exercises']);

                $whiteboardData[] = $data;

            }

        } else {

            //get class bookings for the program and box facility ID on the given date
            $classBookings = ClassBooking::query()
                ->with(['user.injuries', 'createdBy', 'updatedBy'])
                ->whereIn('class_booking_status_id', [
                    ClassBookingStatus::BOOKED->value,
                    ClassBookingStatus::NO_SHOW->value,
                    ClassBookingStatus::CHECKED_IN->value,
                ])
                ->when($classDate, function ($q) use ($classDate, $date, $location) {
                    $q->join('class_to_dates', 'class_to_dates.class_to_date_id', '=', 'class_bookings.class_to_date_id');
                    $q->join('classes', 'classes.class_id', '=', 'class_to_dates.class_id');
                    $q->where('class_to_date_id', $classDate->getKey())
                        ->where('class_to_dates.class_date', $date)
                        ->where('classes.box_facility_id', $location->getKey());

                    return $q;

                })
                ->get();

            foreach ($classBookings as $booking) {

                $data = [
                    'wod_id' => $wod?->getKey(),
                    'class_booking' => new ClassBookingResource($booking),
                    'capture_exercises' => [],
                    'sort_value' => 0,
                ];

                if ($booking->user_id) {

                    $wodCapture = WodCapture::query()
                        ->joinRelationship('wod')
                        ->with('exercises')
                        ->where('user_id', $booking->user_id)
                        ->where('wods.programme_id', $programme->getKey())
                        ->whereDate('wods.wod_date', $date)
                        ->orderBy('wods.wod_date', 'DESC')
                        ->first();

                    if ($wodCapture) {

                        foreach ($wodCapture->exercises as $index => $captureExercise) {

                            if (($sortBy && $sortBy === $parts[$index]) || (! isset($sortBy) && $index === 0)) {
                                $data['sortValue'] = $captureExercise->score;
                            }

                            $data['capture_exercises'][$captureExercise->exercise_id] = [
                                'id' => $captureExercise->getKey(),
                                'exercise_id' => $captureExercise->exercise_id,
                                'wod_capture_id' => $wodCapture->getKey(),
                                'is_personal_best' => $captureExercise->is_personal_best,
                                'is_rx' => $captureExercise->is_rx,
                                'score' => $captureExercise->score,
                                'note' => $captureExercise->note,
                            ];

                        }

                        $data['capture_exercises'] = array_values($data['capture_exercises']);
                    }
                }

                $whiteboardData[] = $data;

            }
        }

        $sortDirection = Arr::get($request->filter, 'sort_direction');

        usort($whiteboardData, function ($arr1, $arr2) use ($sortDirection) {
            if ((isset($sortDirection) && $sortDirection === 'asc') || ! isset($sortDirection)) {
                return $arr1['sort_value'] > $arr2['sort_value'];
            } else {
                return $arr1['sort_value'] < $arr2['sort_value'];
            }
        });

        return response()->json($whiteboardData);
    }

    /**
     * Get whiteboard results
     */
    public function results(WhiteboardResultsRequest $request): AnonymousResourceCollection
    {
        //get wod by box, programme and date
        $wod = QueryBuilder::for(Wod::class)
            ->with(['exercises' => function ($query) {
                $query->where('is_active', true);
            }])
            ->allowedFilters([
                AllowedFilter::exact('tenant_id', 'box_id'),
                AllowedFilter::exact('programme_id'),
                AllowedFilter::exact('date', 'wod_date'),
            ])
            ->firstOrFail();

        // get active wod captures
        $wodCaptures = WodCapture::with(['user', 'comments.user', 'likes.user', 'exercises' => function ($query) {
            $query->where('is_active', true);
        }])->where('wod_id', $wod->getKey())->get();

        return WodCaptureResource::collection($wodCaptures);
    }
}

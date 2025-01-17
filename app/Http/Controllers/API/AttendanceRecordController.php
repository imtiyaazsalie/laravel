<?php

namespace App\Http\Controllers\API;

use App\Enums\ClassBookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceRecords\CancelCheckInRequest;
use App\Http\Requests\AttendanceRecords\CheckInRequest;
use App\Http\Requests\AttendanceRecords\CheckOutRequest;
use App\Http\Resources\AttendanceRecordResource;
use App\Http\Resources\ClassBookingResource;
use App\Models\AttendanceRecord;
use App\Models\ClassBooking;
use App\Models\ClassDate;
use App\Models\HealthCareProvider;
use App\Models\Location;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\AttendanceRecordService;
use App\Services\ClassService;
use App\Services\TenantUserService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\Uid\Uuid;

class AttendanceRecordController extends Controller
{
    public function checkIn(CheckInRequest $request): JsonResponse
    {
        $now = now();
        $attendanceRecord = null;
        $hasAttendanceCode = $request->safe()->has('code');
        $classBooking = $request->safe()->collect()->get('class_booking_id') ? ClassBooking::query()->find($request->safe()->collect()->get('class_booking_id')) : null;
        $isStaffMember = false;

        $authUserTenant = null;

        if (! auth()->check() && $request->safe()->collect()->get('class_booking_id')) {
            abort(400, 'You may not provide a class booking ID for a non-member.');
        }

        if ($classBooking) {
            $authUserTenant = auth()->user()
                ? (new TenantUserService())->getCurrentUserTenantForTenant(auth()->user(), $classBooking->class->tenant_id)
                : null;

            if ($authUserTenant instanceof TenantUser && $authUserTenant->isStaff()) {
                $isStaffMember = true;
            }
        }

        if ($classBooking instanceof ClassBooking) {
            $authUserTenant = auth()->user()
                ? (new TenantUserService())->getCurrentUserTenantForTenant(auth()->user(), $classBooking->class->tenant)
                : null;

            $existingAttendanceRecordForClassBookings = AttendanceRecord::query()->where('class_booking_id', '=', $classBooking->getKey())->first();

            if ($existingAttendanceRecordForClassBookings instanceof AttendanceRecord) {
                abort(400, 'An attendance record already exists for this class booking.');
            }
        }

        if (! $hasAttendanceCode && ! $classBooking instanceof ClassBooking) {
            abort(400, 'Please make sure that either code or classBookingId is set');
        }

        if ($hasAttendanceCode) {
            $attendanceCode = Uuid::fromString($request->safe()->collect()->get('code'))->toBinary();
            $boxFacility = Location::whereAttendanceCode($attendanceCode)->first();

            if (! $boxFacility instanceof Location) {
                abort(400, 'The attendance code is invalid, please ask the studio to request a new one.');
            }

            $authUserTenant = auth()->check()
                ? (new TenantUserService())->getCurrentUserTenantForTenant(auth()->user(), $boxFacility->tenant)
                : null;

            // Check if code is still valid
            if (Carbon::now()->gte($boxFacility->attendance_code_expires_on)) {
                abort(400, 'The attendance code has expired, please ask the studio to request a new one.');
            }

            if ($classBooking instanceof ClassBooking && ($boxFacility->getKey() !== $classBooking->class->location->getKey())) {
                abort(400, 'Please ensure that you are at the location that your booking was made for.');
            }

            // Validate data
            $errors = (new AttendanceRecordService())->validatePostData($request, auth()->check());

            if (count($errors) > 0) {
                return response()->json($errors, 400);
            }

            // Determine if user is fully authenticated
            if (auth()->check() && $authUserTenant instanceof TenantUser) {
                // For logged in user
                $healthProvider = $authUserTenant->user->healthProvider;
            } else {
                // For non-member
                $healthProvider = HealthCareProvider::query()->find($request->safe()->collect()->get('health_provider_id'));

                if (! $healthProvider instanceof HealthCareProvider) {
                    abort(400, 'Health provider could not be found');
                }
            }

            // Check if user's healthProvider is used by this facility aka box
            if ($boxFacility->healthProviders()->where('health_provider_id', '=', $healthProvider?->getKey())->exists()) {
                // Create attendance record
                $attendanceRecord = new AttendanceRecord();
                $attendanceRecord->setAttribute('health_provider_id', $healthProvider->getKey());
                $attendanceRecord->setAttribute('checked_in_at', $now);
                $attendanceRecord->setAttribute('box_facility_id', $boxFacility->getKey());

                if (auth()->check() && $authUserTenant instanceof TenantUser) {
                    $classDate = $request->safe()->collect()->get('class_date_id') ? ClassDate::query()->find($request->safe()->collect()->get('class_date_id')) : null;

                    // Get classDate from booking if one was not sent and classBooking is present
                    if ($classBooking instanceof ClassBooking) {
                        // Check if classBooking belongs to this box
                        if ($classBooking->class->tenant->getKey() !== $boxFacility->tenant->getKey()) {
                            abort(400, 'Sorry, your user role does not have access to this resource.');
                        }

                        if (! $classDate instanceof ClassDate) {
                            $classDate = $classBooking->classDate;
                        }
                    }

                    // For logged in user
                    $attendanceRecord->setAttribute('user_id', auth()->user()->getAuthIdentifier());
                    $attendanceRecord->setAttribute('class_booking_id', $classBooking->getKey());
                    $attendanceRecord->setAttribute('class_to_date_id', $classDate->getKey());
                } else {
                    // For non-member
                    $attendanceRecord->setAttribute('id_number', $request->safe()->collect()->get('id_number'));
                    $attendanceRecord->setAttribute('name', $request->safe()->collect()->get('name'));
                    $attendanceRecord->setAttribute('surname', $request->safe()->collect()->get('surname'));
                    $attendanceRecord->setAttribute('date_of_birth', Carbon::parse($request->safe()->collect()->get('date_of_birth')));
                }

                $attendanceRecord->save();
            }
        } elseif ($classBooking instanceof ClassBooking && $classBooking->user instanceof User && $isStaffMember) {
            $user = $classBooking->user;
            $healthProvider = $user->health_provider_id;
            $class = $classBooking->classDate->class;
            $boxFacility = $class->location;

            // Create attendance record
            if ($boxFacility->healthProviders()->where('health_provider_id', '=', $healthProvider)->exists()) {
                $attributes = [
                    'health_provider_id' => $healthProvider,
                    'user_id' => $user->getKey(),
                    'class_booking_id' => $classBooking->getKey(),
                    'class_to_date_id' => $classBooking->classDate->getKey(),
                    'box_facility_id' => $boxFacility->getKey(),
                ];

                // Set class check-in and check-out time
                if ($class->is_virtual == 1) {
                    $classService = new ClassService();
                    $attributes['checked_in_at'] = $classService->getClassDateStartDateTime($classBooking->classDate);
                    $attributes['checked_out_at'] = $classService->getClassDateEndDateTime($classBooking->classDate);
                } else {
                    $attributes['checked_in_at'] = $now;
                }

                $attendanceRecord = AttendanceRecord::firstOrCreate(
                    ['class_booking_id' => $classBooking->getKey()],
                    $attributes
                );
            }
        }

        // Normal check-in for fully authenticated members
        if (auth()->check() && $authUserTenant instanceof TenantUser && $classBooking instanceof ClassBooking) {
            if ($classBooking->class->tenant_id !== $authUserTenant->tenant_id) {
                abort(400, 'Sorry, your user role does not have access to this resource.');
            }
            $classBooking->update([
                'is_checked_in' => true,
                'checked_in_at' => $now,
            ]);

            if ($isStaffMember && $classBooking->classDate->class->is_virtual == 1) {
                $classBooking->update([
                    'checked_in_at' => $classBooking->classDate->start_time ?? $classBooking->class->end_time,
                    'checked_out_at' => $classBooking->classDate->end_time ?? $classBooking->class->end_time,
                    'is_checked_out' => true,
                ]);
            }
        }

        // Set classBookingStatus to 'booked' when a check-in happens
        if ($classBooking instanceof ClassBooking && $classBooking->class_booking_status_id !== ClassBookingStatus::BOOKED->value) {
            $classBooking->update([
                'class_booking_status_id' => ClassBookingStatus::BOOKED->value,
            ]);
        }

        $data = [
            'classBooking' => $classBooking instanceof ClassBooking ? new ClassBookingResource($classBooking) : null,
            'attendanceRecord' => $attendanceRecord instanceof AttendanceRecord ? new AttendanceRecordResource($attendanceRecord) : null,
        ];

        return response()->json($data);
    }

    public function checkOut(CheckOutRequest $request): JsonResponse
    {
        $now = now();
        $attendanceRecord = null;
        $hasAttendanceCode = $request->safe()->has('code');
        $classBooking = $request->safe()->collect()->get('class_booking_id') ? ClassBooking::query()->find($request->safe()->collect()->get('class_booking_id')) : null;

        if (! auth()->check() && $request->safe()->collect()->get('class_booking_id')) {
            abort(400, 'You may not provide a class booking ID for a non-member.');
        }

        if (! $hasAttendanceCode && ! $classBooking instanceof ClassBooking) {
            abort(400, 'Please make sure that either code or class_booking_id is set');
        }

        if (auth()->check()) {
            $boxFacility = Location::whereAttendanceCode(Uuid::fromString($request->safe()->collect()->get('code'))->toBinary())
                ->first();

            if ($boxFacility?->box_facility_id != $classBooking->class->box_facility_id) {
                abort(400, 'The code is invalid.');
            }

        }

        // HealthProvider check-out
        if ($hasAttendanceCode) {
            $attendanceCode = Uuid::fromString($request->safe()->collect()->get('code'))->toBinary();
            $boxFacility = Location::whereAttendanceCode($attendanceCode)->first();

            if (! $boxFacility instanceof Location) {
                abort(400, 'The attendance code is invalid, please ask the studio to request a new one.');
            }

            // Check if code is still valid
            if (Carbon::now()->gte($boxFacility->attendance_code_expires_on)) {
                abort(400, 'The attendance code has expired, please ask the studio to request a new one.');
            }

            if (auth()->check()) {
                $attendanceRecord = (new AttendanceRecordService())->getLatestAttendanceRecordForMember(auth()->user(), $classBooking, true, false);
            } else {
                $attendanceRecord = AttendanceRecord::query()
                    ->where('id_number', '=', $request->safe()->collect()->get('id_number'))
                    ->orderBy('id', 'desc')
                    ->first();

                if ($attendanceRecord instanceof AttendanceRecord && $attendanceRecord->checked_out_at) {
                    abort(400, 'Your most recent check-in record has already been marked as checked out.');
                }
            }

            // Check if they have a class booking with checked-in time set
            if (! $attendanceRecord instanceof AttendanceRecord && $classBooking instanceof ClassBooking && ($classBooking->user instanceof User && $classBooking->status == ClassBookingStatus::BOOKED)) {
                $user = $classBooking->user;
                $healthProvider = $user->health_provider_id;
                $userProvider = HealthCareProvider::query()->find($user->health_provider_id);

                if ($userProvider instanceof HealthCareProvider && $boxFacility->healthProviders()->where('health_provider_id', '=', $healthProvider)->exists()) {
                    $attendanceRecord = new AttendanceRecord();
                    $attendanceRecord->setAttribute('health_provider_id', $healthProvider);
                    $attendanceRecord->setAttribute('user_id', $user->getKey());
                    $attendanceRecord->setAttribute('checked_in_at', $classBooking->checked_in_at);
                    $attendanceRecord->setAttribute('class_booking_id', $classBooking->getKey());
                    $attendanceRecord->setAttribute('class_to_date_id', $classBooking->classDate->getKey());
                    $attendanceRecord->setAttribute('box_facility_id', $boxFacility->getKey());
                    $attendanceRecord->save();
                }
            }

            if (! $attendanceRecord instanceof AttendanceRecord) {
                abort(400, 'No check-in attendance record could be found.');
            }

            // Update checkout time
            $attendanceRecord->update([
                'checked_out_at' => $now,
            ]);
        }

        // Normal check-out for fully authenticated members
        if ($classBooking instanceof ClassBooking) {
            // TODO: Fix
            $authUserTenant = (new TenantUserService())->getCurrentUserTenantForTenant(auth()->user(), $classBooking->class->tenant);

            if ($classBooking->class->box_id !== $authUserTenant?->box_id) {
                abort(400, 'Sorry, your user role does not have access to this resource.');
            }

            $classBooking->update([
                'is_checked_out' => true,
                'checked_out_at' => $now,
            ]);
        }

        $data = [
            'classBooking' => $classBooking instanceof ClassBooking ? new ClassBookingResource($classBooking) : null,
            'attendanceRecord' => $attendanceRecord instanceof AttendanceRecord ? new AttendanceRecordResource($attendanceRecord) : null,
        ];

        return response()->json($data);
    }

    public function cancelCheckIn(CancelCheckInRequest $request, ClassBooking $classBooking): Response
    {
        if ($classBooking->user instanceof User) {
            // Find the attendance record by class booking
            $attendanceRecord = (new AttendanceRecordService())->getLatestAttendanceRecordForMember($classBooking->user, $classBooking);

            if ($attendanceRecord instanceof AttendanceRecord) {
                $attendanceRecord->delete();
            }
        }

        // Update classBooking
        $classBooking->update([
            'is_checked_in' => false,
            'checked_in_at' => null,
            'is_checked_out' => false,
            'checked_out_at' => null,
        ]);

        return response()->noContent();
    }
}

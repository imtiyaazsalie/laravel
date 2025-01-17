<?php

namespace App\Http\Controllers\API;

use App\Enums\ScheduleUserAction as ScheduleUserActionEnum;
use App\Enums\ScheduleUserActionStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\OnHoldUser\CancelOnHoldUserRequest;
use App\Http\Requests\OnHoldUser\CreateOnHoldUserRequest;
use App\Http\Requests\OnHoldUser\ListOnHoldUsersRequest;
use App\Http\Requests\OnHoldUser\ReadOnHoldUserRequest;
use App\Http\Requests\OnHoldUser\ReleaseOnHoldUserRequest;
use App\Http\Requests\OnHoldUser\UpdateOnHoldUserRequest;
use App\Http\Resources\UserOnHoldResource;
use App\Models\ScheduleUserAction;
use App\Models\User;
use App\Models\UserOnHold;
use App\Services\UserOnHoldService;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Knuckles\Scribe\Attributes\QueryParam;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class OnHoldUserController extends Controller
{
    public function __construct(public UserOnHoldService $onHoldUser)
    {
    }

    #[QueryParam('filter[tenant_id]', 'integer', required: false)]
    #[QueryParam('filter[user_id]', 'integer', required: false)]
    public function list(ListOnHoldUsersRequest $request)
    {
        return UserOnHoldResource::collection(
            QueryBuilder::for(UserOnHold::class)
                ->allowedIncludes(
                    'user'
                )
                ->allowedFilters([
                    AllowedFilter::exact('tenant_id', 'box_id'),
                    AllowedFilter::exact('user_id'),
                    AllowedFilter::exact('location_id', 'user.userLocations.box_facility_id'),
                ])
                ->_paginate()
        );
    }

    public function getOnHoldUser(ReadOnHoldUserRequest $request, UserOnHold $userOnHold)
    {
        return new UserOnHoldResource(
            $userOnHold
        );
    }

    public function store(CreateOnHoldUserRequest $request)
    {
        $users = User::query()
            ->whereKey($request->user_ids)
            ->whereHas('tenantUser', function ($query) use ($request) {
                $query->active()->where('box_id', $request->tenant_id);
            }
            )
            ->get();

        $startDate = Carbon::parse($request->start_date);
        $releaseDate = $request->release_date ? Carbon::parse($request->release_date) : null;

        $users->each(function ($user) use ($request, $startDate, $releaseDate) {
            if ($startDate->isFuture()) {
                // create scheduled user action
                ScheduleUserAction::create([
                    'tenant_id' => $request->tenant_id,
                    'user_id' => $user->getAuthIdentifier(),
                    'action' => ScheduleUserActionEnum::PLACE_ON_HOLD,
                    'date' => $startDate,
                    'status' => ScheduleUserActionStatus::PENDING,
                    'on_hold_note' => $request->note,
                    'on_hold_pro_rata_fee' => $request->pro_rata_fee ? number_format((float) $request->pro_rata_fee, 2, '.', '') : null,
                    'on_hold_release_date' => $releaseDate,
                    'extend_package_end_date' => $request->is_extend_package_end_date,
                ]);
            } else {
                // place user on hold
                $this->onHoldUser->placeUserOnHold(
                    tenantId: $request->tenant_id,
                    user: $user,
                    startDate: $startDate,
                    releaseDate: $releaseDate,
                    proRataFee: (float) $request->pro_rata_fee ?: 0.00,
                    note: $request->note,
                    isExtendPackageEndDate: $request->is_extend_package_end_date,
                    actionedBy: auth()->user()
                );
            }
        });

        return response()->noContent(Response::HTTP_CREATED);
    }

    public function update(UserOnHold $userOnHold, UpdateOnHoldUserRequest $request)
    {
        DB::transaction(function () use (&$userOnHold, $request) {
            $startDate = Carbon::parse($request->input('start_date'));
            // Revert package extension if start or release date is change
            if ($userOnHold->extend_package_end_date && ($userOnHold->start_date->toDateString() !== $request->input('start_date') || $userOnHold->release_date?->toDateString() !== $request->input('release_date'))) {
                $this->onHoldUser->revertExtendPackagesEndDate($userOnHold->tenant_id, $userOnHold->user, $userOnHold->start_date, $userOnHold->release_date);
            }

            if ($startDate->isFuture()) {
                // create scheduled user action
                ScheduleUserAction::create([
                    'tenant_id' => $userOnHold->tenant_id,
                    'user_id' => $userOnHold->user->user_id,
                    'action' => ScheduleUserActionEnum::PLACE_ON_HOLD,
                    'date' => $startDate,
                    'status' => ScheduleUserActionStatus::PENDING,
                    'on_hold_note' => $request->input('note') ?? $userOnHold->note,
                    'on_hold_pro_rata_fee' => number_format((float) $request->input('pro_rata_fee'), 2, '.', ''),
                    'on_hold_release_date' => $request->input('release_date'),
                    'extend_package_end_date' => $request->input('is_extend_package_end_date'),
                ]);

                $this->onHoldUser->releaseOnHoldUser($userOnHold, true, false);

            } else {

                $userOnHold->update([
                    'start_date' => $request->input('start_date'),
                    'release_date' => $request->input('release_date'),
                    'pro_rata_fee' => number_format((float) $request->input('pro_rata_fee'), 2, '.', ''),
                    'extend_package_end_date' => $request->input('is_extend_package_end_date'),
                    'note' => $request->input('note') ?? $userOnHold->note,
                ]);

                if ($userOnHold->release_date && $userOnHold->extend_package_end_date) {
                    $this->onHoldUser->extendPackagesEndDate($userOnHold->tenant_id, $userOnHold->user, $userOnHold->start_date, $userOnHold->release_date);
                }
            }
        });

        return new UserOnHoldResource($userOnHold);
    }

    public function releaseMember(ReleaseOnHoldUserRequest $request, UserOnHold $userOnHold)
    {
        DB::transaction(function () use (&$userOnHold, $request) {
            // Update proRataFee in case it has changed or it was removed
            $userOnHold->update([
                'pro_rata_fee' => number_format((float) $request->pro_rata_fee, 2, '.', ''),
            ]);

            // Release on-hold user
            $this->onHoldUser->releaseOnHoldUser($userOnHold, true, $request->is_extend_package_end_date);
        });

        return new UserOnHoldResource($userOnHold);
    }

    public function cancel(CancelOnHoldUserRequest $request, UserOnHold $userOnHold)
    {
        DB::transaction(function () use (&$userOnHold) {
            $userOnHold->userTenant()->update([
                'user_status_id' => UserStatus::ACTIVE,
            ]);

            // Revert package extensions
            $this->onHoldUser->revertExtendPackagesEndDate($userOnHold->tenant_id, $userOnHold->user, $userOnHold->start_date, $userOnHold->release_date);

            $userOnHold->delete();
        });

        return new UserOnHoldResource($userOnHold);
    }
}

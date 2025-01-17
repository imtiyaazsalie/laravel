<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\ScheduledUserAction\CreateScheduledUserActionRequest;
use App\Http\Requests\ScheduledUserAction\DeleteScheduledUserActionRequest;
use App\Http\Requests\ScheduledUserAction\ListScheduledUserActionsRequest;
use App\Http\Resources\ScheduleUserActionResource;
use App\Models\ScheduleUserAction;
use App\Models\TenantUser;
use App\Services\FinanceService;
use App\Services\ScheduledUserActionsService;
use App\Services\TenantUserService;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class ScheduledUserActionController extends Controller
{
    public function list(ListScheduledUserActionsRequest $request)
    {
        return ScheduleUserActionResource::collection(
            QueryBuilder::for(ScheduleUserAction::class)
                ->select('scheduled_user_actions.*')
                ->join('users', 'users.user_id', '=', 'scheduled_user_actions.user_id')
                ->allowedIncludes([
                    'user',
                    'userTenant',
                    'userTenant.user',
                    'userTenant.userContract',
                    AllowedInclude::callback('isOverdue', function ($query) {
                    }, 'user'),
                ])
                ->allowedFilters([
                    AllowedFilter::exact('tenant_id', 'box_id'),
                    AllowedFilter::exact('location_id', 'user.userLocations.box_facility_id'),
                    AllowedFilter::exact('user_id'),
                    AllowedFilter::exact('status'),
                    AllowedFilter::exact('action'),
                    AllowedFilter::scope('search', 'searchUser'),
                ])
                ->allowedSorts([
                    AllowedSort::field('name', 'users.name'),
                    AllowedSort::field('surname', 'users.surname'),
                    AllowedSort::field('email', 'users.email'),
                    AllowedSort::field('action', 'scheduled_user_actions.action'),
                    AllowedSort::field('date', 'scheduled_user_actions.date'),
                ])
                ->_paginate()
                ->tap()
                ->transform(function ($scheduledUserAction) use ($request) {

                    if (str($request->input('include'))->contains('isOverdue')) {
                        $scheduledUserAction->userTenant->is_overdue = $scheduledUserAction->userTenant->isMember()
                            ? (new FinanceService())->getAmountOutstanding($scheduledUserAction->userTenant) > 0
                            : false;
                    }

                    if ($scheduledUserAction->userTenant->relationLoaded('userContract')) {
                        $scheduledUserAction->userTenant->userContract->unsetRelation('userTenant');
                    }

                    return $scheduledUserAction;

                })
        );
    }

    public function store(CreateScheduledUserActionRequest $request): Response
    {
        $action = $request->get('action');
        $userBoxMembershipIds = $request->get('user_ids');
        $actionDate = Carbon::parse($request->get('action_date'));
        $releaseDate = $request->get('release_date') ? Carbon::parse($request->get('release_date')) : null;
        $proRataFee = (float) $request->get('pro_rata_fee');
        $lastDebitDate = $request->get('last_debit_date') ? Carbon::parse($request->get('last_debit_date')) : null;

        $authUser = (new TenantUserService())->getCurrentUserTenantForTenant($request->user(), $request->get('tenant_id'));

        foreach ($userBoxMembershipIds as $userBoxMembershipId) {
            $userBoxMembership = (new TenantUserService())->getCurrentUserTenantForTenant($userBoxMembershipId, $request->get('tenant_id'));

            if (! $userBoxMembership instanceof TenantUser) {
                continue;
            }

            // Check if user belongs to this box
            if ($authUser->box_id !== $userBoxMembership->box_id) {
                continue;
            }

            // Check if an action has been already scheduled for user that is in a pending state
            $existingScheduleUserActions = ScheduleUserAction::query()
                ->where('user_id', $userBoxMembership->user_id)
                ->where('box_id', $userBoxMembership->box_id)
                ->where('action', $action)
                ->where('status', 'pending')
                ->first();

            if ($existingScheduleUserActions) {
                continue;
            }

            (new ScheduledUserActionsService())->createScheduleAction(
                $userBoxMembership,
                $action,
                $actionDate,
                $releaseDate,
                $proRataFee,
                $request->get('note'),
                $request->get('is_extend_package_end_date', false),
                $request->get('is_excluded_from_future_batches', false),
                $lastDebitDate
            );
        }

        return response()->noContent();
    }

    public function delete(DeleteScheduledUserActionRequest $request, ScheduleUserAction $action)
    {
        $action->delete();

        return response()->noContent();
    }
}

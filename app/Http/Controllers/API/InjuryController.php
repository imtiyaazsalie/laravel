<?php

namespace App\Http\Controllers\API;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Injury\CreateInjuryRequest;
use App\Http\Requests\Injury\DeleteInjuryRequest;
use App\Http\Requests\Injury\ListInjuriesRequest;
use App\Http\Requests\Injury\MarkInjuryHealedRequest;
use App\Http\Requests\Injury\ReadInjuryRequest;
use App\Http\Requests\Injury\UpdateInjuryRequest;
use App\Http\Resources\InjuryResource;
use App\Models\Injury;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\CrmService;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\Enums\SortDirection;
use Spatie\QueryBuilder\QueryBuilder;

class InjuryController extends Controller
{
    public function list(ListInjuriesRequest $request): AnonymousResourceCollection
    {
        $injuries = QueryBuilder::for(Injury::class)
            ->allowedFilters([
                AllowedFilter::exact('location_id', 'user.userLocations.box_facility_id'),
                AllowedFilter::exact('user_id', 'created_for_id'),
                AllowedFilter::exact('status'),
            ])
            ->allowedIncludes(
                'user',
                'injuryUpdates',
                'user.userTenant'
            )
            ->when($request->input('filter.tenant_id'), function ($query) use ($request) {
                $query->join('user_to_box', function (JoinClause $join) use ($request) {
                    $join->on('user_to_box.user_id', '=', 'injury_injuries.created_for_id')
                        ->where('user_to_box.box_id', '=', $request->input('filter.tenant_id'));
                })
                    ->where('user_to_box.user_status_id', '=', UserStatus::ACTIVE->value);
            })
            ->has('user')
            ->select('injury_injuries.*')
            ->orderBy('injury_injuries.created_on', SortDirection::DESCENDING)
            ->_paginate();

        return InjuryResource::collection($injuries);
    }

    public function show(ReadInjuryRequest $request, Injury $injury): InjuryResource
    {
        return new InjuryResource($injury->loadMissing('user', 'injuryUpdates'));
    }

    public function store(CreateInjuryRequest $request): InjuryResource
    {
        $crmService = resolve(CrmService::class);

        $user = User::query()->findOrFail($request->input('user_id'));

        $injury = Injury::create([
            ...$request->safe()->except('content'),
            'status' => 'injured',
        ]);

        $injury->injuryUpdates()->create([
            'content' => $request->get('content'),
        ]);

        // if($forSelf) {
        // Get all user tenants for user
        // Get all tenants
        // Get head coaches for those tenants
        // Notify those coaches
        // } else {
        // Notify user of injury that was created for them
        // Notify head coaches of the injury created for the user.
        // }

        $forSelf = auth()->user()->getAuthIdentifier() == $injury->user_id;

        if ($forSelf) {
            /**
             * Injury created for self, mail all your head coaches.
             */
            TenantUser::query()
                ->active()
                ->where('user_id', $injury->user_id)
                ->where('user_type_id', '=', UserType::GYM_MEMBER->value)
                ->each(function ($tenantUser) use ($crmService, $user, $injury) {

                    //get head coach
                    $headCoaches = TenantUser::query()
                        ->headCoaches()
                        ->active()
                        ->where('box_id', $tenantUser->tenant_id)
                        ->get();

                    if ($headCoaches->isEmpty()) {
                        return;
                    }

                    $headCoach = $headCoaches->first();

                    if ($headCoach->user_id == auth()->user()->getAuthIdentifier()) {
                        return;
                    }

                    $crmService->createScheduledEmailForNotification(
                        tenantOrLocation: $tenantUser->tenant,
                        context: 'add_injury_athlete',
                        recipient: $headCoach->user,
                        data: [
                            'coach_name' => $headCoach->user->name,
                            'coach_surname' => $headCoach->user->surname,
                            'member_name' => $user->name,
                            'member_surname' => $user->surname,
                            'injury_update_content' => $injury->injuryUpdates()->first()->content,
                        ]
                    );
                });
        } else {

            /**
             * If the injury is not for yourself you need to have put in a tenant ID, and must be a staff member.
             */

            /**
             * Message athlete and other tenant user head coaches.
             */
            TenantUser::query()
                ->active()
                ->where('user_id', $injury->user_id)
                ->each(function ($tenantUser) use ($crmService, $user, $injury, $request) {

                    if ($tenantUser->tenant_id == $request->tenant_id) {
                        /**
                         * Message the athlete.
                         */
                        $crmService->createScheduledEmailForNotification(
                            tenantOrLocation: $tenantUser->tenant,
                            context: 'add_injury_coach',
                            recipient: $injury->user,
                            data: [
                                'coach_name' => auth()->user()->name,
                                'coach_surname' => auth()->user()->surname,
                                'member_name' => $injury->user->name,
                                'member_surname' => $injury->user->surname,
                                'injury_update_content' => $injury->injuryUpdates()->first()->content,
                            ]
                        );

                        return;
                    }

                    //get head coach
                    $headCoaches = TenantUser::query()
                        ->headCoaches()
                        ->active()
                        ->where('box_id', $tenantUser->tenant_id)
                        ->get();

                    if ($headCoaches->isEmpty()) {
                        return;
                    }

                    $headCoach = $headCoaches->first();

                    $crmService->createScheduledEmailForNotification(
                        tenantOrLocation: $tenantUser->tenant,
                        context: 'add_injury_athlete',
                        recipient: $headCoach->user,
                        data: [
                            'coach_name' => $headCoach->user->name,
                            'coach_surname' => $headCoach->user->surname,
                            'member_name' => $user->name,
                            'member_surname' => $user->surname,
                            'injury_update_content' => $injury->injuryUpdates()->first()->content,
                        ]
                    );
                });
        }

        return new InjuryResource($injury->loadMissing(['user', 'injuryUpdates']));
    }

    public function update(UpdateInjuryRequest $request, Injury $injury): InjuryResource
    {
        $crmService = resolve(CrmService::class);

        $user = User::query()->findOrFail($injury->user_id);

        $injury->injuryUpdates()->create([
            'injury_id' => $injury->getKey(),
            'content' => $request->get('content'),
        ]);

        $injury->touch();

        $forSelf = auth()->user()->getAuthIdentifier() == $injury->user_id;

        if ($forSelf) {

            /**
             * Injury created for self, mail all your head coaches.
             */
            TenantUser::query()
                ->active()
                ->where('user_id', $injury->user_id)
                ->where('user_type_id', '=', UserType::GYM_MEMBER->value)
                ->each(function ($tenantUser) use ($crmService, $user, $injury) {

                    //get head coach
                    $headCoaches = TenantUser::query()
                        ->headCoaches()
                        ->active()
                        ->where('box_id', $tenantUser->tenant_id)
                        ->get();

                    if ($headCoaches->isEmpty()) {
                        return;
                    }

                    $headCoach = $headCoaches->first();

                    if ($headCoach->user_id == auth()->user()->getAuthIdentifier()) {
                        return;
                    }

                    $crmService->createScheduledEmailForNotification(
                        tenantOrLocation: $tenantUser->tenant,
                        context: 'update_injury_athlete',
                        recipient: $headCoach->user,
                        data: [
                            'coach_name' => $headCoach->user->name,
                            'coach_surname' => $headCoach->user->surname,
                            'member_name' => $user->name,
                            'member_surname' => $user->surname,
                            'injury_update_content' => $injury->injuryUpdates()->first()->content,
                        ]
                    );
                });
        } else {

            /**
             * If the injury is not for youself you need to have put in a tenant ID on create, (but we don't save this so must put it in again on update?),
             * and must be a staff member.
             */

            /**
             * Message athelete and other tenant user head coaches.
             */
            TenantUser::query()
                ->active()
                ->where('user_id', $injury->user_id)
                ->each(function ($tenantUser) use ($crmService, $user, $injury) {

                    if ($tenantUser->isMember()) {
                        /**
                         * Message the athlete.
                         */
                        $crmService->createScheduledEmailForNotification(
                            tenantOrLocation: $tenantUser?->tenant,
                            context: 'update_injury_coach',
                            recipient: $injury->user,
                            data: [
                                'coach_name' => auth()->user()->name,
                                'coach_surname' => auth()->user()->surname,
                                'member_name' => $injury->user->name,
                                'member_surname' => $injury->user->surname,
                                'injury_update_content' => $injury->injuryUpdates()->first()->content,
                            ]

                        );

                        return;
                    }

                    //get head coach
                    $headCoaches = TenantUser::query()
                        ->headCoaches()
                        ->active()
                        ->where('box_id', $tenantUser->tenant_id)
                        ->get();

                    if ($headCoaches->isEmpty()) {
                        return;
                    }

                    $headCoach = $headCoaches->first();

                    $crmService->createScheduledEmailForNotification(
                        tenantOrLocation: $tenantUser->tenant,
                        context: 'update_injury_athlete',
                        recipient: $headCoach->user,
                        data: [
                            'coach_name' => $headCoach->user->name,
                            'coach_surname' => $headCoach->user->surname,
                            'member_name' => $user->name,
                            'member_surname' => $user->surname,
                            'injury_update_content' => $injury->injuryUpdates()->first()->content,
                        ]
                    );
                });
        }

        return new InjuryResource($injury->load(['user', 'injuryUpdates']));
    }

    public function markAsHealed(MarkInjuryHealedRequest $request, Injury $injury): InjuryResource
    {
        $crmService = resolve(CrmService::class);

        $user = User::query()->findOrFail($injury->user_id);

        $injury->injuryUpdates()->create([
            'injury_id' => $injury->getKey(),
            'content' => 'Marked as "healed"',
        ]);

        $injury->update(['status' => 'healed']);

        $forSelf = auth()->user()->getAuthIdentifier() == $injury->user_id;

        if ($forSelf) {

            /**
             * Injury created for self, mail all your head coaches.
             */
            TenantUser::query()
                ->active()
                ->where('user_id', $injury->user_id)
                ->where('user_type_id', '=', UserType::GYM_MEMBER->value)
                ->each(function ($tenantUser) use ($crmService, $user, $injury) {

                    //get head coach
                    $headCoaches = TenantUser::query()
                        ->headCoaches()
                        ->active()
                        ->where('box_id', $tenantUser->tenant_id)
                        ->get();

                    if ($headCoaches->isEmpty()) {
                        return;
                    }

                    $headCoach = $headCoaches->first();

                    if ($headCoach->user_id == auth()->user()->getAuthIdentifier()) {
                        return;
                    }

                    $crmService->createScheduledEmailForNotification(
                        tenantOrLocation: $tenantUser->tenant,
                        context: 'heal_injury_athlete',
                        recipient: $headCoach->user,
                        data: [
                            'coach_name' => $headCoach->user->name,
                            'coach_surname' => $headCoach->user->surname,
                            'member_name' => $user->name,
                            'member_surname' => $user->surname,
                            'injury_update_content' => $injury->injuryUpdates()->first()->content,
                        ]
                    );
                });
        } else {

            /**
             * If the injury is not for youself you need to have put in a tenant ID on create, (but we don't save this so must put it in again on update?),
             * and must be a staff member.
             */

            /**
             * Message athelete and other tenant user head coaches.
             */
            TenantUser::query()
                ->active()
                ->where('user_id', $injury->user_id)
                ->each(function ($tenantUser) use ($crmService, $user, $injury) {

                    if ($tenantUser->isMember()) {
                        /**
                         * Message the athlete.
                         */
                        $crmService->createScheduledEmailForNotification(
                            tenantOrLocation: $tenantUser?->tenant,
                            context: 'heal_injury_coach',
                            recipient: $injury->user,
                            data: [
                                'coach_name' => auth()->user()->name,
                                'coach_surname' => auth()->user()->surname,
                                'member_name' => $injury->user->name,
                                'member_surname' => $injury->user->surname,
                                'injury_update_content' => $injury->injuryUpdates()->first()->content,
                            ]
                        );

                        return;
                    }

                    //get head coach
                    $headCoaches = TenantUser::query()
                        ->headCoaches()
                        ->active()
                        ->where('box_id', $tenantUser->tenant_id)
                        ->get();

                    if ($headCoaches->isEmpty()) {
                        return;
                    }

                    $headCoach = $headCoaches->first();

                    $crmService->createScheduledEmailForNotification(
                        tenantOrLocation: $tenantUser->tenant,
                        context: 'heal_injury_athlete',
                        recipient: $headCoach->user,
                        data: [
                            'coach_name' => $headCoach->user->name,
                            'coach_surname' => $headCoach->user->surname,
                            'member_name' => $user->name,
                            'member_surname' => $user->surname,
                            'injury_update_content' => $injury->injuryUpdates()->first()->content,
                        ]
                    );
                });
        }

        return new InjuryResource($injury->loadMissing(['user', 'injuryUpdates']));
    }

    public function delete(DeleteInjuryRequest $request, Injury $injury): Response
    {
        $injury->update(['status' => 'deleted']);

        $injury->injuryUpdates->each(function ($item) {
            $item->delete();
        });

        return response()->noContent(200);
    }
}

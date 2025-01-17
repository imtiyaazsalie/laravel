<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Enums\WaiverStatus;
use App\Models\Injury;
use App\Models\LeadWaivers;
use App\Models\Location;
use App\Models\Region;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidDateException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class UserService
{
    public function getUsersQueryBuilder(): QueryBuilder
    {
        return QueryBuilder::for(User::class)
            ->select('users.*')
            ->when(
                request()->input('filter.tenant_id') && str(request()->input('append'))->contains('user_tenant_has_sessions_available_for_date:'),
                function ($query) {

                    $tenant = Tenant::findOrFail(request()->input('filter.tenant_id'));

                    try {
                        $date = Carbon::parse(
                            str(request()->input('append'))->afterLast('user_tenant_has_sessions_available_for_date:')->take(10)
                        );
                    } catch (InvalidDateException) {
                        abort(400, 'The user_tenant_has_sessions_available_for_date appends must be in the format: user_tenant_has_sessions_available_for_date:YYYY-MM-DD');
                    }

                    $packageIds = explode(',', request()->input('filter.user_tenant_package_id'));

                    $location = request()->input('filter.user_tenant_location_id')
                        ? Location::findOrFail(request()->input('filter.user_tenant_location_id'))
                        : null;

                    $query->withHasSessionRemainingForDate($tenant, $date, $location, $packageIds);

                }
            )
            ->withoutGlobalScopes()
            ->with(['userTenant.user', 'userTenant.tenant'])
            ->allowedIncludes([
                'healthProvider',
                'userTenant.userContract',
                'userTenant.leadMember',
                'userTenant.leadMember.referredBy',
                'userTenant.leadMember.location',
                'userTenant.leadMember.classBookings',
                'userTenant.leadMember.waiver',
                AllowedInclude::callback('tenantUser.locations', function ($query) {
                    if ($tenantId = request()->input('filter.tenant_id')) {
                        $query->where('box_facility.box_id', $tenantId);
                    }
                }),
                AllowedInclude::callback('tenantUser.tenant', function ($query) {
                    if ($tenantId = request()->input('filter.tenant_id')) {
                        $query->where('boxes.box_id', $tenantId);
                    }
                }),
                AllowedInclude::callback('userTenant.locations', function ($query) {
                    if ($tenantId = request()->input('filter.tenant_id')) {
                        $query->where('box_facility.box_id', $tenantId);
                    }
                }),
                AllowedInclude::callback('userTenant.userPackages', function ($query) {
                }, 'healthProvider'), // because QueryBuilder can't ignore missing includes, we will include a cheap relationship instead.
                AllowedInclude::callback('isOverdue', function ($query) {
                }, 'userTenant'),

            ])
            ->allowedFilters([
                AllowedFilter::exact('user_id'),
                AllowedFilter::scope('search'),
                AllowedFilter::scope('birthdays_between', 'birthdaysBetween'),
                AllowedFilter::callback('tenant_id', function (Builder $query, $value) {
                    $query->whereHas('userTenant', function ($query) use ($value) {
                        $value = is_array($value) ? $value : [$value];

                        $query->whereIn('user_to_box.box_id', $value);

                        $query->when(
                            request()->input('filter.user_tenant_location_id'),
                            function ($query) use ($value) {
                                $query->where(function ($query) use ($value) {
                                    $query->whereHas('locations', function ($query) use ($value) {
                                        $query->whereIn('user_to_facility.box_facility_id', explode(',', request()->input('filter.user_tenant_location_id')));
                                        $query->whereIn('user_to_box.box_id', $value);
                                    })->orWhereHas('locationAccessPrivileges', function ($query) use ($value) {
                                        $query->join('box_facility', 'box_facility.box_facility_id', '=', 'user_facility_access_privileges.box_facility_id');
                                        $query->whereIn('user_facility_access_privileges.box_facility_id', explode(',', request()->input('filter.user_tenant_location_id')));
                                        $query->whereIn('user_to_box.user_type_id', UserType::staffUserTypeIds());
                                        $query->whereIn('box_facility.box_id', $value);
                                    })->orWhereIn('user_to_box.user_type_id', [
                                        UserType::HEAD_COACH,
                                        UserType::GYM_COACH,
                                        UserType::BOX_ADMIN,
                                    ]);
                                });
                            },
                            function ($query) use ($value) {
                                $query->where(function ($query) use ($value) {
                                    $query->whereHas('locations', function ($query) use ($value) {
                                        $query->whereIn('user_to_box.box_id', $value)->where('box_facility.is_active', 1);

                                    })->orWhereHas('locationAccessPrivileges', function ($query) use ($value) {
                                        $query->join('box_facility', 'box_facility.box_facility_id', '=', 'user_facility_access_privileges.box_facility_id');
                                        $query->whereIn('box_facility.box_id', $value)
                                            ->where('box_facility.is_active', 1);

                                    })->orWhereIn('user_to_box.user_type_id', [
                                        UserType::HEAD_COACH,
                                        UserType::GYM_COACH,
                                        UserType::BOX_ADMIN,
                                    ]);
                                });
                            },
                        );

                        $query->when(request()->input('filter.user_tenant_type_id'), function ($query) {
                            $query->whereIn('user_to_box.user_type_id', explode(',', request()->input('filter.user_tenant_type_id')));
                        });

                        $query->when(request()->input('filter.user_tenant_status_id'), function ($query) {
                            if (request()->input('filter.user_tenant_status_id') == UserStatus::ACTIVE->value) {
                                $query->whereDate('user_to_box.end_date', '>=', now());
                            }

                            $query->whereIn('user_to_box.user_status_id', explode(',', request()->input('filter.user_tenant_status_id')));
                        });

                        $query->when(request()->input('filter.user_tenant_debit_status_id'), function ($query) {
                            $query->whereIn('user_to_box.user_debit_status_id', explode(',', request()->input('filter.user_tenant_debit_status_id')));
                        });

                        //TODO: SQL injection prevention in case of validation failure.
                        $query->when(request()->input('filter.user_tenant_package_visibility_for_class_id'), function ($query) {
                            $query->join('user_to_package as utp_pvcid', function ($join) {
                                $join->on('users.user_id', '=', 'utp_pvcid.user_id')
                                    ->where(
                                        'utp_pvcid.user_to_package_id',
                                        '=',
                                        DB::raw('(SELECT utp_pvcid.user_to_package_id FROM user_to_package
                                            INNER JOIN class_to_packages ON class_to_packages.package_id = utp_pvcid.package_id
                                                AND class_to_packages.is_active = 1
                                                AND class_to_packages.class_id = '.request()->input('filter.user_tenant_package_visibility_for_class_id').'
                                            INNER JOIN packages ON packages.package_id = class_to_packages.package_id
                                                AND packages.is_active = 1
                                            WHERE utp_pvcid.user_id = users.user_id
                                                AND utp_pvcid.deleted = 0
                                                AND utp_pvcid.effective_date <= CURDATE()
                                                AND (utp_pvcid.end_date >= CURDATE() OR utp_pvcid.end_date IS NULL)
                                            LIMIT 1)'
                                        )
                                    );
                            });
                        });

                        $query->when(request()->input('filter.user_tenant_programme_id'), function ($query) {
                            $query->whereIn('user_to_box.programme_id', explode(',', request()->input('filter.user_tenant_programme_id')));
                        });

                        // $query->when(request()->input('filter.user_tenant_has_sessions_available_for_date'), function ($query) {
                        //     $query->hasSessionRemainingForDate(request()->input('filter.tenant_id'), request()->input('filter.user_tenant_has_sessions_available_for_date'));
                        // });

                        $query->when(request()->input('filter.user_tenant_package_id'), function ($query) {
                            $query->join('user_to_package', 'user_to_package.user_id', '=', 'users.user_id')
                                ->whereIn('user_to_package.package_id', explode(',', request()->input('filter.user_tenant_package_id')))
                                ->where(function ($query) {
                                    $query->where(function ($query) {
                                        $query->whereNotNull('user_to_package.end_date')
                                            ->where('user_to_package.effective_date', '<=', now())
                                            ->where('user_to_package.end_date', '>', now());
                                    })
                                        ->orWhere(function ($query) {
                                            $query->where('user_to_package.effective_date', '<=', now())
                                                ->whereNull('user_to_package.end_date');
                                        });
                                })
                                ->where('user_to_package.deleted', false);
                        });

                        $query->when(request()->input('filter.user_tenant_assigned_user_id'), function ($query) {
                            $query->whereIn('user_to_box.assigned_coach_utb_id', explode(',', request()->input('filter.user_tenant_assigned_user_id')));
                        });

                        $query->when(request()->input('filter.user_tenant_type_id') == UserType::LEAD_MEMBER->value
                                && (request()->input('filter.lead_member_status') || request()->input('filter.lead_member_type')),
                            function ($query) {

                                $query->join('lead_members', 'user_to_box.lead_member_id', '=', 'lead_members.member_id')
                                    ->when(request()->input('filter.lead_member_status'), function ($query) {
                                        $query->whereIn('lead_members.status', explode(',', request()->input('filter.lead_member_status')));
                                    })
                                    ->when(request()->input('filter.lead_member_type'), function ($query) {
                                        $query->whereIn('lead_members.type', explode(',', request()->input('filter.lead_member_type')));
                                    });
                            });

                    });
                }),
                AllowedFilter::callback('is_minimal_data', function (Builder $query, $isMinimalData) {
                    $query->when($isMinimalData, function ($query) {
                        $query->select('users.user_id', 'users.name', 'users.surname', 'users.email', 'users.profilepic');
                    });
                }),
            ])
            ->when(! request()->input('filter.tenant_id'), function ($query) {
                $query->with('tenantUser');
            })
            ->when(request()->input('filter.tenant_id'), function ($query) {
                $query->with([
                    'userTenant' => function ($query) {
                        $query->whereIn('box_id', explode(',', request()->input('filter.tenant_id')));
                        $query->when(request()->input('filter.user_tenant_type_id'), function ($query) {
                            $query->whereIn('user_to_box.user_type_id', explode(',', request()->input('filter.user_tenant_type_id')));
                        });
                    },
                ]);

            })
            ->allowedSorts([
                AllowedSort::field('name', 'users.name'),
                AllowedSort::field('email', 'users.email'),
                AllowedSort::field('surname', 'users.surname'),
                AllowedSort::field('date_of_birth', 'dob'),
                AllowedSort::field('created_at', 'created_on'),
                AllowedSort::callback('contract_ending_at', function (Builder $query, $descending) {
                    $query->join('user_contracts', function ($join) {
                        $join->on('user_contracts.user_id', '=', 'users.user_id')
                            ->where('user_contracts.box_id', '=', request()->input('filter.tenant_id'));
                    })->orderBy(DB::raw('MAX(user_contracts.ending_on)'), $descending ? 'DESC' : 'ASC')
                        ->groupBy('users.user_id');
                }),
                AllowedSort::callback('birthday', function (Builder $query, $descending) {
                    $query->orderByRaw("DATE_FORMAT(users.dob, '%m-%d')".($descending ? ' DESC' : ' ASC'));
                }),
            ])
            ->defaultSort('name');
    }

    public function store($data)
    {
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        return User::create($data);
    }

    public function acceptTermsConditions(User $user): void
    {
        $user->update([
            'terms_and_conditions_accepted' => true,
            'terms_and_conditions_accepted_on' => now(),
        ]);
    }

    public function update(User $user, $data): User
    {
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return $user;
    }

    public function changePassword(User $user, $password): void
    {
        User::where('email', '=', $user->email)
            ->update(['password' => Hash::make($password)]);
    }

    public function delete(User $user): void
    {
        $user->delete();
    }

    public function validate($email): bool
    {
        return User::query()->withoutGlobalScopes()->where('email', '=', $email)->exists();
    }

    public function list()
    {
        return QueryBuilder::for(
            User::query()->where('roles', 'LIKE', '%ROLE_SUPER_ADMIN%')
        )->allowedFilters([
            AllowedFilter::scope('search'),
            AllowedFilter::exact('region_id'),
        ])->paginate();
    }

    public function createUserDigitalLeadWaiver(User $user, Location $boxFacility, string $status = WaiverStatus::ORIGINAL->value, $ipAddress = null): ?object
    {
        // Create waiver for user
        $leadSettings = $boxFacility->tenant->leadSettings;

        if (! $leadSettings) {
            return null;
        }

        $boxWaiver = $leadSettings->waiver;

        if (! $boxWaiver->digital) {
            return null;
        }

        $waiver = LeadWaivers::query()
            ->create([
                'box_facility_id' => $boxFacility->getKey(),
                'digital' => $boxWaiver->digital,
                'digital_terms_and_conditions' => $boxWaiver->digital,
                'parent_id' => $boxWaiver->getKey(),
                'user_id' => $user->getKey(),
                'status' => $status,
            ]);

        if ($status === WaiverStatus::SIGNED->value) {
            $waiver->update([
                'signed_on' => new \DateTime(),
                'ip_address' => $ipAddress,
            ]);
        }

        return $waiver;
    }

    public function getUsersCount(array $userStatuses, ?Tenant $box, ?Location $boxFacility, ?array $userTypes = null, ?Region $region = null, ?bool $isMembers = false): int
    {
        $parameters = [
            'date' => now()->toDateString(),
            'userStatuses' => $userStatuses,
        ];

        $qb = User::query()
            ->withoutGlobalScopes()
            ->select('u.user_id')
            ->distinct()
            ->from('users', 'u')
            ->leftJoin('user_to_box as bm', 'bm.user_id', '=', 'u.user_id')
            ->leftJoin('boxes as b', 'bm.box_id', '=', 'b.box_id')
            ->where(function ($q) use ($parameters) {
                $q->where('bm.effective_date', '<=', $parameters['date']);
                $q->where('bm.end_date', '>=', $parameters['date']);
            })
            ->whereIn('bm.user_status_id', $userStatuses)
            ->where('bm.deleted', false)
            ->where('b.box_status_id', 1);

        if ($isMembers || $boxFacility instanceof Location) {
            $qb->leftJoin('user_to_facility as fm', 'fm.user_id', '=', 'u.user_id')
                ->leftJoin('box_facility as bf', 'fm.box_facility_id', '=', 'bf.box_facility_id')
                ->where(function ($q) use ($parameters) {
                    $q->where('fm.effective_date', '<=', $parameters['date']);
                    $q->where('fm.end_date', '>=', $parameters['date']);
                })->where('bf.is_active', '=', 1);
        }

        if ($box instanceof Tenant) {
            $qb->where('bm.box_id', $box->getKey());
        }

        if ($boxFacility instanceof Location) {
            $qb->where('fm.box_facility_id ', $boxFacility->getKey());
        }

        if ($userTypes) {
            $qb->whereIn('bm.user_type_id', $userTypes);
        }

        if ($region instanceof Region) {
            $qb->where('b.region_id', '=', $region->getKey());
        }

        return $qb->count('u.user_id');
    }

    public function getLatestInjuryForUser(User $athlete): ?object
    {
        return Injury::query()
            ->where('created_for_id', $athlete->getKey())
            ->where('status', 'injured')
            ->where('status', '!=', 'deleted')
            ->orderByDesc('created_on')
            ->first();
    }
}

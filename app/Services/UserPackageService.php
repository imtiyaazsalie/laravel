<?php

namespace App\Services;

use App\Enums\PackageType;
use App\Models\ClassDate;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserInvoice;
use App\Models\UserInvoiceItem;
use App\Models\UserPackage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\JoinClause;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class UserPackageService
{
    public function getUserPackagesQueryBuilder($request = null)
    {
        $locationId = $request->input('filter.location_id');

        return QueryBuilder::for(UserPackage::class)
            ->select('user_to_package.*')
            ->join('users', 'users.user_id', '=', 'user_to_package.user_id')
            ->join('packages', 'packages.package_id', '=', 'user_to_package.package_id')
            ->when(isset($locationId), function ($query) use ($locationId) {
                $query->leftJoin('package_to_box_facility', function ($join) use ($locationId) {
                    $join->on('packages.package_id', '=', 'package_to_box_facility.package_id')
                        ->where('package_to_box_facility.box_facility_id', '=', $locationId);
                });
                $query->where(function ($query) use ($locationId) {
                    $query->whereDoesntHave('package.locations')
                        ->orWhereHas('package.locations', function ($query) use ($locationId) {
                            $query->where('package_to_box_facility.box_facility_id', $locationId);
                        });
                });
            })
            ->allowedFilters([
                AllowedFilter::exact('tenant_id', 'packages.box_id'),
                AllowedFilter::callback('user_tenant_status_id', function (Builder $query, $value) {
                    $query->join('user_to_box', function (JoinClause $join) {
                        $join->on('user_to_box.user_id', '=', 'user_to_package.user_id')
                            ->on('user_to_box.box_id', '=', 'packages.box_id');
                    })->where('user_to_box.user_status_id', '=', $value);
                }),
                AllowedFilter::exact('user_id', 'user_to_package.user_id', false),
                AllowedFilter::exact('package_id', 'packages.package_id'),
                AllowedFilter::exact('package_type', 'packages.package_limit_type_id'),
                AllowedFilter::scope('starts_between', 'startsBetween'),
                AllowedFilter::scope('ends_between', 'endsBetween'),
                AllowedFilter::scope('is_active', 'isActive'),
                AllowedFilter::scope('is_package_active', 'isPackageActive'),
                AllowedFilter::scope('is_sessions_available', 'isSessionsAvailable'),
            ])
            ->allowedSorts([
                AllowedSort::field('user_name', 'users.name'),
                AllowedSort::field('user_surname', 'users.surname'),
                AllowedSort::field('start_date', 'user_to_package.effective_date'),
                AllowedSort::field('end_date', 'user_to_package.end_date'),
            ])
            ->allowedIncludes(
                'user',
                'invoices'
            )
            ->when(! auth()->user()->tokenCan('discovery-vitality'), function (Builder $query) {
                $query->where('packages.package_limit_type_id', '!=', 4);
            })
            ->where('user_to_package.deleted', '=', false)
            ->where('packages.is_active', '=', true)
            ->where('users.deleted', '=', false)
            ->with(['package', 'locations.location']);
    }

    public function getActiveUserPackagesForTenant(User $user, Tenant $tenant, $date = null, ?bool $excludeLimitedPackagesWithNoSessions = false): Collection|array
    {
        $tenantPackages = Package::query()
            ->where('box_id', '=', $tenant->getKey())
            ->where('is_active', '=', true)
            ->select('package_id')
            ->get();

        return UserPackage::query()
            ->select('user_to_package.*')
            ->leftJoin('packages', 'packages.package_id', '=', 'user_to_package.package_id')
            ->where('user_to_package.user_id', '=', $user->getKey())
            ->where('user_to_package.deleted', '=', false)
            ->whereIn('user_to_package.package_id', $tenantPackages->pluck('package_id'))
            ->whereDate('effective_date', '<=', is_null($date) ? now() : $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>', is_null($date) ? now() : $date);
            })
            ->when($excludeLimitedPackagesWithNoSessions, function (Builder $query) {
                $query->whereNested(function ($query) {
                    $query->where('packages.package_limit', '>=', 1)
                        ->where('user_to_package.sessions_available', '>', 0)
                        ->orWhereNull('user_to_package.sessions_available')
                        ->orWhere('packages.package_limit', '=', 0);
                });
            })
            ->where('packages.package_limit_type_id', '!=', PackageType::DROP_IN->value)
            ->orderBy('packages.package_limit')
            ->orderByRaw('FIELD(packages.package_limit_type_id,2,1,3) asc')
            ->orderBy('end_date')
            ->orderBy('sessions_available')
            ->get();
    }

    public function getActiveUserPackageForTenant(User $user, Tenant $tenant, $date = null): Model|Builder|null
    {
        return UserPackage::query()
            ->where('user_id', '=', $user->getKey())
            ->whereHas('package', function ($q) use ($tenant) {
                $q->where('box_id', '=', $tenant->getKey());
                $q->where('is_active', '=', true);
            })
            ->where('deleted', '=', false)
            ->whereDate('effective_date', '<=', is_null($date) ? now() : $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>', is_null($date) ? now() : $date);
            })
            ->first();
    }

    public function getActiveUserPackagesExcludingLimitedPackagesForTenant(User $user, Tenant $tenant, $date = null): Collection
    {
        return $this->getActiveUserPackagesForTenant($user, $tenant, $date)->filter(function (UserPackage $userPackage) {
            return $userPackage->package->type !== PackageType::LIMITED;
        });
    }

    public function getActiveUserPackagesTotalForTenant(User $user, Tenant $tenant, $date = null)
    {
        return $this->getActiveUserPackagesForTenant($user, $tenant, $date)->sum('package.price');
    }

    public function processDebitOrderCoachTopup(UserPackage $userPackage, int $sessionAmount): UserInvoice|false
    {
        $invoice = (new InvoiceService())->getUpcomingDebitOrderInvoice($userPackage->user);

        if (! $invoice instanceof UserInvoice) {
            return false;
        }

        $sessionsTopUpAmount = $userPackage->package->package_topup_price * $sessionAmount;

        $invoiceLineItem = new UserInvoiceItem();

        $invoiceLineItem->setAttribute('invoice_id', $invoice->getKey());
        $invoiceLineItem->setAttribute('discriminator', 'membership');
        $invoiceLineItem->setAttribute('description', 'Membership top-up fee: '.$userPackage->package->package_name);
        $invoiceLineItem->setAttribute('quantity', $sessionAmount);
        $invoiceLineItem->setAttribute('unitPrice', $userPackage->package->package_topup_price);
        $invoiceLineItem->setAttribute('amount', $sessionsTopUpAmount);
        $invoiceLineItem->save();

        $invoice->recalculateTotal();
        $invoice->userBatch->update(['amount' => $invoice->amount]);

        return $invoice;
    }

    public function storeLeadUserPackage(User $user, Package $package)
    {
        return UserPackage::create([
            'user_id' => $user->getKey(),
            'effective_date' => today()->format('Y-m-d'),
            'package_id' => $package->getKey(),
            'sessions_available' => $package->limit,
        ]);
    }

    public function packagesAvailableForClass(User $athlete, ClassDate $classDate): array
    {
        $userPackages = [];

        foreach ((new UserPackageService())->getActiveUserPackagesForTenant($athlete, $classDate->class->tenant, $classDate->date) as $userPackage) {
            // is package allowed for this class?
            if ((new ClassService())->isPackageAllowedForClass($userPackage->package, $classDate->class) === false) {
                continue;
            }

            // check if the class date is > the user's package end date
            if ($userPackage->end_date && $classDate->class_date > $userPackage->end_date) {
                continue;
            }

            if (! $classDate->class->is_free) {
                $boxFacility = $classDate->class->location;

                // user has sessions left for package period?
                $sessionsRemaining = (new ClassService())->getSessionsRemainingForUserPackageForDate($userPackage, $boxFacility, $classDate->class_date);

                if (($userPackage->package->limit === 0 && $sessionsRemaining <= 0) || $sessionsRemaining <= 0) {

                    // Check if user has not reached their daily limit
                    if ($boxFacility->tenant->limit_inter_facility_bookings) {
                        $bookedForDate = (new ClassBookingsService())->getSessionsUsedForAthleteForPeriod($userPackage, $classDate->class_date, clone $classDate->class_date, $boxFacility->tenant, $boxFacility);
                    } else {
                        $bookedForDate = (new ClassBookingsService())->getSessionsUsedForAthleteForPeriod($userPackage, $classDate->class_date, clone $classDate->class_date, $boxFacility->tenant);
                    }

                    $remainingBookingsForDay = $boxFacility->max_bookings_per_athlete_per_day - $bookedForDate;

                    if ($remainingBookingsForDay <= 0 || $userPackage->package->limit === 0) {
                        continue;
                    }

                    continue;
                }
            }

            $userPackages[] = $userPackage;
        }

        return $userPackages;
    }
}

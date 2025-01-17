<?php

namespace App\Exports;

use App\Models\Location;
use App\Models\Tenant;
use App\Services\ClassBookingsService;
use App\Services\ClassService;
use App\Services\TenantUserService;
use App\Services\UserPackageService;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UserPackagesExport implements FromCollection, WithHeadings, WithMapping
{
    private TenantUserService $tenantUserService;

    private ClassService $classService;

    private ClassBookingsService $classBookingsService;

    private Tenant $tenant;

    private ?Location $location = null;

    public function __construct()
    {
        $this->tenantUserService = (new TenantUserService());
        $this->classService = (new ClassService());
        $this->classBookingsService = (new ClassBookingsService());
        $this->tenant = Tenant::find(request()->input('filter.tenant_id'));

        if (request()->has('filter.location_id')) {
            $this->location = Location::find(request()->input('filter.location_id'));
        }
    }

    public function headings(): array
    {
        return [
            'Name',
            'Surname',
            'Email',
            'Package name',
            'Package start date',
            'Package end date',
            'Sessions Available',
            'Upcoming bookings',
        ];
    }

    public function map($userPackage): array
    {
        if (! $this->location) {
            $this->location = $this->tenantUserService->getLocationUserByTenant($userPackage->user, $this->tenant)?->location;
        }

        return [
            $userPackage->user->name,
            $userPackage->user->surname,
            $userPackage->user->email,
            $userPackage->package->name,
            $userPackage->start_date?->toDateString(),
            $userPackage->end_date?->toDateString(),
            $this->classService->getSessionsRemainingForUserPackage($userPackage, $this->location),
            $this->classBookingsService->getSessionsUsedForAthleteForPeriod($userPackage, now(), null, $this->tenant, $this->tenant->limit_inter_facility_bookings ? $this->location : null, true, true),
        ];
    }

    public function collection(): Collection
    {
        return (new UserPackageService())->getUserPackagesQueryBuilder(request())->get();
    }
}

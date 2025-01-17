<?php

namespace App\Services;

use App\Enums\PackageType;
use App\Models\ClassPackage;
use App\Models\DropInPackage;
use App\Models\DropInPackageClasses;
use App\Models\Location;
use App\Models\LocationPackage;
use App\Models\Package;
use App\Models\ProgrammePackageVisibility;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserPackage;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PackageService
{
    private ClassPackageService $classPackage;

    public function __construct()
    {
        $this->classPackage = (new ClassPackageService());
    }

    public function update(Package $package, $data)
    {
        return $package->update($data);
    }

    public function activateOrDeactivate(Package $package)
    {
        $package->toggleStatus();

        return $package;
    }

    public function show(Package $package)
    {
        return $package;
    }

    public function getPackageClasses(Package $package)
    {
        return QueryBuilder::for($package)
            ->with('classes')
            ->allowedFilters([
                AllowedFilter::exact('location_id', 'classes.box_facility_id'),
            ])
            ->whereRelation('classes', 'classes.is_active', '=', true)
            ->whereRelation('classes', 'class_to_packages.is_active', '=', true)
            ->first();
    }

    public function updatePackageClasses($package, $locationId, $classIds)
    {
        ClassPackage::query()
            ->joinRelationship('class')
            ->where('class_to_packages.is_active', '=', true)
            ->where('classes.is_active', '=', true)
            ->where('class_to_packages.package_id', '=', $package->getKey())
            ->where('classes.box_facility_id', '=', $locationId)
            ->delete();

        foreach ($classIds as $classId) {
            $this->classPackage->store([
                'class_id' => $classId,
                'package_id' => $package->getKey(),
            ]);
        }

        return $package;
    }

    public function store($data)
    {
        return Package::create($data);
    }

    public function getProgrammes(Package $package): Package
    {
        return $package->loadMissing('programmes');
    }

    public function updateProgrammes(Package $package, $programmeIds): Package
    {
        $programmes = $this->getProgrammes($package);

        $programmes->map(function ($item) use ($package, $programmeIds) {
            if (! in_array($item->programme_id, $programmeIds)) {
                ProgrammePackageVisibility::where('programme_id', '=', $item->programme_id)
                    ->where('package_id', '=', $package->getKey())
                    ->delete();
            }
        });

        collect($programmeIds)->map(function ($programmeId) use ($package) {
            ProgrammePackageVisibility::updateOrCreate([
                'package_id' => $package->getKey(),
                'programme_id' => $programmeId,
            ]
            );
        });

        return $package;
    }

    public function getActiveLocations(Package $package)
    {
        return $package->locations()->where('is_active', '=', true)->get();
    }

    public function updateActiveLocations(Package $package, $locationIds)
    {
        $locations = $this->getActiveLocations($package);

        $locations->map(function ($item) use ($package, $locationIds) {
            if (! in_array($item->box_facility_id, $locationIds)) {
                LocationPackage::where('box_facility_id', '=', $item->box_facility_id)
                    ->where('package_id', '=', $package->getKey())
                    ->delete();
            }
        });

        collect($locationIds)->map(function ($locationId) use ($package) {
            LocationPackage::updateOrCreate([
                'package_id' => $package->getKey(),
                'box_facility_id' => $locationId,
            ]
            );
        });

        return $package;
    }

    public function getClassLookupForPackageAndFacility(Package $package, Location $boxFacility): Collection|array
    {
        return ClassPackage::query()
            ->distinct()
            ->select('classes.class_id AS id', DB::raw("CONCAT(classes.class_name, ' (', TIME_FORMAT(classes.start_time, '%H:%i'), '-', TIME_FORMAT(classes.end_time, '%H:%i'), ')') AS name"))
            ->join('classes', 'class_to_packages.class_id', '=', 'classes.class_id')
            ->join('packages', 'class_to_packages.package_id', '=', 'packages.package_id')
            ->where('classes.is_active', '=', true)
            ->where('packages.package_id', '=', $package->getKey())
            ->where('classes.box_facility_id', '=', $boxFacility->getKey())
            ->where('class_to_packages.is_active', '=', 1)
            ->orderBy('classes.class_name')
            ->get();
    }

    public function getClassLookupForDropInPackageAndFacility(DropInPackage $dropInPackage, Location $boxFacility): Collection|array
    {
        return DropInPackageClasses::query()
            ->distinct()
            ->select('classes.class_id AS id', DB::raw("CONCAT(classes.class_name, ' (', TIME_FORMAT(classes.start_time, '%H:%i'), '-', TIME_FORMAT(classes.end_time, '%H:%i'), ')') AS name"))
            ->join('classes', 'drop_in_packages_to_classes.class_id', '=', 'classes.class_id')
            ->join('drop_in_packages', 'drop_in_packages_to_classes.drop_in_package_id', '=', 'drop_in_packages.id')
            ->where('classes.is_active', '=', true)
            ->where('drop_in_packages.id', '=', $dropInPackage->getKey())
            ->where('classes.box_facility_id', '=', $boxFacility->getKey())
            ->where('class_to_packages.is_active', '=', 1)
            ->orderBy('classes.class_name')
            ->get();
    }

    public function getProgrammesForPackage(Package $package, Tenant $box)
    {
        $programmes = (new ProgrammeService())->getProgrammesForTenant($box);

        $package->loadMissing('programmeVisibility');

        $programmes->transform(function ($programme) use ($package) {
            $programme['is_checked'] = $package->programmeVisibility->isEmpty() || $package->programmeVisibility->pluck('programme_id')->contains($programme->getKey());

            return $programme;
        });

        return $programmes;
    }

    public function getLocationsForPackage(Package $package)
    {
        $locations = $package->tenant->locations()->_paginate();

        $locations->transform(function ($location) use ($package) {
            $location['is_checked'] = $package->locations?->isEmpty() || $package->locations?->contains($location);

            return $location;
        });

        return $locations;
    }

    /**
     * @throws Exception
     */
    public function createUserPackage(User $user, Package $package, ?\DateTime $startingDate = null, ?\DateTime $endingDate = null, ?int $sessionsAvailable = null): Builder|Model|UserPackage
    {
        if ($startingDate === null) {
            $startingDate = new \DateTime();
        }

        if (! $endingDate) {
            $datetime = new \DateTime();

            if (! $package->default_period_interval || in_array($package->default_period_interval, [
                'P0W',
                'P0M',
                'P0Y',
                'P0D',
                'P',
            ])) {
                $endingDate = null;
            } else {
                $endingDate = $datetime->add(new \DateInterval($package->default_period_interval))->format('Y-m-d');
            }
        }

        if ($package->package_limit_type_id === PackageType::LIMITED) {
            $sessionsAvailable = $sessionsAvailable ?? $package->limit;
        }

        return UserPackage::query()->create([
            'user_id' => $user->getKey(),
            'package_id' => $package->getKey(),
            'effective_date' => $startingDate,
            'end_date' => $endingDate,
            'sessions_available' => $sessionsAvailable,
        ]);
    }

    public function getDefaultPeriod($defaultPeriodInterval): ?string
    {
        try {
            $defaultPeriodInterval = new \DateInterval($defaultPeriodInterval);
            $defaultPeriodInterval->format('Y-m-d');

            $format = [];

            if ($defaultPeriodInterval->y !== 0) {
                $format[] = '%y';
            }

            if ($defaultPeriodInterval->m !== 0) {
                $format[] = '%m';
            }

            if ($defaultPeriodInterval->d !== 0) {
                $weeks = floor($defaultPeriodInterval->d / 7);
                $format[] = $weeks > 0 ? $weeks : '%d';
            }

            // We use the two biggest parts
            if (count($format) > 1) {
                $format = array_shift($format).' and '.array_shift($format);
            } else {
                $format = array_pop($format);
            }

            // Prepend 'since ' or whatever you like
            return $defaultPeriodInterval->format($format);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getDefaultPeriodType($defaultPeriodInterval): ?string
    {
        try {
            $defaultPeriodInterval = new \DateInterval($defaultPeriodInterval);

            $defaultPeriodInterval->format('Y-m-d');

            $format = [];

            if ($defaultPeriodInterval->y !== 0) {
                $format[] = 'years';
            }

            if ($defaultPeriodInterval->m !== 0) {
                $format[] = 'months';
            }

            if ($defaultPeriodInterval->d !== 0) {
                $weeks = floor($defaultPeriodInterval->d / 7);

                $format[] = $weeks > 0 ? 'weeks' : 'days';
            }

            // We use the two biggest parts
            if (count($format) > 1) {
                $format = array_shift($format).' and '.array_shift($format);
            } else {
                $format = array_pop($format);
            }

            // Prepend 'since ' or whatever you like
            return $defaultPeriodInterval->format($format);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getPackagesForBox(Tenant $box, ?Location $boxFacility = null, ?Package $package = null, $packageLimitType = null, ?bool $isActive = null, ?bool $isForSignup = null, ?bool $isForBuyPackages = null, ?array $packageIds = null, ?bool $isHidden = null)
    {
        $qb = Package::query()
            ->from('packages', 'p')
            ->addSelect(DB::raw('CASE WHEN p.priority IS NULL THEN 1 ELSE 0 END as HIDDEN priority_order_is_null'))
            ->where('p.box_id', $box->getKey())
            ->orderBy('priority_order_is_null')
            ->orderBy('p.priority')
            ->orderBy('p.name');

        if ($boxFacility instanceof Location) {
            $qb->leftJoin('box_facility as bf', '')
                ->andWhere('bf = :boxFacility OR bf IS NULL')
                ->setParameter('boxFacility', $boxFacility);
        }

        if ($package instanceof Package) {
            $qb->andWhere('p = :package')->setParameter('package', $package);
        }

        if ($packageLimitType instanceof PackageLimitType) {
            $qb->andWhere('p.limitType = :packageLimitType')
                ->setParameter('packageLimitType', $packageLimitType);
        }

        if ($isActive !== null) {
            $qb->andWhere('p.active = :isActive')->setParameter('isActive', $isActive);
        }

        if ($isForSignup !== null) {
            $qb->andWhere('p.display = :isForSignup')->setParameter('isForSignup', $isForSignup);
        }

        if ($isForBuyPackages !== null) {
            $qb->andWhere('p.displayOnBuyPackages = :isForBuyPackages')->setParameter('isForBuyPackages', $isForBuyPackages);
        }

        if (is_array($packageIds)) {
            $qb->andWhere('p.id IN (:packageIds)')
                ->setParameter('packageIds', $packageIds);
        }

        if (is_bool($isHidden)) {
            $qb->andWhere('p.isHidden = :isHidden')->setParameter('isHidden', $isHidden);
        }

        return $qb->getQuery()->getResult();
    }
}

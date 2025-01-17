<?php

namespace App\Services;

use App\Models\ClassPackage;
use App\Models\Location;
use App\Models\Package;
use Illuminate\Database\Eloquent\Collection;

class ClassPackageService
{
    public function store($data)
    {
        $classPackage = new ClassPackage();
        $classPackage->fill($data);
        $classPackage->save();
    }

    public function getClassPackagesByPackageAndBoxFacility(Package $package, Location $boxFacility): Collection|array
    {

        return ClassPackage::query()
            ->join('classes', 'class_to_packages.class_id', '=', 'classes.class_id')
            ->join('packages', 'class_to_packages.package_id', '=', 'packages.package_id')
            ->where('class_to_packages.is_active', '=', true)
            ->where('classes.is_active', '=', true)
            ->where('class_to_packages.package_id', '=', $package->getKey())
            ->where('classes.box_facility_id', '=', $boxFacility->getKey())
            ->orderBy('classes.class_name', 'ASC')
            ->get();
    }
}

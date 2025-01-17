<?php

namespace App\Services;

use App\Enums\TenantStatus;
use App\Models\LocationUser;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LocationUserService
{
    public function store($data)
    {
        $userLocation = new LocationUser();
        $userLocation->fill($data);
        $userLocation->save();

        return $userLocation;
    }

    public function getActiveLocationByTenantId(User|int $user, Tenant|int $tenant): Model|Builder|null
    {

        return LocationUser::query()
            ->join('box_facility', 'user_to_facility.box_facility_id', '=', 'box_facility.box_facility_id')
            ->where('user_to_facility.user_id', $user instanceof User ? $user->getKey() : $user)
            ->where('box_facility.box_id', $tenant instanceof Tenant ? $tenant->getKey() : $tenant)
            ->where('user_to_facility.end_date', '>', today())
            ->where('box_facility.is_active', '=', TenantStatus::ACTIVE->value)
            ->first();
    }

    public function getOldestActiveFacilityMembershipForUserAndBox(User $user, Tenant $box): ?object
    {
        return LocationUser::query()
            ->from('user_to_facility', 'ufm')
            ->join('box_facility as f', 'f.box_facility_id', '=', 'ufm.box_facility_id')
            ->where('ufm.user_id', '=', $user->getKey())
            ->where('f.box_id', '=', $box->getKey())
            ->whereRaw('NOW() BETWEEN ufm.effective_date AND ufm.end_date')
            ->orderBy('ufm.user_to_facility_id')
            ->first();
    }
}

<?php

namespace App\Traits;

use App\Enums\UserType;
use Illuminate\Contracts\Database\Eloquent\Builder;

trait IsOwnedByUser
{
    public function scopeWithTenantUser(
        $query,
        $boxId,
        ?int $locationId = null,
        ?array $userTypeIds = null,
        ?bool $active = true
    ): Builder {
        $userTypeIds = $userTypeIds ?? array_merge(UserType::staffUserTypeIds(), [UserType::GYM_MEMBER->value]);

        return $query
            ->with(['user.tenant' => function ($query) use ($boxId, $locationId, $userTypeIds, $active) {
                $query->with(['user.coronavirusVaccinationDetails']);

                $query->with(['user.injuries' => function ($query) use ($boxId, $locationId) {
                    $query->where('box_id', $boxId);
                    $query->where('box_facility_id', $locationId);
                    $query->where('status', 'injured');
                    $query->latest('injury_id');
                    $query->limit(1);
                }]);

                if ($active) {
                    $query = $query->active();
                }

                $query->where('user_to_box.box_id', $boxId)
                    ->whereIn('user_to_box.user_type_id', $userTypeIds)
                    ->limit(1);
            }]);
    }
}

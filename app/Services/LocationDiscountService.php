<?php

namespace App\Services;

use App\Models\Location;
use App\Models\LocationUserDiscount;
use App\Models\User;

class LocationDiscountService
{
    public function getFacilityMembershipDiscountForUser(User $user, Location $boxFacility): ?object
    {
        return LocationUserDiscount::query()
            ->from('facility_membership_discounts', 'fmd')
            ->join('user_to_facility as fm', 'fm.user_to_facility_id', '=', 'fmd.user_to_facility_id')
            ->join('finance_discounts as fd', 'fd.discount_id', '=', 'fmd.discount_id')
            ->where('fm.user_id', '=', $user->getKey())
            ->where('fm.box_facility_id', '=', $boxFacility->getKey())
            ->where('fmd.status', '=', 'active')
            ->where('fd.deleted', false)
            ->where(function ($query) {
                $query->whereNull('fmd.ending_on')
                    ->orWhere('fmd.ending_on', '>', new \DateTime());
            })
            ->distinct()
            ->limit(1)
            ->first();
    }

    public function getFacilityMembershipDiscountForUserByDate(int $userLocationId, $date): ?LocationUserDiscount
    {
        return LocationUserDiscount::query()
            ->from('facility_membership_discounts', 'fmd')
            ->join('user_to_facility as fm', 'fm.user_to_facility_id', '=', 'fmd.user_to_facility_id')
            ->join('finance_discounts as fd', 'fd.discount_id', '=', 'fmd.discount_id')
            ->where('fmd.user_to_facility_id', '=', $userLocationId)
            ->where('fmd.starting_on', '<=', $date)
            ->where('fd.deleted', false)
            ->where(function ($query) {
                $query->whereNull('fmd.ending_on')
                    ->orWhere('fmd.ending_on', '>=', now()->format('Y-m-d'));
            })
            ->distinct()
            ->first();
    }
}

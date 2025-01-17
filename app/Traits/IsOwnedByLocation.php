<?php

namespace App\Traits;

use App\Models\Location;
use App\Models\Scopes\OwnedByLocationScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait IsOwnedByLocation
{
    use MutatesBoxFacilityId;

    /**
     * The "booted" method of the model.
     */
    public static function initializeIsOwnedByLocation(): void
    {
        //ensure lookups include box_id
        static::addGlobalScope(new OwnedByLocationScope());
    }

    /**
     * Location relation.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'box_facility_id');
    }
}

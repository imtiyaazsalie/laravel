<?php

namespace App\Traits;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait IsOwnedByTenant
{
    use MutatesBoxId;

    /**
     * The "booted" method of the model.
     */

    /**
     * Tenant relation.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'box_id');
    }
}

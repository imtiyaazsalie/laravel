<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class LocationAccessPrivilege extends Pivot
{
    protected $table = 'user_facility_access_privileges';

    protected $guarded = [];

    public $timestamps = true;

    protected $primaryKey = 'user_facility_access_privilege_id';

    public const CREATED_AT = 'created_on';

    public const UPDATED_AT = 'updated_on';

    protected $casts = [
        'revoked_on' => 'datetime',
        'revoked' => 'int',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id')->withTrashed();
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'box_facility_id', 'box_facility_id');
    }
}

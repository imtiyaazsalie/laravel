<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class UserAccessPrivilege extends Pivot
{
    use BelongsToTenant;
    use Compoships;

    protected $table = 'user_access_privileges';

    protected $primaryKey = 'user_access_privilege_id';

    protected $guarded = [];

    public $timestamps = true;

    public const CREATED_AT = 'created_on';

    public const UPDATED_AT = 'updated_on';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id')->withTrashed();
    }

    public function privilege(): BelongsTo
    {
        return $this->belongsTo(AccessPrivilege::class, 'access_privilege_id', 'access_privilege_id');
    }
}

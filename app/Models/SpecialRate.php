<?php

namespace App\Models;

use App\Traits\IsOwnedByUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpecialRate extends Model
{
    use HasFactory, IsOwnedByUser;

    public const CREATED_AT = 'dt_added';

    public const UPDATED_AT = 'dt_modified';

    public $timestamps = true;

    protected $table = 'special_rates';

    protected $primaryKey = 'special_rate_id';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'integer',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function for(string|int|User $user, string|int|Tenant $tenant): ?SpecialRate
    {
        if ($tenant instanceof Tenant) {
            $tenant = $tenant->getKey();
        }

        if ($user instanceof User) {
            $user = $user->getAuthIdentifier();
        }

        return self::query()
            ->where('is_active', true)
            ->where('user_id', $user)
            ->where('box_id', $tenant)
            ->first();
    }

    public function disable(): bool
    {
        return $this->update([
            'is_active' => false,
        ]);
    }
}

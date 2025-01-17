<?php

namespace App\Models;

use App\Enums\CoachRateStrategy;
use App\Enums\CoachRateType;
use App\Traits\BelongsToTenant;
use App\Traits\IsOwnedByLocation;
use App\Traits\Paginatable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoachRate extends Model
{
    use BelongsToTenant, HasFactory, IsOwnedByLocation, Paginatable;

    protected $table = 'coach_rates';

    protected $primaryKey = 'coach_rate_id';

    public $timestamps = true;

    public const CREATED_AT = 'created_on';

    public const UPDATED_AT = 'updated_on';

    protected $guarded = [];

    protected $attributes = [
        'deleted' => false,
    ];

    public function tenantId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->location()->exists() ? $this->location->box_id : null,
        );
    }

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'min_members' => 'integer',
        'max_members' => 'integer',
        'amount' => 'float',
        'type' => CoachRateType::class,
        'strategy' => CoachRateStrategy::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

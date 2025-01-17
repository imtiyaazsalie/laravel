<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\MutatesBoxId;
use App\Traits\Paginatable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalWod extends Model
{
    use BelongsToTenant, HasFactory, MutatesBoxId, Paginatable;

    public const CREATED_AT = 'created_on';

    public const UPDATED_AT = 'updated_on';

    public $timestamps = true;

    protected $table = 'personal_wod';

    protected $primaryKey = 'class_type_id';

    protected $casts = [
        'is_rx' => 'integer',
        'score' => 'float',
        'date' => 'date',
    ];

    protected $attributes = [
        'deleted' => 0,
    ];

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function measurementUnit(): BelongsTo
    {
        return $this->belongsTo(MeasurementUnit::class, 'measuring_unit_id');
    }

    public function score(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? round((float) $value, 2) : null,
            set: fn ($value) => $value ? round((float) $value, 2) : null,
        );
    }
}

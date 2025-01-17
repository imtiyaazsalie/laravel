<?php

namespace App\Models;

use App\Enums\WaiverStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Kirschbaum\PowerJoins\PowerJoins;

class LeadWaivers extends Model
{
    use PowerJoins;

    protected $table = 'lead_waivers';

    protected $primaryKey = 'waiver_id';

    protected $guarded = [];

    public $timestamps = true;

    public const CREATED_AT = 'created_on';

    public const UPDATED_AT = 'updated_on';

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'digital' => 'integer',
        'signed_on' => 'datetime',
        'status' => WaiverStatus::class,
    ];

    /**
     * Retrieve the model for a bound value.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $field = $field ? $this->getTable().'.'.$field : $this->getTable().'.'.$this->getRouteKeyName();

        return $this->where($field, $value)->first();
    }

    /**
     * The "booted" method of the model.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'box_facility_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(LeadWaivers::class, 'parent_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function leadSetting()
    {
        return $this->hasOne(LeadSettings::class, 'waiver_id');
    }

    public function isDigital(): bool
    {
        return (bool) $this->digital;
    }

    /**
     * Mutate box_facility_id to location_id
     */
    protected function locationId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->box_facility_id,
            set: fn (mixed $value) => ['box_facility_id' => $value]
        );
    }

    protected function fileUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => ! $this->isDigital() && $this->file_path ? Storage::disk('public')->url($this->file_path) : null,
        );
    }
}

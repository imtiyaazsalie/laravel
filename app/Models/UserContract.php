<?php

namespace App\Models;

use App\Enums\UserType;
use App\Traits\BelongsToTenant;
use App\Traits\IsOwnedByTenant;
use App\Traits\MutatesBoxFacilityId;
use App\Traits\Paginatable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Reedware\LaravelCompositeRelations\CompositeBelongsTo;
use Reedware\LaravelCompositeRelations\HasCompositeRelations;

class UserContract extends Model
{
    use BelongsToTenant, HasCompositeRelations, HasFactory, IsOwnedByTenant, MutatesBoxFacilityId, Paginatable;

    public $timestamps = false;

    protected $table = 'user_contracts';

    protected $primaryKey = 'user_contract_id';

    protected $guarded = [];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'starting_on' => 'datetime',
        'ending_on' => 'datetime',
        'accepted_on' => 'datetime',
        'sent_on' => 'datetime',
        'accepted' => 'integer',
        'sent' => 'integer',
    ];

    public function tenantId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->box_id,
        );
    }

    protected function locationId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attributes['box_facility_id'],
            set: fn (mixed $value) => ['box_facility_id' => $value]
        );
    }

    protected function fileUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->file_path
                ? Storage::disk('private')->temporaryUrl($this->file_path, now()->addMinutes(15))
                : null,
        );
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'box_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'box_facility_id');
    }

    public function tenantUser(): CompositeBelongsTo
    {
        return $this->compositeBelongsTo(TenantUser::class, ['box_id', 'user_id'], ['box_id', 'user_id'])
            ->where('user_to_box.user_type_id', UserType::GYM_MEMBER->value)
            ->where('user_to_box.deleted','=', 0);
    }

    public function userLocations(): CompositeBelongsTo
    {
        return $this->compositeBelongsTo(LocationUser::class, ['box_facility_id', 'user_id'], ['box_facility_id', 'user_id']);
    }

    public function periodAsString(): string
    {
        $start = $this->starting_on->format('Y-m-d');
        $end = $this->ending_on?->format('Y-m-d') ?? '';

        return $start.' '.$end;
    }
}

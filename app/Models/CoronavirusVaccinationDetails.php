<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\MutatesBoxId;
use App\Traits\Paginatable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CoronavirusVaccinationDetails extends Model
{
    use BelongsToTenant, HasFactory, MutatesBoxId, Paginatable;

    protected $table = 'coronavirus_vaccination_details';

    protected $primaryKey = 'id';

    public $timestamps = true;

    public const CREATED_AT = 'created_on';

    public const UPDATED_AT = 'updated_on';

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    /**
     * Mutate file to file_url
     */
    protected function fileUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->file ? Storage::disk('private')->temporaryUrl($this->file, now()->addMinute()) : null,
        );
    }
}

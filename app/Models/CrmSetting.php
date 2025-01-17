<?php

namespace App\Models;

use App\Traits\IsOwnedByLocation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmSetting extends Model
{
    use HasFactory, IsOwnedByLocation;

    protected $table = 'crm_settings';

    protected $primaryKey = 'setting_id';

    protected $guarded = [];

    public $timestamps = false;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'box_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'box_facility_id');
    }

    public function getReplyTo()
    {
        return $this->reply_to ?? config('octiv.emails.noreply');
    }

    public static function for(string|int $boxId, string|int|null $locationId = null): CrmSetting
    {
        return self::query()
            ->withoutGlobalScopes()
            ->where('box_facility_id', $locationId)
            ->orWhere(function ($query) use ($boxId) {
                $query->whereNull('box_facility_id')
                    ->where(function ($query) use ($boxId) {
                        $query->where('box_id', $boxId)
                            ->orWhereNull('box_id');
                    });
            })->firstOrFail();
    }
}

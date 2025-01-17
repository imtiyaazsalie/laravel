<?php

namespace App\Models;

use App\Casts\Serialize;
use App\Traits\MutatesBoxId;
use App\Traits\RecordUserOnCreateAndUpdate;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Setting extends Model
{
    use HasFactory, MutatesBoxId, RecordUserOnCreateAndUpdate;

    protected $table = 'box_settings';

    protected $primaryKey = 'id';

    protected $guarded = [];

    public $timestamps = true;

    public const CREATED_AT = 'created_on';

    public const UPDATED_AT = 'updated_on';

    protected $attributes = [
        'is_user_contract_visible_in_app' => 1,
        'coronavirus_is_enabled' => 1,
        'coronavirus_is_enabled_questionnaire_member_app' => 1,
        'coronavirus_can_display_vaccination_details_roster' => 1,
        'coronavirus_can_display_vaccination_details_class' => 1,
    ];

    protected $casts = [
        'coronavirus_is_enabled' => 'integer',
        'coronavirus_is_enabled_questionnaire_member_app' => 'integer',
        'coronavirus_can_display_vaccination_details_roster' => 'integer',
        'coronavirus_can_display_vaccination_details_class' => 'integer',
        'is_user_contract_visible_in_app' => 'integer',
        'theme' => Serialize::class,
        'hidden_features' => Serialize::class,
    ];

    protected function logoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null,
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }
}

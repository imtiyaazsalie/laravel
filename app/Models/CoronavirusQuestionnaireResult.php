<?php

namespace App\Models;

use App\Traits\Paginatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoronavirusQuestionnaireResult extends Model
{
    use HasFactory, Paginatable;

    public const CREATED_AT = 'created_on';

    public const UPDATED_AT = 'updated_on';

    public $timestamps = true;

    protected $table = 'covid19_questionnaire_results';

    protected $primaryKey = 'id';

    protected $guarded = [];

    protected $casts = [
        'temperature' => 'float',
        'has_cough' => 'integer',
        'has_difficulty_breathing' => 'integer',
        'has_fever' => 'integer',
        'has_been_in_contact_experiencing' => 'integer',
        'has_been_in_contact_positive' => 'integer',
        'has_travelled' => 'integer',
    ];

    public function classBooking(): BelongsTo
    {
        return $this->belongsTo(ClassBooking::class, 'class_booking_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id', 'user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id', 'user_id');
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $model->created_by_id = auth()->user()->getAuthIdentifier();
        });

        static::updating(function ($model) {
            $model->updated_by_id = auth()->user()->getAuthIdentifier();
        });
    }
}

<?php

namespace App\Models;

use App\Traits\RecordUserOnCreateAndUpdate;
use App\Traits\SoftDeletesBoolean;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InjuryUpdate extends Model
{
    use HasFactory, RecordUserOnCreateAndUpdate, SoftDeletesBoolean;

    protected $table = 'injury_updates';

    protected $primaryKey = 'updated_id';

    public $timestamps = true;

    public const CREATED_AT = 'created_on';

    public const UPDATED_AT = null;

    public const UPDATED_BY_ID = null;

    protected $guarded = [];

    public function createdBy(): HasOne
    {
        return $this->hasOne(User::class, 'user_id', 'created_by_id');
    }
}

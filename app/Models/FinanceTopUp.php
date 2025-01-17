<?php

namespace App\Models;

use App\Traits\RecordUserOnCreateAndUpdate;
use App\Traits\SoftDeletesBoolean;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceTopUp extends Model
{
    use HasFactory, RecordUserOnCreateAndUpdate, SoftDeletesBoolean;

    protected $table = 'finance_top_ups';

    protected $primaryKey = 'top_up_id';

    public $timestamps = true;

    public const CREATED_AT = 'created_on';

    public const UPDATED_AT = 'updated_on';

    protected $guarded = [];

    protected $attributes = [
        'deleted' => false,
    ];

    public function userPackage(): BelongsTo
    {
        return $this->belongsTo(UserPackage::class, 'user_to_package_id');
    }
}

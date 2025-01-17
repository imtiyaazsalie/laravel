<?php

namespace App\Models;

use App\Traits\IsOwnedByTenant;
use App\Traits\Paginatable;
use App\Traits\SoftDeletesBoolean;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinanceDiscount extends Model
{
    use HasFactory, IsOwnedByTenant, Paginatable, SoftDeletesBoolean;

    protected $table = 'finance_discounts';

    protected $primaryKey = 'discount_id';

    public $timestamps = true;

    public const CREATED_AT = 'created_on';

    public const UPDATED_AT = 'updated_on';

    protected $guarded = [];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'deleted' => 'integer',
    ];

    public function isFixed(): bool
    {
        return $this->type === 'fixed';
    }

    public function isPercentage(): bool
    {
        return $this->type === 'percentage';
    }
}

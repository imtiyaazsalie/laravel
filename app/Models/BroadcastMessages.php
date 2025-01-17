<?php

namespace App\Models;

use App\Traits\IsOwnedByTenant;
use App\Traits\Paginatable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BroadcastMessages extends Model
{
    use HasFactory, IsOwnedByTenant, Paginatable;

    protected $table = 'broadcast_messages';

    protected $primaryKey = 'broadcast_message_id';

    protected $guarded = [];

    public $timestamps = true;

    public const UPDATED_AT = 'dt_modified';

    public const CREATED_AT = null;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'integer',
        'dt_modified' => 'datetime',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Mutate broadcast_message to message
     */
    protected function message(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->broadcast_message,
            set: fn (mixed $value) => ['broadcast_message' => $value]
        );
    }

    public function tenant(): HasOne
    {
        return $this->hasOne(Tenant::class, 'box_id', 'box_id');
    }
}

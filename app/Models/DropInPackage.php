<?php

namespace App\Models;

use App\Traits\IsOwnedByTenant;
use App\Traits\Paginatable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DropInPackage extends Model
{
    use HasFactory, IsOwnedByTenant, Paginatable;

    protected $table = 'drop_in_packages';

    protected $primaryKey = 'id';

    protected $guarded = [];

    public $timestamps = true;

    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = 'updated_at';

    protected $casts = [
        'is_active' => 'integer',
        'priority' => 'integer',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Mutate created_by to created_by_id
     */
    protected function createdById(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->created_by,
            set: fn (mixed $value) => ['created_by' => $value]
        );
    }

    /**
     * Mutate updated_by to updated_by_id
     */
    protected function updatedById(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->updated_by,
            set: fn (mixed $value) => ['updated_by' => $value]
        );
    }

    public function tenant(): HasOne
    {
        return $this->hasOne(Tenant::class, 'box_id', 'box_id');
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(Classes::class, DropInPackageClasses::class, 'drop_in_package_id', 'class_id');
    }

    public function createdBy(): HasOne
    {
        return $this->hasOne(User::class, 'user_id', 'created_by');
    }

    public function updatedBy(): HasOne
    {
        return $this->hasOne(User::class, 'user_id', 'updated_by');
    }

    public function toggleStatus()
    {
        $this->update(['is_active' => ! $this->is_active]);
    }
}

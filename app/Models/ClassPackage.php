<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kirschbaum\PowerJoins\PowerJoins;

class ClassPackage extends Model
{
    use PowerJoins;

    public const CREATED_AT = 'dt_added';

    public const UPDATED_AT = 'dt_modified';

    public $timestamps = true;

    protected $table = 'class_to_packages';

    protected $primaryKey = 'class_to_package_id';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'integer',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_id');
    }
}

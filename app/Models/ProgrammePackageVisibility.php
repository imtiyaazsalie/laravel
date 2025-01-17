<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgrammePackageVisibility extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'programme_package_visibilities';

    protected $primaryKey = 'package_id';

    protected $guarded = [];

    public function package(): HasMany
    {
        return $this->hasMany(Package::class, 'package_id');
    }

    public function programme(): HasMany
    {
        return $this->hasMany(Programme::class, 'programme_id');
    }
}

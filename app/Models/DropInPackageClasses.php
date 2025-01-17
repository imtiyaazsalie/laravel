<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DropInPackageClasses extends Model
{
    use HasFactory;

    protected $table = 'drop_in_packages_to_classes';

    public $timestamps = false;

    protected $guarded = [];
}

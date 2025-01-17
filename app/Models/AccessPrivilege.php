<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccessPrivilege extends Model
{
    use HasFactory;

    protected $table = 'access_privileges';

    protected $primaryKey = 'access_privilege_id';

    protected $guarded = [];

    public $timestamps = false;
}

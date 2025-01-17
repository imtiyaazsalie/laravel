<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RemoteConfig extends Model
{
    use HasFactory;

    public const CREATED_AT = null;

    public const UPDATED_AT = 'updated_on';

    public $timestamps = true;

    protected $table = 'remote_config';

    protected $primaryKey = 'id';

    protected $guarded = [];
}

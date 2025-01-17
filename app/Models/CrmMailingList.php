<?php

namespace App\Models;

use App\Casts\Serialize;
use App\Traits\IsOwnedByTenant;
use Illuminate\Database\Eloquent\Model;

class CrmMailingList extends Model
{
    use IsOwnedByTenant;

    protected $table = 'crm_mailing_lists';

    protected $primaryKey = 'mailing_list_id';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'filters' => Serialize::class,
    ];
}

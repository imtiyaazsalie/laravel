<?php

namespace App\Models;

use App\Traits\IsOwnedByTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadSettings extends Model
{
    use HasFactory, IsOwnedByTenant;

    protected $table = 'lead_settings';

    protected $primaryKey = 'setting_id';

    protected $guarded = [];

    public $timestamps = false;

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'box_id');
    }

    public function waiver()
    {
        return $this->belongsTo(LeadWaivers::class, 'waiver_id');
    }
}

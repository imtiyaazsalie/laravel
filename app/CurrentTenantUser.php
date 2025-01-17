<?php

namespace App;

use App\Models\TenantUser;

interface CurrentTenantUser
{
    public function get(): TenantUser;
}

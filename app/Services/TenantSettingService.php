<?php

namespace App\Services;

use App\Models\Setting;

class TenantSettingService
{
    public function store($data)
    {
        $boxSetting = new Setting();
        $boxSetting->fill($data);
        $boxSetting->save();
    }
}

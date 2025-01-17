<?php

namespace App\Services\CRM;

use App\Models\CrmSetting;

class SettingService
{
    public function store($data)
    {
        $crmSettings = new CrmSetting();
        $crmSettings->fill($data);
        $crmSettings->save();
    }
}

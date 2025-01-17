<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum TenantStatus: int
{
    use SmartEnum;

    case ACTIVE = 1;
    case INACTIVE = 2;
    case SUSPENDED = 3;
    case DELETED = 4;
}

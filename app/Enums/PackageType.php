<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum PackageType: int
{
    use SmartEnum;

    case MONTHLY = 1;
    case WEEKLY = 2;
    case LIMITED = 3;
    case DROP_IN = 4;

    public function toString()
    {
        return str($this->name)->lower()->ucfirst()->toString();
    }
}

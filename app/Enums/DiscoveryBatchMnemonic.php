<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum DiscoveryBatchMnemonic: string
{
    use SmartEnum;

    case FACILITY_QUALIFYING = 'OCTF';
    case FACILITY_NON_QUALIFYING = 'OCFZ';
    case ONLINE_QUALIFYING = 'OCTV';
    case ONLINE_NON_QUALIFYING = 'OCTZ';
}

<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum DiscoveryVitalityBatchType: string
{
    use SmartEnum;

    case WORKOUT = 'workout';
    case SERVICING_WORKOUT = 'servicing-workout';
    case MONTHLY_RECON = 'monthly-recon';
}

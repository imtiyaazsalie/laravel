<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum GoCardlessWebhookStatus: string
{
    use SmartEnum;

    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case SKIPPED = 'skipped';
    case FAILED = 'failed';
    case ERROR = 'error';
}

<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum WaiverStatus: string
{
    use SmartEnum;

    case ORIGINAL = 'original';
    case SIGNED = 'signed';
    case SENT = 'sent';
}

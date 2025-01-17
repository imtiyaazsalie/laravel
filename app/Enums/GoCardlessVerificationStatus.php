<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum GoCardlessVerificationStatus: string
{
    use SmartEnum;

    case SUCCESSFUL = 'successful';
    case IN_REVIEW = 'in_review';
    case ACTION_REQUIRED = 'action_required';
}

<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum MailerRecipientType: string
{
    use SmartEnum;

    case MEMBER = 'member';
    case COACH = 'coach';
    case LEAD_MEMBER = 'lead-member';
    case NON_MEMBER = 'non-member';
    case REGION = 'region';
}

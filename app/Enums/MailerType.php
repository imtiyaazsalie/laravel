<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum MailerType: string
{
    use SmartEnum;

    case EMAIL = 'email';
    case SMS = 'sms';
    case PUSH = 'push';
}

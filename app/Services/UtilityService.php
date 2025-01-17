<?php

namespace App\Services;

use Hidehalo\Nanoid\Client as NanoIdClient;

class UtilityService
{
    public function generateNanoId($size = 21, ?bool $includeDashesAndUnderscores = false): string
    {
        $nanoIdClient = new NanoIdClient();

        if ($includeDashesAndUnderscores) {
            $nanoId = $nanoIdClient->generateId($size, NanoIdClient::MODE_DYNAMIC);
        } else {
            $nanoId = $nanoIdClient->formattedId('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', $size);
        }

        return $nanoId;
    }

    public function setAliasEmail($emailAddress): string
    {
        $email = $emailAddress;
        $alias = random_int(100000, 999999);
        $prefix = substr($email, 0, strrpos($email, '@'));
        $domain = substr($email, strpos($email, '@') + 1);

        return $prefix.'+'.$alias.'@'.$domain;
    }
}

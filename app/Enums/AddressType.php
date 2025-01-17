<?php

namespace App\Enums;

use App\Models\Location;

enum AddressType: string
{
    case LOCATION = 'location';

    /**
     * Get the fully qualified model class for the enum
     */
    public function getModelClass(): string
    {
        return match ($this) {
            self::LOCATION => Location::class,
        };
    }
}

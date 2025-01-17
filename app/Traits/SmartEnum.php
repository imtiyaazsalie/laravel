<?php

namespace App\Traits;

trait SmartEnum
{
    public function toArray()
    {
        return [
            'id' => $this->value,
            'name' => $this->name,
        ];
    }

    public static function asArray()
    {
        return array_map(fn ($case) => $case->toArray(), self::cases());
    }

    public static function values()
    {
        return array_map(fn ($case) => $case->value, self::cases());
    }

    public function toString(): string
    {
        return str($this->name)->lower()->ucfirst()->toString();
    }
}

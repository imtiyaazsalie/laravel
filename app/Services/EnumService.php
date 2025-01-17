<?php

namespace App\Services;

use HaydenPierce\ClassFinder\ClassFinder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class EnumService
{
    public const NAMESPACE = 'App\\Enums\\';

    public const CACHE_KEY = 'app.enums';

    public function list(): Collection
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            ClassFinder::disablePSR4Vendors();

            return collect(
                ClassFinder::getClassesInNamespace(self::NAMESPACE)
            )->map(fn ($enum) => str($enum)->afterLast('\\')->toString());
        });
    }

    public function refresh(): void
    {
        Cache::forget(self::CACHE_KEY);

        $this->list();
    }
}

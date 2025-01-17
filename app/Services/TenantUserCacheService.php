<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class TenantUserCacheService
{
    /**
     * Get membership data from cache (build cache if nessasary).
     */
    public function get(string|int $userId): Builder|array|Collection|Model
    {
        // return Cache::rememberForever('user_' . auth()->user()->getAuthIdentifier(), function () use ($userId) {
        $user = User::with([
            'injuries',
            'tenantUser.tenant.locations',
        ])->findOrFail($userId);

        return $user;
    }

    /**
     * Clear membership data from cache permanently.
     */
    public function clear(string|int $userId): bool
    {
        return Cache::forget('user_'.$userId);
    }

    /**
     * Rebuild membership data in cache.
     */
    public function rebuild(string|int $userId): array|Builder|Collection|Model
    {
        if (! $this->clear($userId)) {
            abort(ResponseAlias::HTTP_SERVICE_UNAVAILABLE, 'Could not clear user cache.');
        }

        return $this->get($userId);
    }
}

<?php

namespace App\Http\Controllers\API;

use App\Enums\Affiliate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Affiliates\LinkTenantAffiliateRequest;
use App\Http\Requests\Affiliates\UnlinkTenantAffiliateRequest;
use App\Models\TenantAffiliation;
use App\Services\ProgrammeService;

class TenantAffiliationController extends Controller
{
    public function link(LinkTenantAffiliateRequest $request)
    {
        TenantAffiliation::withTrashed()
            ->upsert(
                uniqueBy: ['affiliate_id', 'tenant_id'],
                update: ['deleted_at'],
                values: collect($request->tenant_ids)
                    ->map(fn ($tenantId) => [
                        'affiliate_id' => $request->affiliate_id,
                        'tenant_id' => $tenantId,
                        'deleted_at' => null,
                    ])
                    ->toArray(),
            );

        return response()->noContent();
    }

    public function unlink(UnlinkTenantAffiliateRequest $request)
    {
        $exists = TenantAffiliation::query()
            ->where('affiliate_id', $request->affiliate_id)
            ->whereIn('tenant_id', $request->tenant_ids)
            ->get();

        (new ProgrammeService)->deactivateAffiliateProgrammes(
            $exists->pluck('tenant_id')->toArray(),
            $request->enum('affiliate_id', Affiliate::class)
        );

        $exists->toQuery()->update(['deleted_at' => now()]);

        return response()->noContent();
    }
}

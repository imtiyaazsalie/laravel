<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Programme\Marketplace\AddProgrammeRequest;
use App\Http\Requests\Programme\Marketplace\ListProgrammesRequest;
use App\Http\Resources\ProgrammeResource;
use App\Models\Programme;
use App\Models\TenantAffiliation;
use App\Services\ProgrammeService;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ProgrammeMarketController extends Controller
{
    public function __construct(
        private ProgrammeService $programme
    ) {
    }

    public function index(ListProgrammesRequest $request)
    {
        $affiliations = TenantAffiliation::query()
            ->where('tenant_id', $request->input('filter.tenant_id'))
            ->pluck('affiliate_id')
            ->toArray();

        $programmes = QueryBuilder::for(Programme::class)
            ->global()
            ->whereIn('affiliate_id', $affiliations)
            ->allowedFilters([
                AllowedFilter::exact('affiliate_id'),
                AllowedFilter::scope('search'),
                AllowedFilter::exact('is_active'),
            ])
            ->orderBy('name')
            ->_paginate();

        return ProgrammeResource::collection($programmes);
    }

    public function store(AddProgrammeRequest $request)
    {
        $programme = DB::transaction(
            attempts: 2,
            callback: fn () => $this->programme->copy(
                programme: Programme::findOrFail($request->programme_id),
                tenantId: $request->tenant_id
            ),
        );

        return new ProgrammeResource($programme);

    }
}

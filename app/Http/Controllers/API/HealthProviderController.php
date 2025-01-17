<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\HealthCareProvider\CreateHealthCareProviderRequest;
use App\Http\Requests\HealthCareProvider\DeleteHealthCareProviderRequest;
use App\Http\Requests\HealthCareProvider\ListHealthCareProvidersRequest;
use App\Http\Requests\HealthCareProvider\UpdateHealthCareProviderRequest;
use App\Http\Resources\HealthProviderResource;
use App\Models\HealthCareProvider;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Knuckles\Scribe\Attributes\QueryParam;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class HealthProviderController extends Controller
{
    #[QueryParam('filter[is_active]', 'boolean', required: false)]
    public function list(ListHealthCareProvidersRequest $request): AnonymousResourceCollection
    {
        return HealthProviderResource::collection(
            QueryBuilder::for(HealthCareProvider::class)
                ->allowedFilters([
                    AllowedFilter::exact('is_active'),
                ])
                ->get()
        );
    }

    public function store(CreateHealthCareProviderRequest $request): HealthProviderResource
    {
        $healthProvider = new HealthCareProvider();

        $healthProvider->fill($request->validated());
        $healthProvider->save();

        return new HealthProviderResource($healthProvider);
    }

    public function update(HealthCareProvider $healthCareProvider, UpdateHealthCareProviderRequest $request): HealthProviderResource
    {
        $healthCareProvider->update($request->validated());

        return new HealthProviderResource($healthCareProvider);
    }

    public function delete(HealthCareProvider $healthCareProvider, DeleteHealthCareProviderRequest $request): Response
    {
        $healthCareProvider->delete();

        return response()->noContent();
    }
}

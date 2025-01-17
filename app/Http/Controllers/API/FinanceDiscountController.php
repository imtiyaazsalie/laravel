<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Discount\CreateDiscountRequest;
use App\Http\Requests\Discount\DeleteDiscountRequest;
use App\Http\Requests\Discount\ListDiscountsRequest;
use App\Http\Requests\Discount\ReadDiscountRequest;
use App\Http\Requests\Discount\UpdateDiscountRequest;
use App\Http\Resources\FinanceDiscountResource;
use App\Models\FinanceDiscount;
use App\Models\LocationUserDiscount;
use App\Models\Tenant;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class FinanceDiscountController extends Controller
{
    public function list(ListDiscountsRequest $request)
    {
        return FinanceDiscountResource::collection(
            QueryBuilder::for(FinanceDiscount::class)
                ->allowedFilters([
                    AllowedFilter::exact('tenant_id', 'box_id'),
                ])
                ->where('deleted', '=', false)
                ->distinct()
                ->_paginate()
        );
    }

    public function store(CreateDiscountRequest $request): FinanceDiscountResource
    {
        $tenant = Tenant::query()->find($request->get('tenant_id'));

        $financeDiscount = new FinanceDiscount();

        $financeDiscount->fill([
            'name' => $request->get('name'),
            'description' => $request->get('description'),
            'type' => $request->get('type'),
            'amount' => $request->get('amount'),
            'box_id' => $request->get('tenant_id'),
            'currency' => $tenant->memberCurrency->code,
            'created_by_id' => auth()->user()->getAuthIdentifier(),
            'deleted' => 0,
        ]);

        $financeDiscount->save();

        return new FinanceDiscountResource($financeDiscount);
    }

    public function show(ReadDiscountRequest $request, FinanceDiscount $financeDiscount): FinanceDiscountResource
    {
        return new FinanceDiscountResource($financeDiscount);
    }

    public function update(FinanceDiscount $financeDiscount, UpdateDiscountRequest $request)
    {
        $financeDiscount->update([
            'name' => $request->get('name'),
            'description' => $request->get('description'),
            'type' => $request->get('type'),
            'amount' => $request->get('amount'),
        ]);

        return new FinanceDiscountResource($financeDiscount);
    }

    public function delete(DeleteDiscountRequest $request, FinanceDiscount $financeDiscount): Response
    {
        LocationUserDiscount::query()
            ->where('discount_id', '=', $financeDiscount->getKey())
            ->where('status', '=', 'active')
            ->whereNull('ending_on')
            ->update([
                'status' => 'deactivated',
                'ending_on' => now(),
            ]);

        $financeDiscount->delete();

        return response()->noContent();
    }
}

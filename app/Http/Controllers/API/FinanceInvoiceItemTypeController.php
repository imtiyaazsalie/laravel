<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\InvoiceItemType\CreateInvoiceItemTypeRequest;
use App\Http\Requests\InvoiceItemType\DeleteInvoiceItemTypeRequest;
use App\Http\Requests\InvoiceItemType\ListInvoiceItemTypesRequest;
use App\Http\Requests\InvoiceItemType\ReadInvoiceItemTypeRequest;
use App\Http\Requests\InvoiceItemType\UpdateInvoiceItemTypeRequest;
use App\Http\Resources\FinanceInvoiceItemTypeResource;
use App\Models\InvoiceItemType;
use Illuminate\Http\Response;
use Knuckles\Scribe\Attributes\QueryParam;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class FinanceInvoiceItemTypeController extends Controller
{
    #[QueryParam('filter[tenant_id', 'integer', null, true)]
    public function list(ListInvoiceItemTypesRequest $request)
    {
        $financeInvoiceTypes = QueryBuilder::for(InvoiceItemType::class)
            ->allowedFilters([
                AllowedFilter::exact('tenant_id', 'box_id'),
            ])
            ->defaultSorts([
                'name',
            ])
            ->_paginate();

        return FinanceInvoiceItemTypeResource::collection($financeInvoiceTypes);
    }

    public function store(CreateInvoiceItemTypeRequest $request): FinanceInvoiceItemTypeResource
    {
        $financeInvoiceType = new InvoiceItemType();
        $financeInvoiceType->fill(['name' => $request->get('name'), 'box_id' => $request->get('tenant_id')]);
        $financeInvoiceType->save();

        return new FinanceInvoiceItemTypeResource($financeInvoiceType);
    }

    public function show(ReadInvoiceItemTypeRequest $request, InvoiceItemType $invoiceItemType): FinanceInvoiceItemTypeResource
    {
        return new FinanceInvoiceItemTypeResource($invoiceItemType);
    }

    public function update(InvoiceItemType $invoiceItemType, UpdateInvoiceItemTypeRequest $request): FinanceInvoiceItemTypeResource
    {
        $invoiceItemType->update(['name' => $request->get('name')]);

        return new FinanceInvoiceItemTypeResource($invoiceItemType);
    }

    public function delete(DeleteInvoiceItemTypeRequest $request, InvoiceItemType $invoiceItemType): Response
    {
        $invoiceItemType->delete();

        return response()->noContent();
    }
}

<?php

namespace App\Http\Controllers\API\POS;

use App\Http\Controllers\Controller;
use App\Http\Requests\POS\StockItem\CreateStockItemRequest;
use App\Http\Requests\POS\StockItem\DeleteStockItemRequest;
use App\Http\Requests\POS\StockItem\ImportStockItemsRequest;
use App\Http\Requests\POS\StockItem\ListStockItemCategoriesRequest;
use App\Http\Requests\POS\StockItem\ListStockItemsRequest;
use App\Http\Requests\POS\StockItem\ReadStockItemRequest;
use App\Http\Requests\POS\StockItem\UpdateStockItemRequest;
use App\Http\Resources\PosStockItemResource;
use App\Imports\StockItemImport;
use App\Models\Location;
use App\Models\PosStockItem;
use Illuminate\Support\Facades\Storage;
use Knuckles\Scribe\Attributes\QueryParam;
use Maatwebsite\Excel\Excel;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class StockItemController extends Controller
{
    #[QueryParam('filter[location_id]', 'integer', required: true)]
    #[QueryParam('filter[search]', 'string', required: false)]
    public function list(ListStockItemsRequest $request)
    {
        return PosStockItemResource::collection(
            QueryBuilder::for(PosStockItem::class)
                ->allowedFilters(
                    AllowedFilter::exact('location_id', 'box_facility_id'),
                    AllowedFilter::partial('search', 'name')
                )
                ->allowedIncludes(
                    'createdBy',
                    'location'
                )
                ->defaultSort('name')
                ->_paginate()
        );
    }

    public function show(ReadStockItemRequest $request, PosStockItem $stockItem)
    {
        return new PosStockItemResource($stockItem->loadMissing('createdBy', 'location'));
    }

    public function categories(ListStockItemCategoriesRequest $request)
    {
        return response()->json([
            'categories' => QueryBuilder::for(PosStockItem::class)
                ->select('category')
                ->allowedFilters(
                    AllowedFilter::exact('tenant_id', 'location.box_id'),
                    AllowedFilter::exact('location_id', 'box_facility_id')
                )
                ->distinct()
                ->get()
                ->pluck('category')
                ->toArray(),
        ]);
    }

    public function store(CreateStockItemRequest $request)
    {
        $stockItems = collect();
        $locations = Location::whereKey($request->location_ids)->get();

        $sku = null;
        $imageUrl = null;

        if ($request->hasFile('image')) {
            $fileName = uniqid(rand(), true).'.'.$request->file('image')->getClientOriginalExtension();
            $filePath = 'stock-items/';

            $request->file('image')->storePubliclyAs($filePath, $fileName, ['disk' => 'public']);

            $imageUrl = $filePath.$fileName;
        }

        // Get SKU
        if (! $request->has('sku') || empty($request->get('sku'))) {
            $sku = strtolower(preg_replace('/[^a-zA-Z]+/', '', $request->get('name')));
        }

        foreach ($request->location_ids as $locationId) {
            $location = $locations->where('box_facility_id', $locationId)->first();

            $stockItem = PosStockItem::create([
                ...$request->safe()->only([
                    'category',
                    'name',
                    'cost_price',
                    'selling_price',
                    'vat',
                    'description',
                    'stock_level',
                ]),
                'sku' => $sku ?? $request->get('sku'),
                'image_url' => $imageUrl,
                'location_id' => $locationId,
                'created_by_id' => auth()->user()->getAuthIdentifier(),
            ]);

            $stockItem->setRelation('location', $location);

            $stockItems->add($stockItem);
        }

        return PosStockItemResource::collection($stockItems);
    }

    public function update(UpdateStockItemRequest $request, PosStockItem $stockItem)
    {
        $sku = null;

        if ($request->has('image')) {
            // Delete the file from the server
            if ($stockItem->image && PosStockItem::query()->whereKeyNot($stockItem)->where('image_url', $stockItem->image)->doesntExist()) {
                Storage::disk('public')->delete($stockItem->image);
            }

            if (is_null($request->file('image'))) {
                // Set the image to null
                $stockItem->update(['image' => null]);
            } else {
                $fileName = uniqid(rand(), true).'.'.$request->file('image')->getClientOriginalExtension();
                $filePath = 'stock-items/';

                $request->file('image')->storePubliclyAs(
                    $filePath,
                    $fileName,
                    [
                        'disk' => 'public',
                        'visibility' => 'public',
                    ]
                );

                $stockItem->update(['image' => $filePath.$fileName]);
            }
        }

        // Get SKU
        if (! $request->has('sku') || empty($request->get('sku'))) {
            $sku = strtolower(preg_replace('/[^a-zA-Z]+/', '', $request->get('name')));
        }

        $stockItem->update([
            ...$request->safe()->only([
                'location_id',
                'category',
                'name',
                'cost_price',
                'selling_price',
                'vat',
                'description',
                'stock_level',
            ]),
            'sku' => $sku ?? $request->get('sku'),
        ]);

        return new PosStockItemResource($stockItem->loadMissing('createdBy', 'location'));
    }

    public function import(ImportStockItemsRequest $request)
    {
        (new StockItemImport($request->location_id))->queue($request->csv_file, 's3', Excel::CSV);

        return response()->noContent();
    }

    public function delete(DeleteStockItemRequest $request, PosStockItem $stockItem)
    {
        if ($stockItem->image && PosStockItem::query()->whereKeyNot($stockItem)->where('image_url', $stockItem->image)->doesntExist()) {
            Storage::disk('public')->delete($stockItem->image);
        }

        $stockItem->delete();

        return response()->noContent();
    }
}

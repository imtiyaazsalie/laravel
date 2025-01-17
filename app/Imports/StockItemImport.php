<?php

namespace App\Imports;

use App\Models\PosStockItem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithProgressBar;

class StockItemImport implements ShouldQueue, ToCollection, WithBatchInserts, WithChunkReading, WithHeadingRow, WithProgressBar
{
    use Importable;

    public function __construct(protected string|int $locationId)
    {
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function batchSize(): int
    {
        return 100;
    }

    public function rules(): array
    {
        return [
            'name' => 'required',
            'cost_price' => 'required|float',
            'selling_price' => 'required|float',
            'sku' => 'nullable',
            'stock_level' => 'required|nullable|integer|gte:-1',
            'description' => 'required',
            'category' => 'required',
        ];
    }

    public function collection(Collection $collection): void
    {
        $data = [];

        foreach ($collection as $item) {
            $data[] = [
                'created_by_id' => auth()->user()->getAuthIdentifier(),
                'box_facility_id' => $this->locationId,
                'name' => $item['name'],
                'cost_price' => $item['cost_price'],
                'selling_price' => $item['selling_price'],
                'sku' => $item['sku'] ?: strtolower(preg_replace('/[^a-zA-Z]+/', '', $item['name'])),
                'stock_level' => empty($item['stock_level']) ? -1 : $item['stock_level'],
                'description' => $item['description'],
                'category' => $item['category'],
            ];
        }

        if (! empty($data)) {
            PosStockItem::insert($data);
        }
    }
}

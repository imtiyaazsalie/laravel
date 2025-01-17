<?php

namespace App\Imports;

use App\Enums\Affiliate;
use App\Models\Tenant;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use RuntimeException;

class WodImport implements WithMultipleSheets
{
    public function __construct(
        private ?Tenant $tenant = null,
        private ?Affiliate $affiliate = null,
    ) {
        if ($this->affiliate) {
            $this->tenant = null;
        }

        if (! $this->tenant && ! $this->affiliate) {
            throw new RuntimeException('Tenant or affiliate must be provided');
        }

    }

    public function sheets(): array
    {
        return [
            new WodSheetImport(
                tenant: $this->tenant,
                affiliate: $this->affiliate,
            ),
        ];
    }
}

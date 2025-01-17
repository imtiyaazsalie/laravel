<?php

namespace App\Traits;

trait ValidatesContractDetails
{
    public function contractDetailsValidationRules(): array
    {
        return [
            'contract_details' => 'nullable|array',
            'contract_details.start_date' => 'date',
            'contract_details.end_date' => 'date|after_or_equal:contract_detail.start_date',
            'contract_details.file' => 'file',
        ];
    }
}

<?php

namespace App\Rules;

use App\Services\FinanceService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class IbanRule implements ValidationRule
{
    public function __construct(
        public string|int|null $bic
    ) {
        //
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $iban, Closure $fail): void
    {
        if (empty($iban)) {
            $fail('The IBAN number must be a valid IBAN number.');

            return;
        }

        if (empty($this->bic)) {
            $fail('The BIC number corresponding with the IBAN number must be valid.');

            return;
        }

        $errorMessage = (new FinanceService())->validateIbanAndBic($iban, $this->bic);

        if ($errorMessage) {
            $fail($errorMessage);

            return;
        }
    }
}

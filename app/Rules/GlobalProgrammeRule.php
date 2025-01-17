<?php

namespace App\Rules;

use App\Models\Programme;
use App\Models\TenantAffiliation;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class GlobalProgrammeRule implements ValidationRule
{
    public function __construct(
        private ?int $tenantId
    ) {
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->tenantId) {
            $fail('Tenant ID is required to check affiliation to :attribute.');

            return;
        }

        $programme = Programme::query()
            ->global()
            ->active()
            ->whereKey($value)
            ->first();

        if (! $programme) {
            $fail('The :attribute must be an active global programme.');

            return;
        }

        if (Programme::query()
            ->where('box_id', $this->tenantId)
            ->where('parent_id', $programme->getKey())
            ->exists()
        ) {
            $fail('Global programme already exists for this tenant.');

            return;
        }

        if (TenantAffiliation::isNotAffiliate($this->tenantId, $programme->affiliate_id)) {
            $fail('Tenant is not an affiliate of the selected :attribute.');
        }
    }
}

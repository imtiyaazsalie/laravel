<?php

namespace App\Rules;

use App\Models\Programme;
use App\Models\Scopes\OwnedByTenantScope;
use App\Traits\ValidatesIds;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ProgrammeRule implements ValidationRule
{
    use ValidatesIds;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct(public int|string|null $tenantId = null, public ?bool $active = null)
    {
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $ids = $this->validateIds($value)) {
            $fail('The :attribute must be a valid string, integer or array<string|int>');
        }

        $query = Programme::query()->whereIn('id', $ids);

        if ($this->tenantId) {
            $query = $query->where('box_id', $this->tenantId)->withoutGlobalScopes([OwnedByTenantScope::class]);
        }

        if (is_bool($this->active)) {
            $query = $query->where('is_active', $this->active)->get();
        }

        if (count($ids) !== $query->count()) {
            $status = $this->active ? 'active' : 'valid';
            $fail('The :attribute value must be '.$status.' programme ID(s).');
        }
    }
}

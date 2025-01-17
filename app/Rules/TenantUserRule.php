<?php

namespace App\Rules;

use App\Models\Scopes\OwnedByTenantScope;
use App\Models\TenantUser;
use App\Traits\ValidatesIds;
use Illuminate\Contracts\Validation\Rule;

class TenantUserRule implements Rule
{
    use ValidatesIds;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct(
        public string|int|null $tenantId = null,
        public ?array $userTypeIds = null,
        public ?bool $active = null,
    ) {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if (! $ids = $this->validateIds($value)) {
            return false;
        }

        $query = TenantUser::query()
            ->whereIn('user_to_box_id', $ids)
            ->when(is_bool($this->active), function ($query) {
                if ($this->active) {
                    $query->active();
                } else {
                    $query->inactive();
                }
            })
            ->when($this->userTypeIds, function ($query) {
                $query->whereIn('user_type_id', $this->userTypeIds);
            });

        if ($this->tenantId) {
            $query = $query->where('box_id', $this->tenantId)
                ->withoutGlobalScopes([OwnedByTenantScope::class]);
        }

        return $query->count() === count($ids);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'User box ID does not exists.';
    }
}

<?php

namespace App\Rules;

use App\Enums\PaymentGateway;
use App\Models\LocationPaymentGatewaySettings;
use App\Models\Scopes\OwnedByTenantScope;
use App\Traits\ValidatesIds;
use Illuminate\Contracts\Validation\Rule;

class FinancePaymentGatewaySettingRule implements Rule
{
    use ValidatesIds;

    /**
     * Create a new rule instance.
     */
    public function __construct(public string|int|null $tenantId = null, public string|int|null $paymentGateway = null, public bool $checkValidity = false)
    {
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     */
    public function passes($attribute, $value): bool
    {
        if (! $ids = $this->validateIds($value)) {
            return false;
        }

        $query = LocationPaymentGatewaySettings::query()->whereIn('setting_id', $ids)
            ->when($this->checkValidity, function ($query) {
                if ($this->paymentGateway === PaymentGateway::PAYSTACK->value) {
                    $query->whereNotNull('sub_account_id');
                }
            });

        if ($this->tenantId) {
            $query = $query->whereRelation('locationPaymentGateway', 'box_id', '=', $this->tenantId)
                ->withoutGlobalScopes([OwnedByTenantScope::class]);
        }

        return $query->count() === count($ids);
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return 'Payment gateway settings ID(s) not found or invalid.';
    }
}

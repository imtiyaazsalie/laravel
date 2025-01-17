<?php

namespace App\Http\Requests\UserPackage;

use App\Enums\PackageType;
use App\Enums\PaymentType;
use App\Enums\UserType;
use App\Models\Package;
use App\Rules\BooleanRule;
use App\Rules\PriceRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateUserPackageRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        /**
         * The rules
         *
         * 1. Discovery users need the 'discovery-vitality' scope in their token.
         * 2. Octiv Users without user_to_box records are not allowed.
         * 3. Discovery users without user_to_box records are allowed to pass in their own user_id.
         * 4. Gym members and lead members are allowed to pass in their own user_id.
         * 5. Location admins are allowed to pass in their own or other user_ids.
         */
        $user = auth()->user();

        if ($user->is_redacted) {
            if (! $user->tokenCan('discovery-vitality')) {
                return Response::deny('Access token requires more scope.');
            }

            $this->merge([
                'terms_and_conditions' => true,
            ]);

            return auth()->user()->getAuthIdentifier() == $this->user_id;
        }

        /** @var Package $package */
        $package = Package::findOrFail($this->package_id);

        return $this->canOperate(
            userTypes: [
                ...UserType::locationAdmins(),
                ...UserType::tenantMembers(),
            ],
            tenantId: $package->tenant_id,
            allowMember: true,
            userId: $this->user_id
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $package = Package::findOrFail($this->package_id);

        return [
            'user_id' => ['required', 'integer', 'exists:users,user_id'],
            'class_date_id' => 'sometimes|integer|exists:class_to_dates,class_to_date_id',
            'package_id' => ['required', 'integer', 'exists:packages,package_id'],
            'starting_on' => 'sometimes|date',
            'ending_on' => 'nullable|date|after:starting_on',
            'invoice' => 'nullable|array',
            'invoice.payment_type' => Rule::in(PaymentType::userPackageTypes()),
            'invoice.amount' => new PriceRule(),
            'invoice.is_send' => [new BooleanRule()],
            'terms_and_conditions' => $package->type === PackageType::DROP_IN ? 'accepted' : '',
        ];
    }
}

<?php

namespace App\Http\Requests\Admin\Location;

use App\Rules\PriceRule;
use App\Traits\Authorize;
use Illuminate\Foundation\Http\FormRequest;

class AdminProcessPaymentsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function authorize()
    {
        return $this->canOperate();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'billing_amounts' => 'required|array',
            'billing_amounts.*' => new PriceRule(),
        ];
    }
}

<?php

namespace App\Http\Requests\Settings;

use App\Enums\UserType;
use App\Rules\BooleanRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantBookingSettingsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {

        return $this->canOperate(
            permission: 'settings',
            tenantId: $this->route('tenant')->tenant_id,
            userTypes: [
                UserType::HEAD_COACH,
                UserType::BOX_ADMIN,
            ]
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'booking_threshold' => 'required|integer',
            'is_limit_inter_facility_bookings' => ['required', new BooleanRule()],
            'location_settings.*.id' => 'required|integer',
            'location_settings.*.max_bookings_per_athlete_per_day' => 'required|integer',
            'location_settings.*.is_view_class_bookings' => ['required', new BooleanRule()],
            'location_settings.*.is_display_booking_details' => ['required', new BooleanRule()],
            'location_settings.*.is_visible_in_app_sessions' => ['required', new BooleanRule()],
        ];
    }
}

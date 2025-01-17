<?php

namespace App\Http\Requests\CRM\Mailer;

use App\Enums\MailerType;
use App\Enums\UserType;
use App\Rules\ImageRule;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class CreateMailerRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::tenantStaff(),
            permission: 'communication_center',
            tenantId: $this->tenant_id,
            locationId: $this->location_id
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'tenant_id' => 'required|integer|exists:boxes,box_id',
            'location_id' => 'sometimes|integer|exists:box_facility,box_facility_id', // required for location admins

            'type' => ['required', new Enum(MailerType::class)],
            'name' => 'required|string|min:3|max:255',
            'title' => 'string|min:3|max:255',
            'content' => 'required|string',

            'recipients' => ['required', 'array'],

            'recipients.non_members' => 'array',
            'recipients.non_members.*.email' => 'required_if:type,email|email:rfc,dns',
            'recipients.non_members.*.mobile' => 'required_if:type,sms|string',

            'recipients.members' => 'array',
            'recipients.members.*' => 'integer|exists:users,user_id',

            'recipients.leads' => 'array',
            'recipients.leads.*' => 'integer|exists:users,user_id',

            'recipients.staff' => 'array',
            'recipients.staff.*' => 'integer|exists:users,user_id',

            'frequency' => ['required', Rule::in(['now', 'onceoff'])],
            'send_at' => 'required_if:frequency,=,onceoff|date_format:Y-m-d H:i:s|after:now',

            'subject' => 'required_if:type,email|string|min:3|max:255',
            'reply_to' => 'nullable|email:rfc,dns',
            'sender_name' => 'nullable|string|min:3|max:255',
            'attachment' => 'nullable|file|max:4000',
            'image_header' => ['nullable', new ImageRule()],
            'image_footer' => ['nullable', new ImageRule()],
        ];
    }
}

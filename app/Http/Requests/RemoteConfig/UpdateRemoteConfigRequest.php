<?php

namespace App\Http\Requests\RemoteConfig;

use App\Enums\UserType;
use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRemoteConfigRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        return $this->canOperate(
            userTypes: UserType::SUPER_ADMINISTRATOR
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'maintenance_title' => 'nullable|string',
            'maintenance_body' => 'nullable|string',
            'version_web_app' => 'required|string',
            'version_web_app_required' => 'required|string',
            'version_app_store_ios' => 'required|string',
            'version_app_store_ios_required' => 'required|string',
            'version_app_store_android' => 'required|string',
            'version_app_store_android_required' => 'required|string',
        ];
    }
}

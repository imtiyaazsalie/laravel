<?php

namespace App\Http\Requests\GoCardless;

use App\Traits\Authorize;
use GoCardlessPro\Core\Exception\InvalidSignatureException;
use GoCardlessPro\Webhook;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class EventsRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        try {
            $events = Webhook::parse(
                request()->getContent(),
                request()->header('Webhook-Signature'),
                config('gocardless.webhook_secret')
            );

            $this->merge(['events' => $events]);

            return true;
        } catch (InvalidSignatureException $ex) {

            Log::emergency('GoCardless events arrived but signature did not verify its origin, and therefore no GoCardless events were stored.');

            return Response::denyWithStatus(498, 'Invalid Token');
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [];
    }
}

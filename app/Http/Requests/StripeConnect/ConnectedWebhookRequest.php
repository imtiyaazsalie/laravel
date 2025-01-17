<?php

namespace App\Http\Requests\StripeConnect;

use App\Traits\Authorize;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class ConnectedWebhookRequest extends FormRequest
{
    use Authorize;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response|bool
    {
        try {
            $event = Webhook::constructEvent(
                request()->getContent(),
                $_SERVER['HTTP_STRIPE_SIGNATURE'],
                config('stripe-connect.connected_webhook_secret')
            );

            $this->merge(['event' => $event]);

            return true;
        } catch (SignatureVerificationException $e) {
            Log::emergency('Stripe webhook arrived but signature did not verify its origin, and therefore the webhook was not processed. Error message: '.$e->getMessage());

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

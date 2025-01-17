<?php

namespace App\Http\Controllers\API\Finance;

use App\Enums\PaymentGateway;
use App\Enums\PaymentGatewayContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Stripe\CreateCheckoutSessionRequest;
use App\Http\Requests\Stripe\EventsRequest;
use App\Models\LocationPaymentGatewaySettings;
use App\Models\UserInvoice;
use App\Services\PaymentGateways\StripeService;
use Exception;
use Illuminate\Http\Response;

class StripeController extends Controller
{
    public function createCheckoutSession(CreateCheckoutSessionRequest $request)
    {
        $invoice = UserInvoice::find($request->input('invoice_id'));
        $locationPaymentGatewaySettings = LocationPaymentGatewaySettings::with('locationPaymentGateway')
            ->whereRelation('locationPaymentGateway', 'payment_gateway_id', '=', PaymentGateway::STRIPE)
            ->whereRelation('locationPaymentGateway', 'context', '=', PaymentGatewayContext::AD_HOC)
            ->findOrFail($request->input('settings_id'));

        if (! $locationPaymentGatewaySettings->secret_key) {
            abort(404, 'Settings could not be found.');
        }

        return response()->json(['id' => (new StripeService())->createCheckOutSession($invoice, $locationPaymentGatewaySettings)]);
    }

    public function events(EventsRequest $request, $token)
    {
        // This might not be needed anymore. Leaving it here just in case I am lying.
        // $locationPaymentGatewaySettings = LocationPaymentGatewaySettings::where('public_token', '=', $token)->firstOrFail();

        try {
            (new StripeService())->handleWebhook($request);
        } catch (Exception $ex) {
            return new Response($ex->getMessage());
        }

        return response([], 200);
    }
}

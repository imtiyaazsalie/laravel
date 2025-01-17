<?php

namespace App\Http\Controllers\API\Finance\Paystack;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentGatewayContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Paystack\EventsRequest;
use App\Http\Requests\Paystack\GetSubAccount;
use App\Http\Requests\Paystack\InitializePaystackTransactionRequest;
use App\Http\Requests\Paystack\ListPaystackBanksRequest;
use App\Http\Requests\Paystack\PaystackSettlementsRequest;
use App\Http\Requests\Paystack\PaystackSettlementTransactionsRequest;
use App\Http\Requests\Paystack\UpdatePercentageCharge;
use App\Http\Requests\Paystack\VerifyPaystackTransactionRequest;
use App\Models\Location;
use App\Models\LocationPaymentGateway;
use App\Models\LocationPaymentGatewaySettings;
use App\Models\UserInvoice;
use App\Services\PaymentGateways\PaymentGatewayService;
use App\Services\PaymentGateways\PaystackService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PaystackController extends Controller
{
    public function __construct(protected PaystackService $paystack)
    {
    }

    public function listBanks(ListPaystackBanksRequest $request): JsonResponse
    {
        return response()->json($this->paystack->listBanksByCountry($request->input('filter.country')));
    }

    public function initializeTransaction(InitializePaystackTransactionRequest $request)
    {
        $authorizationURL = null;
        $invoice = UserInvoice::query()->findOrFail($request->invoice_id);
        $paymentGatewaySetting = LocationPaymentGatewaySettings::query()->findOrFail($request->settings_id);

        if ($invoice->status === InvoiceStatus::PAID) {
            abort(400, 'This invoice has already been paid.');
        }

        if ($invoice->amount_in_cents <= 0) {
            abort(400, 'An invoice with the amount of 0 or less cannot be processed.');
        }

        // This is to avoid duplicate transactions. This will solve the current paystack issue that is in Symfony.
        if ($invoice->gateway_payment_id && $invoice->gateway_notes) {
            $data = json_decode($invoice->gateway_notes, true);
            $authorizationURL = array_key_exists('authorization_url', $data) ? $data['authorization_url'] : null;
        }

        return response()->json(['authorizationURL' => $authorizationURL ?? $this->paystack->initializeTransaction($invoice, $paymentGatewaySetting)]);
    }

    public function verifyTransaction(VerifyPaystackTransactionRequest $request): JsonResponse
    {
        return response()->json($this->paystack->verifyTransaction($request->reference));
    }

    public function getSubAccount(GetSubAccount $request, Location $location): JsonResponse
    {
        $locationPaymentGateway = (new PaymentGatewayService())->getActiveLocationPaymentGateway($location, PaymentGateway::PAYSTACK, PaymentGatewayContext::AD_HOC);

        if (! $locationPaymentGateway) {
            abort(400, 'This location does not have active Paystack payment gateway credentials');
        }

        return response()->json($this->paystack->fetchSubaccount($locationPaymentGateway->settings->sub_account_id, true));
    }

    public function fetchSettlements(PaystackSettlementsRequest $request, Location $location): JsonResponse
    {
        $locationPaymentGateway = LocationPaymentGateway::query()
            ->where('box_facility_id', '=', $location->getKey())
            ->where('context', '=', 'adhoc_payment')
            ->where('payment_gateway_id', '=', PaymentGateway::PAYSTACK->value)
            ->where('is_active', '=', true)
            ->whereHas('settings', function ($query) {
                $query->whereNotNull('sub_account_id');
                $query->orWhereNotNull('sub_account_code');
            })->orderBy('facility_to_payment_gateway_id', 'desc')->first();

        if (! $locationPaymentGateway) {
            abort(400, 'This location does not have active Paystack payment gateway credentials');
        }

        return response()->json($this->paystack->fetchSettlements($locationPaymentGateway->settings->sub_account_id, $request->input('filter.start_date'), $request->input('filter.end_date')));
    }

    public function getSettlementTransactions(PaystackSettlementTransactionsRequest $request): JsonResponse
    {
        return response()->json($this->paystack->listTransactionsBySettlementId($request->input('filter.settlement_id'), $request->input('filter.start_date'), $request->input('filter.end_date')));
    }

    public function events(EventsRequest $request): Response
    {
        try {
            $this->paystack->processEvents($request);
        } catch (Exception $ex) {
            $class = get_class($ex);
            Log::error("Paystack event exception: ($class) {$ex->getMessage()}");
        }

        return response()->noContent(200);
    }

    public function updatePercentageCharge(UpdatePercentageCharge $request, Location $location): Response
    {
        $locationPaymentGateway = (new PaymentGatewayService())->getActiveLocationPaymentGateway($location, PaymentGateway::PAYSTACK, PaymentGatewayContext::AD_HOC);

        if (! $locationPaymentGateway) {
            abort(400, 'This location does not have active Paystack payment gateway credentials');
        }

        (new PaystackService())->updateSubaccountPercentageCharge($locationPaymentGateway->settings->sub_account_id, $request->get('percentage_charge'));

        return response()->noContent();
    }
}

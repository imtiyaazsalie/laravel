<?php

namespace App\Services\PaymentGateways;

use App\Enums\PaymentGateway as EnumsPaymentGateway;
use App\Enums\PaymentGatewayContext;
use App\Models\Location;
use App\Models\LocationPaymentGateway;
use App\Models\LocationPaymentGatewaySettings;
use App\Models\MandateGoCardless;
use App\Models\PaymentGateway;
use App\Models\StripeMandate;
use App\Models\Tenant;
use App\Models\UserInvoice;
use App\Services\CrmService;
use App\Services\MandateService;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Log;

class PaymentGatewayService
{
    public function getActiveLocationPaymentGateway(Location $location, EnumsPaymentGateway $paymentGateway, PaymentGatewayContext $paymentGatewayContext): ?LocationPaymentGateway
    {
        return LocationPaymentGateway::query()
            ->where('box_facility_id', $location->getKey())
            ->where('payment_gateway_id', '=', $paymentGateway)
            ->where('context', '=', $paymentGatewayContext)
            ->where('is_active', '=', true)
            ->with('settings')
            ->orderBy('facility_to_payment_gateway_id', 'desc')
            ->first();
    }

    public function attemptPayment(UserInvoice $invoice, LocationPaymentGateway $locationPaymentGateway): bool|string
    {
        if (! $invoice->locationUser) {
            return false;
        }

        if ($locationPaymentGateway->payment_gateway_id === EnumsPaymentGateway::SAGE_PAY_V3->value) {
            $result = false;
        } elseif ($locationPaymentGateway->payment_gateway_id === EnumsPaymentGateway::GO_CARDLESS->value) {
            // Check if this user has a mandate in this table
            $mandate = (new MandateService())->getMandateForUserAndLocation($invoice->locationUser->user, $locationPaymentGateway->location);

            if ($mandate instanceof MandateGoCardless) {
                $goCardlessPaymentId = (new GoCardlessService())->requestPaymentForInvoice($invoice, true);

                if ($goCardlessPaymentId) {
                    $result = true;
                } else {
                    $result = 'There was an error trying to make payment via GoCardless.';
                }
            } else {
                $result = 'User needs to have an active mandate to be charged.';
            }
        } elseif ($locationPaymentGateway->payment_gateway_id === EnumsPaymentGateway::STRIPE->value) {
            $mandate = StripeMandate::query()
                ->where('user_id', '=', $invoice->locationUser->user)
                ->where('facility_payment_gateway_id', '=', $locationPaymentGateway->getKey())
                ->where('status', '=', 'active')
                ->first();

            if ($mandate instanceof StripeMandate) {
                $stripeChargeId = (new StripeService())->requestPaymentForInvoice($invoice);

                if ($stripeChargeId) {
                    $result = true;
                } else {
                    $result = 'There was an error trying to make payment via Stripe.';
                }
            } else {
                $result = false;
            }
        } elseif ($locationPaymentGateway->payment_gateway_id === EnumsPaymentGateway::PAYSTACK) {
            $result = false;
        } else {
            $result = false;
        }

        return $result;
    }

    public function createPaymentRequest(UserInvoice $invoice, LocationPaymentGateway $locationPaymentGateway): bool
    {
        $crmService = resolve(CrmService::class);

        $location = $invoice->invoice_location;
        $replyTo = $crmService->getReplyTo($location);

        try {
            $content = Markdown::parse(
                view('emails.request-payment', [
                    'locationName' => $location->name,
                    'memberName' => $invoice->invoice_member_name,
                    'username' => auth()->user()->full_name,
                    'url' => config('octiv.web_app_url').'/payment/'.$invoice->getKey().'?gid='.$locationPaymentGateway->getKey(),
                ])
            )->__toString();

        } catch (Exception $exception) {
            Log::error($exception->getTraceAsString(), ['createPaymentRequest']);

            return false;
        }

        $crmService->createScheduledEmail(
            content: $content,
            subject: 'Octiv Payment Request',
            to: $invoice->invoice_email,
            replyTo: (! $replyTo || $replyTo == '') ? 'noreply@octivfitness.com' : $replyTo,
            tenant: $location->tenant,
            location: $location,
            skipUserChecks: true
        );

        return true;
    }

    public function createOrUpdateLocationPaymentGateway(Location $location, EnumsPaymentGateway $paymentGateway, ?LocationPaymentGateway $locationPaymentGateway, PaymentGatewayContext $paymentGatewayContext): ?LocationPaymentGateway
    {
        if (! $locationPaymentGateway instanceof LocationPaymentGateway) {
            // Create locationPaymentGateway entry
            $locationPaymentGateway = LocationPaymentGateway::create([
                'box_facility_id' => $location->getKey(),
                'payment_gateway_id' => $paymentGateway,
                'context' => $paymentGatewayContext,
            ]);
        }

        // Create settings if there are none or if new $boxFacilityPaymentGateway was created
        if (! $locationPaymentGateway->settings instanceof LocationPaymentGatewaySettings && $paymentGateway !== EnumsPaymentGateway::THREE_PEAKS) {
            LocationPaymentGatewaySettings::create([
                'box_facility_payment_gateway_id' => $locationPaymentGateway->getKey(),
                'public_token' => sha1(uniqid(true)),
                'discr' => EnumsPaymentGateway::getLocationPaymentGatewayDiscr($paymentGateway),
            ]);
        }

        return $locationPaymentGateway->refresh();
    }

    public function updateAdhocSettings(LocationPaymentGateway $locationPaymentGateway, Request $request): void
    {
        if ($request->has('is_for_sign_up')) {
            LocationPaymentGatewaySettings::query()
                ->join('box_facility_to_payment_gateway', 'box_facility_to_payment_gateway.facility_to_payment_gateway_id', '=', 'facility_payment_gateway_settings.box_facility_payment_gateway_id')
                ->where('facility_payment_gateway_settings.for_sign_up', '=', true)
                ->where('box_facility_to_payment_gateway.box_facility_id', '=', $locationPaymentGateway->location_id)
                ->where('box_facility_to_payment_gateway.is_active', '=', true)
                ->update(['for_sign_up' => false]);
        }

        if ($locationPaymentGateway->payment_gateway_id === EnumsPaymentGateway::SAGE_PAY_V3->value) {
            $locationPaymentGateway->settings->update([
                'merchant_account_number' => $request->get('merchant_account_number'),
                'pay_now_service_key' => $request->get('pay_now_service_key'),
                'for_sign_up' => $request->get('is_for_sign_up'),
            ]);
        } elseif ($locationPaymentGateway->payment_gateway_id === EnumsPaymentGateway::GO_CARDLESS->value) {
            $locationPaymentGateway->settings->update([
                'for_sign_up' => $request->get('is_for_sign_up'),
            ]);
        } elseif ($locationPaymentGateway->payment_gateway_id === EnumsPaymentGateway::STRIPE->value) {
            $locationPaymentGateway->settings->update([
                'public_key' => $request->get('public_key'),
                'secret_key' => $request->get('secret_key'),
                'for_sign_up' => $request->get('is_for_sign_up'),
            ]);
        } elseif ($locationPaymentGateway->payment_gateway_id === EnumsPaymentGateway::PAYSTACK->value) {
            if ($locationPaymentGateway->settings->sub_account_id || $locationPaymentGateway->settings->sub_account_code) {
                $subaccountData = (new PaystackService())->updateSubaccount($locationPaymentGateway->settings->sub_account_id, $request->get('bank_code'), $request->get('account_number'), $request->get('notify_email_address'));
            } else {
                $subaccountData = (new PaystackService())->createSubaccount($locationPaymentGateway->location, $request->get('bank_code'), $request->get('account_number'), $request->get('notify_email_address'));
            }

            if ($subaccountData['sub_account_id'] && $subaccountData['sub_account_code']) {
                $locationPaymentGateway->settings->update([
                    'sub_account_id' => $subaccountData['sub_account_id'],
                    'sub_account_code' => $subaccountData['sub_account_code'],
                    'modified_on' => now(),
                    'for_sign_up' => $request->get('is_for_sign_up'),
                ]);
            }
        }

        $locationPaymentGateway->refresh();
    }

    public function getBoxFacilityPaymentGatewayBySettings(Location $location, bool $isLimitedPackage): ?LocationPaymentGateway
    {
        $paymentGatewaySettings = $this->getCurrentSignUpSettingsForLocation($location);

        // If $paymentGatewaySettings is found and the selected package $isLimitedPackage then use that payment gateway settings going forward
        $locationPaymentGateway = ($paymentGatewaySettings instanceof LocationPaymentGatewaySettings && $isLimitedPackage) ? $paymentGatewaySettings->locationPaymentGateway : null;

        // Look for other active AD_HOC payment gateway settings if none were found specifically for sign-up
        if (! $locationPaymentGateway instanceof LocationPaymentGateway) {
            $locationPaymentGateways = $this->getLocationPaymentGateways($location, $isLimitedPackage);

            /** @var LocationPaymentGateway $paymentGateway */
            foreach ($locationPaymentGateways as $paymentGateway) {
                // Check if its goCardless or stripe so that we can make sure that they have valid details
                if (in_array($paymentGateway?->payment_gateway_id, [EnumsPaymentGateway::GO_CARDLESS->value, EnumsPaymentGateway::STRIPE->value, EnumsPaymentGateway::PAYSTACK->value])) {
                    $paymentGatewaySettings = LocationPaymentGatewaySettings::query()
                        ->where('box_facility_payment_gateway_id', '=', $paymentGateway->getKey())
                        ->first();

                    // Continue if settings are there but don't have active credentials
                    if ($paymentGatewaySettings->discr == 'stripe' && ! $paymentGatewaySettings->secret_key && ! $paymentGatewaySettings->public_key) {
                        continue;
                    } elseif ($paymentGatewaySettings->discr == 'go_cardless' && ! $paymentGatewaySettings->token && ! $paymentGatewaySettings->public_token) {
                        continue;
                    } elseif ($paymentGatewaySettings->discr == 'paystack' && ! $paymentGatewaySettings->sub_account_id) {
                        continue;
                    }
                }

                $locationPaymentGateway = $paymentGateway;
                break;
            }
        }

        return $locationPaymentGateway;
    }

    public function getCurrentSignUpSettingsForLocation(Location $location): ?LocationPaymentGatewaySettings
    {
        return LocationPaymentGatewaySettings::query()
            ->join('box_facility_to_payment_gateway', 'box_facility_to_payment_gateway.facility_to_payment_gateway_id', '=', 'facility_payment_gateway_settings.box_facility_payment_gateway_id')
            ->where('box_facility_to_payment_gateway.box_facility_id', '=', $location->getKey())
            ->where('box_facility_to_payment_gateway.is_active', '=', true)
            ->where('facility_payment_gateway_settings.for_sign_up', '=', true)
            ->first();
    }

    public function getLocationPaymentGatewaysByLocation(Location $location, string $context, $paymentGatewayIds): Collection|array
    {
        return LocationPaymentGateway::query()
            ->from('box_facility_to_payment_gateway', 'bfpg')
            ->join('payment_gateways as pg', 'bfpg.payment_gateway_id', '=', 'pg.payment_gateway_id')
            ->where('bfpg.box_facility_id', '=', $location->getKey())
            ->where('bfpg.is_active', '=', true)
            ->where('bfpg.context', '=', $context)
            ->whereIn('bfpg.payment_gateway_id', $paymentGatewayIds)
            ->orderByDesc('pg.payment_gateway_id')
            ->get();
    }

    private function getLocationPaymentGateways(Location $location, bool $isLimitedPackage = false): Collection|array
    {
        $tenant = $location->tenant;

        if ($tenant->region->region_desc == 'South Africa') {
            $locationPaymentGateways = $this->getLocationPaymentGatewaysByLocation(
                $location,
                PaymentGatewayContext::AD_HOC->value,
                [EnumsPaymentGateway::SAGE_PAY_V3->value, EnumsPaymentGateway::PAYSTACK->value]
            );
        } elseif ($this->isGoCardlessActiveInRegion($tenant->region->region_desc)) {
            $paymentGatewayIds = [EnumsPaymentGateway::GO_CARDLESS->value];

            if ($isLimitedPackage) {
                $paymentGatewayIds[] = EnumsPaymentGateway::STRIPE->value;
            }

            $locationPaymentGateways = $this->getLocationPaymentGatewaysByLocation(
                $location,
                PaymentGatewayContext::AD_HOC->value,
                $paymentGatewayIds
            );
        } else {
            $locationPaymentGateways = $this->getLocationPaymentGatewaysByLocation(
                $location,
                PaymentGatewayContext::AD_HOC->value,
                [EnumsPaymentGateway::STRIPE->value]
            );
        }

        return $locationPaymentGateways;
    }

    public function getAppropriatePaymentGatewaysForTenantAndContext(Tenant $tenant, PaymentGatewayContext $context): array
    {
        $paymentGateways = [];

        // Depending on region, get appropriate payment gateway
        if ($tenant->region->name == 'South Africa') {
            // Sage can be used for both debit-orders and adhoc payments
            $paymentGateways[] = PaymentGateway::query()
                ->where('payment_gateway_id', EnumsPaymentGateway::SAGE_PAY_V3)
                ->first();

            if ($context == PaymentGatewayContext::AD_HOC) {
                $paymentGateways[] = PaymentGateway::query()
                    ->where('payment_gateway_id', EnumsPaymentGateway::PAYSTACK)
                    ->first();
            }
        } elseif ($this->isGoCardlessActiveInRegion($tenant->region->name)) {
            if ($context == PaymentGatewayContext::AD_HOC) {
                $paymentGateways[] = PaymentGateway::query()
                    ->where('payment_gateway_id', EnumsPaymentGateway::GO_CARDLESS)
                    ->first();
            }
        }

        // This is for all regions
        $paymentGateways[] = PaymentGateway::query()
            ->where('payment_gateway_id', EnumsPaymentGateway::STRIPE)
            ->first();

        return $paymentGateways;
    }

    public function getAppropriateLocationPaymentGateways(Tenant $tenant, Location $location, PaymentGatewayContext $context, ?bool $isActive): array
    {
        $locationPaymentGateway = null;
        $locationPaymentGateways = [];

        if ($location->paymentGateway->isStripeConnect()) {
            $locationPaymentGateway = LocationPaymentGateway::query()
                ->where('box_facility_id', $location->getKey())
                ->where('payment_gateway_id', '=', EnumsPaymentGateway::STRIPE_CONNECT)
                ->whereHas('settings')
                ->first();

            $locationPaymentGateways[] = $locationPaymentGateway;
        } else {
            // Get appropriate payment gateways for box
            $paymentGateways = $this->getAppropriatePaymentGatewaysForTenantAndContext($tenant, $context);

            /** @var PaymentGateway $paymentGateway */
            foreach ($paymentGateways as $paymentGateway) {
                // Find active payment gateway settings
                if ($isActive !== false) { // true or null
                    $locationPaymentGateway = LocationPaymentGateway::query()
                        ->where('box_facility_id', $location->getKey())
                        ->where('payment_gateway_id', $paymentGateway->getKey())
                        ->where('context', PaymentGatewayContext::AD_HOC)
                        ->where('is_active', true)
                        ->orderBy('facility_to_payment_gateway_id', 'desc')
                        ->first();
                }

                if ($isActive !== true) { // false or null
                    // If no active payment gateway settings then find most recent inactive payment gateway settings
                    if (! $locationPaymentGateway instanceof LocationPaymentGateway) {
                        $locationPaymentGateway = LocationPaymentGateway::query()
                            ->where('box_facility_id', $location->getKey())
                            ->where('payment_gateway_id', $paymentGateway->getKey())
                            ->where('context', PaymentGatewayContext::AD_HOC)
                            ->where('is_active', false)
                            ->orderBy('facility_to_payment_gateway_id', 'desc')
                            ->first();
                    }
                }

                if ($locationPaymentGateway instanceof LocationPaymentGateway) {
                    $locationPaymentGateways[] = $locationPaymentGateway;
                }
            }
        }

        return $locationPaymentGateways;
    }

    private function isGoCardlessActiveInRegion($regionName): bool
    {
        return in_array($regionName, ['Australia', 'Austria', 'Belgium', 'Bulgaria', 'Canada', 'Croatia', 'Cyprus', 'Czech Republic', 'Denmark', 'Finland', 'France', 'Germany', 'Hungary', 'Italy', 'Luxembourg', 'Malta', 'Netherlands', 'New Zealand', 'Norway', 'Poland', 'Portugal', 'Republic of Ireland', 'Romania', 'Slovakia', 'Slovenia', 'Spain', 'Sweden', 'Switzerland', 'United Kingdom', 'United States']);
    }
}

<?php

namespace App\Http\Controllers\API\Finance\GoCardless;

use App\Enums\MandateStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentGatewayContext;
use App\Enums\UserDebitStatus;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Helpers\CollectionHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\GoCardless\DeleteMandateRequest;
use App\Http\Requests\GoCardless\EventsRequest;
use App\Http\Requests\GoCardless\GetDebitOrderMembersRequest;
use App\Http\Requests\GoCardless\GetOnboardingLinkRequest;
use App\Http\Requests\GoCardless\ImportMandatesRequest;
use App\Http\Requests\GoCardless\MandateCallbackRequest;
use App\Http\Requests\GoCardless\ManuallyProcessEventRequest;
use App\Http\Requests\GoCardless\MerchantOAuthCallbackRequest;
use App\Http\Requests\GoCardless\PaymentByIdRequest;
use App\Http\Requests\GoCardless\PayoutItemsRequest;
use App\Http\Requests\GoCardless\PayoutsRequest;
use App\Http\Requests\GoCardless\ProcessPaymentForInvoiceRequest;
use App\Http\Requests\GoCardless\SendOnboardingLinkRequest;
use App\Http\Resources\UserInvoiceResource;
use App\Http\Resources\UserResource;
use App\Jobs\GoCardless\ProcessEventsJob;
use App\Models\GoCardlessWebhookEvent;
use App\Models\Location;
use App\Models\LocationPaymentGatewaySettings;
use App\Models\MandateGoCardless;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\UserInvoice;
use App\Services\LocationService;
use App\Services\MandateService;
use App\Services\NotificationsService;
use App\Services\PaymentGateways\GoCardlessService;
use App\Services\TenantUserService;
use Carbon\Carbon;
use Exception;
use GoCardlessPro\Core\Exception\InvalidStateException;
use Illuminate\Database\Eloquent\Builder;
use Knuckles\Scribe\Attributes\QueryParam;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class GoCardlessController extends Controller
{
    public function __construct(public GoCardlessService $goCardlessService, protected NotificationsService $notifications)
    {
    }

    #[QueryParam('filter[tenant_id]', 'integer', required: true)]
    #[QueryParam('filter[location_id]', 'integer', required: false)]
    #[QueryParam('filter[mandate_status]', 'boolean', required: false)]
    #[QueryParam('page', 'boolean', required: false)]
    #[QueryParam('per_page', 'integer', required: false)]
    public function listDebitOrderMembers(GetDebitOrderMembersRequest $request)
    {
        $tenantUsers = QueryBuilder::for(TenantUser::class)
            ->allowedFilters([
                AllowedFilter::exact('tenant_id', 'user_to_box.box_id'),
                AllowedFilter::callback('location_id', function (Builder $query, $value) {
                    $query->join('user_to_facility', 'user_to_box.user_id', 'user_to_facility.user_id')
                        ->where('user_to_facility.box_facility_id', '=', $value)
                        ->whereDate('user_to_facility.end_date', '>', now());
                }),
            ])
            ->where('user_to_box.user_type_id', '=', UserType::GYM_MEMBER)
            ->where('user_to_box.user_debit_status_id', '=', UserDebitStatus::DEBIT_ORDER)
            ->active()
            ->join('users', 'users.user_id', '=', 'user_to_box.user_id')
            ->orderBy('users.name', 'ASC')
            ->orderBy('users.surname', 'ASC')
            ->get();

        /** @var TenantUser $tenantUser */
        foreach ($tenantUsers as $key => $tenantUser) {
            $user = $tenantUser->user;
            $tenant = $tenantUser->tenant;
            $location = (new TenantUserService())->getLocationUserByTenant($user, $tenant)?->location;

            if (! $location) {
                continue;
            }

            $tenantUser->user->setAttribute('is_onboarded', $this->goCardlessService->isUserOnBoard($user, $location));
            $tenantUser->user->load('userTenant');
            $tenantUser->user->load('tenantUser');

            $mandateStatus = $request->mandate_status ? [$request->mandate_status] : null;
            $mandate = $this->goCardlessService->getMandateForUser($user, $location, $mandateStatus)->first();

            if ($mandateStatus && ! $mandate) {
                $tenantUsers->forget($key);
            }

            if ($mandate) {
                $tenantUser->user->setAttribute('gocardless_mandate', $mandate);
            }
        }

        return UserResource::collection(
            CollectionHelper::paginate($tenantUsers->pluck('user'), $request->per_page)
        );
    }

    public function getOnboardingLink(GetOnboardingLinkRequest $request)
    {
        $tenantUser = (new TenantUserService())->getCurrentUserTenantForTenant($request->user_id, $request->tenant_id);

        if (! $tenantUser) {
            abort(404, 'Tenant membership could not be found.');
        }

        $user = $tenantUser->user;
        $tenant = $tenantUser->tenant;

        $locationUser = (new TenantUserService())->getLocationUserByTenant($user, $tenant);

        if (! $locationUser) {
            abort(404, "This user does not have an active location membership at this $tenant->name.");
        }

        $location = $locationUser->location;

        if (! $this->goCardlessService->isLocationOnboarded($location)) {
            abort(400, "This user's location($location->name) has not yet been on-boarded to GoCardless.");
        }

        $mandate = $this->goCardlessService->getMandateForUser($user, $location, [MandateStatus::CREATED, MandateStatus::ACTIVE])->first();

        if ($mandate) {
            abort(400, 'User has already been on-boarded to GoCardless');
        }

        if ($tenantUser->user_debit_status_id !== UserDebitStatus::DEBIT_ORDER) {
            abort(400, 'User is not a debit order user');
        }

        try {
            return response()->json([
                'url' => $this->goCardlessService->beginUserOnBoardingFlow($user, $location),
            ]);
        } catch (InvalidStateException|Exception $e) {
            abort(400, $e->getMessage());
        }
    }

    public function sendOnBoardingLink(SendOnboardingLinkRequest $request)
    {
        $tenant = Tenant::findOrFail($request->tenant_id);
        $userIds = $request->user_ids;

        foreach ($userIds as $userId) {
            $tenantUser = (new TenantUserService())->getCurrentUserTenantForTenant($userId, $tenant);

            if (! $tenantUser || $tenantUser->tenant_id !== $tenant->getKey()) {
                continue;
            }

            $user = $tenantUser->user;

            $locationUser = (new TenantUserService())->getLocationUserByTenant($user, $tenant);

            if (! $locationUser) {
                continue;
            }

            $location = $locationUser->location;

            if (! $this->goCardlessService->isLocationOnboarded($location)) {
                continue;
            }

            $mandate = $this->goCardlessService->getMandateForUser($user, $location, [MandateStatus::CREATED, MandateStatus::PENDING_SUBMISSION, MandateStatus::SUBMITTED, MandateStatus::ACTIVE])->first();

            if ($mandate) {
                continue;
            }

            if ($tenantUser->user_debit_status_id !== UserDebitStatus::DEBIT_ORDER) {
                continue;
            }

            $tenantUser->go_cardless_link_sent_on = now();
            $tenantUser->save();

            (new MandateService())->sendOnboardingMail($tenantUser->user, $tenantUser->tenant);
        }

        return response()->noContent();
    }

    public function importMandates(ImportMandatesRequest $request, Location $location)
    {
        if (! $this->goCardlessService->isLocationOnboarded($location)) {
            abort(400, "This user's location($location->name) has not yet been on-boarded to GoCardless.");
        }

        $locationPaymentGateway = (new LocationService())->getActivePaymentGatewayByContextAndTypeId($location, PaymentGatewayContext::AD_HOC, PaymentGateway::GO_CARDLESS)->first();
        $token = $locationPaymentGateway->settings?->token;

        $createdCount = 0;
        $updatedCount = 0;
        $validMandatesCount = 0;
        $listOfMandates = null;
        $locationUser = null;

        try {
            $mandates = $this->goCardlessService->listMandates($token, [
                'params' => ['limit' => '500'],
            ]);
        } catch (Exception $ex) {
            abort(400, $ex->getMessage());
        }

        $listOfMandates[] = $mandates->records;
        $allMandatesCount = count($mandates->records);

        if ($mandates->after) {
            do {
                try {
                    $mandates = $this->goCardlessService->listMandates($token, [
                        'params' => [
                            'after' => $mandates->after,
                            'limit' => '500',
                        ],
                    ]);

                    $listOfMandates[] = $mandates->records;
                    $allMandatesCount += count($mandates->records);
                } catch (Exception $ex) {
                    abort(400, $ex->getMessage());
                }
            } while ($mandates->after);
        }

        $goCardlessData = [];

        foreach ($listOfMandates as $recordSet) {
            foreach ($recordSet as $mandate) {
                if (in_array($mandate->status, ['pending_customer_approval', 'pending_submission', 'submitted', 'active'])) {
                    $validMandatesCount += 1;

                    $goCardlessData[] = [
                        'mandate' => $mandate,
                        'customer' => $this->goCardlessService->getCustomer($token, $mandate->links->customer),
                    ];
                }
            }
        }

        // Get the latest mandates in case there is more than one mandate for a user
        $uniqueGoCardlessData = [];

        foreach ($goCardlessData as $item) {
            $customerEmail = $item['customer']->email;

            $uniqueItem = array_filter($uniqueGoCardlessData, function ($uniqueDataItem) use ($customerEmail) {
                return $uniqueDataItem['customer']->email === $customerEmail;
            });

            if (count($uniqueItem) === 0) {
                $uniqueGoCardlessData[] = $item;
            } else {
                $uniqueItem = reset($uniqueItem);

                if ($uniqueItem && $uniqueItem['mandate']->created_at < $item['mandate']->created_at) {
                    $index = array_search($uniqueItem, $uniqueGoCardlessData);
                    array_splice($uniqueGoCardlessData, $index, 1);
                    $uniqueGoCardlessData[] = $item;
                }
            }
        }

        $results = [];
        $tenant = $location->tenant;

        foreach ($uniqueGoCardlessData as $customerAndMandate) {
            $goCardlessMandate = $customerAndMandate['mandate'];
            $goCardlessCustomer = $customerAndMandate['customer'];

            // Check if the customer is a user in our system
            $tenantUser = TenantUser::query()
                ->whereRelation('user', 'email', '=', $goCardlessCustomer->email)
                ->where('box_id', '=', $tenant->getKey())
                ->whereIn('user_to_box.user_status_id', [UserStatus::PENDING, UserStatus::ACTIVE])
                ->withinActivePeriod()
                ->first();

            if ($tenantUser) {
                // Check if member is a location user at this location
                $locationUser = (new TenantUserService())->getLocationUserByTenant($tenantUser->user, $tenant);
            }

            if ($tenantUser && $tenantUser->type === UserType::GYM_MEMBER && $locationUser->location->getKey() === $location->getKey()) {
                $mandateCreatedAt = Carbon::parse($goCardlessMandate->created_at);

                // Check if this user has a mandate in our system
                $existingMandate = $this->goCardlessService->getMandateForUser($tenantUser->user, $location)->first();

                $note = null;

                if ($goCardlessMandate->status == MandateStatus::CREATED->value) {
                    $note = "Mandate $goCardlessMandate->id has been created. Created at ".$mandateCreatedAt->format('Y-m-d H:i:s');
                } elseif ($goCardlessMandate->status == MandateStatus::SUBMITTED->value) {
                    $note = "Mandate $goCardlessMandate->id has been submitted. Created at ".$mandateCreatedAt->format('Y-m-d H:i:s');
                } elseif ($goCardlessMandate->status == MandateStatus::ACTIVE->value) {
                    $note = "Mandate $goCardlessMandate->id has been activated. Created at ".$mandateCreatedAt->format('Y-m-d H:i:s');
                }

                // Update existing mandate else create a new mandate if user does not have one yet
                if ($existingMandate) {
                    // Check if this user has a mandate on goCardless
                    $existingMandate->update([
                        'user_id' => $tenantUser->user_id,
                        'box_id' => $tenantUser->tenant_id,
                        'facility_payment_gateway_id' => $locationPaymentGateway->getKey(),
                        'mandate' => $goCardlessMandate->id,
                        'customer' => $goCardlessCustomer->id,
                        'status' => $goCardlessMandate->status,
                        'created_at' => $mandateCreatedAt,
                        'note' => $note,
                    ]);

                    if ($goCardlessMandate->status !== MandateStatus::CANCELLED) {
                        $existingMandate->update([
                            'cancelled_at' => null,
                            'cancel_reason' => null,
                        ]);
                    }

                    $updatedCount += 1;
                    $results[] = "Mandate updated for: {$tenantUser->user->email} - $goCardlessMandate->id";
                } else {
                    $mandate = MandateGoCardless::create([
                        'user_id' => $tenantUser->user_id,
                        'box_id' => $tenantUser->tenant_id,
                        'facility_payment_gateway_id' => $locationPaymentGateway->getKey(),
                        'mandate' => $goCardlessMandate->id,
                        'customer' => $goCardlessCustomer->id,
                        'status' => $goCardlessMandate->status,
                        'created_at' => $mandateCreatedAt,
                        'note' => $note,
                    ]);

                    $createdCount += 1;

                    $results[] = "Mandate created for: {$tenantUser->user->email} - $goCardlessMandate->id";
                }
            } else {
                if (! $tenantUser) {
                    $reason = 'user does not have an active facility-membership for this location';
                } elseif ($tenantUser->type !== UserType::GYM_MEMBER) {
                    $reason = "user is not a 'Gym member'";
                } elseif (! $locationUser) {
                    $reason = 'user does not belong to a facility';
                } elseif ($locationUser->location->getKey() !== $location->getKey()) {
                    $reason = 'user does not belong to this facility';
                } else {
                    $reason = 'unknown reason.';
                }

                $results[] = "Mandate not created for: $goCardlessCustomer->email - $goCardlessMandate->id, because $reason";
            }
        }

        $metadata['allMandatesCount'] = "$allMandatesCount mandates found for: $location->name (all)";
        $metadata['validMandatesCount'] = "$validMandatesCount valid mandates found for: $location->name";
        $metadata['createdMandatesCount'] = "$createdCount mandates have been created in our system";
        $metadata['updatedMandatesCount'] = "$updatedCount mandates have been updated in our system";

        return response([
            'results' => $results,
            'metaData' => $metadata,
        ]);
    }

    public function deleteMandate(DeleteMandateRequest $request, MandateGoCardless $mandate)
    {
        $mandate->delete();

        return response()->noContent();
    }

    public function getPayouts(PayoutsRequest $request)
    {
        $location = Location::query()->findOrFail($request->location_id);
        $locationPaymentGateway = (new LocationService())->getActivePaymentGatewayByContextAndTypeId($location, PaymentGatewayContext::AD_HOC, PaymentGateway::GO_CARDLESS)->first();
        $token = $locationPaymentGateway?->settings?->token;

        if (! $token) {
            abort(404, 'Location does not have an active GC token.');
        }

        try {
            return response($this->goCardlessService->getPayouts($token, Carbon::parse($request->start_date), Carbon::parse($request->end_date), $request->get('limit', 100), $request->after, $request->before));
        } catch (Exception $exception) {
            abort($exception->getCode() ?: 400, $exception->getMessage());
        }
    }

    public function getPayoutsItems(PayoutItemsRequest $request)
    {
        $location = Location::find($request->location_id);
        $locationPaymentGateway = (new LocationService())->getActivePaymentGatewayByContextAndTypeId($location, PaymentGatewayContext::AD_HOC, PaymentGateway::GO_CARDLESS)->first();
        $token = $locationPaymentGateway?->settings?->token;

        if (! $token) {
            abort(404, 'Location does not have an active GC token.');
        }

        try {
            $payoutItemsData = $this->goCardlessService->getPayoutItems($token, $request->payout_id, $request->get('limit', 100), $request->after, $request->before);

            $invoices = UserInvoice::whereIn('gateway_payment_id', $payoutItemsData['payout_items']->pluck('payment_id')->all())->get();

            $payoutItemsData['payout_items']->transform(function ($payoutItem) use ($invoices) {
                $invoice = $invoices->where('gateway_payment_id', '=', $payoutItem['payment_id'])->first();
                $payoutItem['invoice'] = $invoice ? new UserInvoiceResource($invoice) : null;

                return $payoutItem;
            });

            return response($payoutItemsData);
        } catch (Exception $exception) {
            abort($exception->getCode() ?: 400, $exception->getMessage());
        }
    }

    public function getPaymentById(PaymentByIdRequest $request, $id)
    {
        $location = Location::find($request->location_id);
        $locationPaymentGateway = (new LocationService())->getActivePaymentGatewayByContextAndTypeId($location, PaymentGatewayContext::AD_HOC, PaymentGateway::GO_CARDLESS)->first();
        $token = $locationPaymentGateway?->settings?->token;

        if (! $token) {
            abort(404, 'Location does not have an active GC token.');
        }

        try {
            $payment = $this->goCardlessService->getPayment($token, $id);

            if (! $payment) {
                abort(404, 'Payment could not be found.');
            }

            return response([
                'id' => $payment->id,
                'amount' => bcdiv($payment->amount, 100, 2),
                'amountRefunded' => bcdiv($payment->amount_refunded, 100, 2),
                'status' => $payment->status,
                'currency' => $payment->currency,
                'reference' => $payment->reference,
                'createdAt' => $payment->created_at,
                'chargeDate' => $payment->charge_date,
                'description' => $payment->description,
            ]);
        } catch (Exception $exception) {
            abort($exception->getCode() ?: 400, $exception->getMessage());
        }
    }

    public function merchantCallback(MerchantOAuthCallbackRequest $request)
    {
        $settings = LocationPaymentGatewaySettings::query()->where('public_token', $request->state)->firstOrFail();

        if (! $settings->locationPaymentGateway->isActive()) {
            abort(400, 'The payment gateway linked to this URL is no longer active.');
        }

        if (! $settings->locationPaymentGateway->location->is_active) {
            abort(400, 'The location linked to this URL is no longer active.');
        }

        try {
            $this->goCardlessService->completeMerchantOnBoardingFlow($settings, $request->safe()->collect()->get('code'));
        } catch (Exception $e) {
            abort(400, "Error: {$e->getMessage()}");
        }

        return redirect(config('octiv.web_app_url').'/settings/payment-gateways');
    }

    public function mandateCallback(MandateCallbackRequest $request)
    {
        $mandate = MandateGoCardless::query()
            ->where('status', MandateStatus::PENDING)
            ->where('redirect_flow_id', $request->get('redirect_flow_id'))
            ->first();

        if (! $mandate) {
            abort(404, "Mandate with redirect URL ID $request->redirect_flow_id not found.");
        }

        $tenantUser = TenantUser::query()
            ->where('user_id', $mandate->user_id)
            ->where('box_id', $mandate->locationPaymentGateway->location->tenant_id)
            ->withinActivePeriod()
            ->first();

        if (! $tenantUser) {
            abort(404, 'User membership could not be found.');
        }

        if (! in_array($tenantUser->user_status_id, [UserStatus::PENDING, UserStatus::ACTIVE])) {
            abort(400, 'Your account is not in a pending or active state. Please contact your location for assistance.');
        }

        $this->goCardlessService->completeUserOnBoardingFlow($mandate);

        if ($tenantUser->user_status_id === UserStatus::PENDING) {
            $tenantUser->user_status_id = UserStatus::ACTIVE;
            $tenantUser->save();
        }

        return response()->noContent(200);
    }

    public function events(EventsRequest $request)
    {
        ProcessEventsJob::dispatch($request->events)->onQueue('finance');

        return response()->noContent();
    }

    public function manuallyProcessEvent(ManuallyProcessEventRequest $request)
    {
        foreach ($request->input('event_ids') as $eventId) {
            $existingWebhookEvent = GoCardlessWebhookEvent::find($eventId);

            if (! $existingWebhookEvent) {
                continue;
            }

            $this->goCardlessService->processEvent($existingWebhookEvent);
        }
    }

    public function processPaymentForInvoice(ProcessPaymentForInvoiceRequest $request)
    {
        $invoice = UserInvoice::with(['userLocation.location', 'userLocation.user', 'leadMember'])->findOrFail($request->invoice_id);

        $settings = LocationPaymentGatewaySettings::with('locationPaymentGateway')
            ->whereRelation('locationPaymentGateway', 'payment_gateway_id', '=', PaymentGateway::GO_CARDLESS)
            ->whereRelation('locationPaymentGateway', 'context', '=', PaymentGatewayContext::AD_HOC)
            ->findOrFail($request->settings_id);

        if ($invoice->isPaid()) {
            abort(400, 'Invoice has already been marked as paid');
        }

        $locationUser = $invoice->userLocation;

        if (! $locationUser) {
            abort(400, 'Adhoc payments cannot be used for non-members');
        }

        if (! $settings->token) {
            abort(400, 'Facility settings are not valid.');
        }

        $successUrlSuffix = '/payment/'.$invoice->getKey().'?gid='.$settings->locationPaymentGateway->getKey();

        if (! $this->goCardlessService->isUserOnBoard($locationUser->user, $locationUser->location)) {
            $onboardingUrl = $this->goCardlessService->beginUserOnBoardingFlow($locationUser->user, $locationUser->location, $successUrlSuffix);

            return response()->json([
                'link' => $onboardingUrl,
            ]);
        }

        try {
            $this->goCardlessService->requestPaymentForInvoice($invoice, true);
        } catch (Exception $ex) {
            abort(400, $ex->getMessage());
        }

        return response()->json();
    }
}

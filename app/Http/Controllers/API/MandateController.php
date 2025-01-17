<?php

namespace App\Http\Controllers\API;

use App\Enums\MandateStatus;
use App\Enums\MandateType;
use App\Enums\UserDebitStatus;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mandate\GetAllMandatesForUserRequest;
use App\Http\Requests\Mandate\GetLatestMandateForUserRequest;
use App\Http\Requests\Mandate\ListMandatesRequest;
use App\Http\Requests\Mandate\SendOnBoardingLinkRequest;
use App\Http\Requests\Mandate\ShowMandateRequest;
use App\Http\Requests\Mandate\StoreMandateRequest;
use App\Http\Requests\Mandate\UpdateMandateRequest;
use App\Http\Resources\MandateResource;
use App\Http\Resources\TenantResource;
use App\Http\Resources\UserResource;
use App\Models\Mandate;
use App\Models\TenantUser;
use App\Services\MandateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\QueryParam;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class MandateController extends Controller
{
    public function __construct(public MandateService $mandateService)
    {
    }

    #[QueryParam('tenant_id', 'int', required: true)]
    #[QueryParam('location_id', 'int', required: false)]
    #[QueryParam('user_id', 'int', required: false)]
    #[QueryParam('status', 'string', required: false)]
    #[QueryParam('page', 'int', required: false)]
    #[QueryParam('per_page', 'int', required: false)]
    public function list(ListMandatesRequest $request): JsonResponse
    {
        $tenantUsers = QueryBuilder::for(TenantUser::class)
            ->select('user_to_box.*')
            ->join('users', 'users.user_id', 'user_to_box.user_id')
            ->where('user_to_box.user_type_id', '=', UserType::GYM_MEMBER)
            ->where('user_to_box.user_status_id', '=', UserStatus::ACTIVE)
            ->where(function (Builder $query) use ($request) {
                if ($request->input('filter.is_debit_order_members_only')) {
                    $query->where('user_to_box.user_debit_status_id', '=', UserDebitStatus::DEBIT_ORDER);
                } else {
                    $query->where('user_to_box.user_debit_status_id', '!=', UserDebitStatus::DEBIT_ORDER);
                }
            })
            ->allowedFilters([
                AllowedFilter::exact('tenant_id', 'box_id'),
                AllowedFilter::callback('location_id', function (Builder $query, $value) {
                    $query->join('user_to_facility', 'user_to_box.user_id', 'user_to_facility.user_id')
                        ->where('user_to_facility.box_facility_id', '=', $value)
                        ->whereDate('user_to_facility.end_date', '>', now());
                }),
                AllowedFilter::exact('user_id'),
            ])
            ->orderBy('users.name')
            ->orderBy('users.surname')
            ->with('mandates', function ($query) {
                $query->latest()->limit(1);
            })->get();

        $data = collect();
        $filterStatus = $request->input('filter.status');

        foreach ($tenantUsers as $tenantUser) {
            $latestMandate = $this->mandateService->getLatestMandate($tenantUser->user_id, $tenantUser->tenant_id, MandateType::SEPA);

            if ($filterStatus && (! $latestMandate instanceof Mandate || $latestMandate->status !== MandateStatus::from($filterStatus))) {
                continue;
            }

            $tenantUser->user->load('userTenant');

            $data->add([
                'user' => new UserResource($tenantUser->user),
                'box' => new TenantResource($tenantUser->tenant),
                'mandate' => [
                    'id' => $latestMandate?->getKey(),
                    'type' => $latestMandate?->status,
                    'reference' => $latestMandate?->reference,
                    'status' => $latestMandate?->status,
                    'sent_at' => $latestMandate?->sent_at?->toDateTimeString(),
                    'signed_at' => $latestMandate?->signed_at?->toDateTimeString(),
                    'cancelled_at' => $latestMandate?->cancelled_at?->toDateTimeString(),
                    'ip_address' => $latestMandate?->ip_address,
                    'created_at' => $latestMandate?->created_at?->toDateTimeString(),
                    'updated_at' => $latestMandate?->updated_at?->toDateTimeString(),
                ],
            ]);
        }

        return response()->json($data->paginate());
    }

    #[QueryParam('tenant_id', 'int', required: true)]
    public function getAllMandatesForUser(GetAllMandatesForUserRequest $request, int $userId)
    {
        return $this->mandateService->getAllMandatesForUserAndTenant($userId, $request->get('tenant_id'), MandateType::SEPA);
    }

    #[QueryParam('tenant_id', 'int', required: true)]
    public function getLatestMandateForUser(GetLatestMandateForUserRequest $request, int $userId)
    {
        $latestMandate = $this->mandateService->getLatestMandate($userId, $request->get('tenant_id'), MandateType::SEPA);

        if (! $latestMandate instanceof Mandate) {
            $latestMandate = $this->mandateService->store($request->get('tenant_id'), $userId);
        }

        return new MandateResource($latestMandate);
    }

    public function show(ShowMandateRequest $request, Mandate $mandate)
    {
        return new MandateResource($mandate);
    }

    #[BodyParam('tenant_id', 'int', required: true)]
    #[BodyParam('user_id', 'int', required: true)]
    public function store(StoreMandateRequest $request)
    {
        return new MandateResource($this->mandateService->store($request->get('tenant_id'), $request->get('user_id')));
    }

    #[BodyParam('tenant_id', 'int', required: true)]
    #[BodyParam('user_ids', 'int[]', required: true)]
    public function sendOnboardingLink(SendOnBoardingLinkRequest $request)
    {
        $this->mandateService->sendOnboardingLinkToListOfUsers($request->get('tenant_id'), $request->get('user_ids'));

        return response()->noContent();
    }

    #[BodyParam('status', 'int', required: true)]
    public function update(UpdateMandateRequest $request, Mandate $mandate)
    {
        return new MandateResource($this->mandateService->update($mandate, MandateStatus::from($request->get('status'))));
    }
}

<?php

namespace App\Http\Controllers\API\Reports;

use App\Helpers\JsonResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\GetLocationCategoryMetricsRequest;
use App\Http\Requests\Reports\GetLocationMetricsRequest;
use App\Http\Requests\Reports\GetMetricsForRegionsRequest;
use App\Http\Requests\Reports\GetUserMetricsRequest;
use App\Http\Requests\Reports\Report\CoachClassOrSessionDetailsRequest;
use App\Http\Requests\Reports\Report\CoachSessionsRequest;
use App\Http\Requests\Reports\Report\DiscoveryVitalityRequest;
use App\Http\Requests\Reports\Report\ExportRequest;
use App\Http\Requests\Reports\Report\PerformanceDetailsRequest;
use App\Http\Requests\Reports\Report\PerformanceRequest;
use App\Http\Requests\Reports\Report\PersonalBestsRequest;
use App\Http\Resources\UserTenantResource;
use App\Models\Location;
use App\Models\TenantUser;
use App\Models\WodCaptureExercise;
use App\Services\ReportsService;
use App\Services\TenantUserService;
use App\Services\WODCaptureExerciseService;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportsService $reportsService)
    {
    }

    public function coachesSessions(CoachSessionsRequest $request): JsonResponse
    {
        return response()->json($this->reportsService->getCoachesSessions($request));
    }

    public function coachesClassesOrSessionsDetails(CoachClassOrSessionDetailsRequest $request): JsonResponse
    {
        return response()->json($this->reportsService->getCoachesClassesOrSessionsDetails($request));
    }

    public function performance(PerformanceRequest $request): JsonResponse
    {
        return response()->json($this->reportsService->getPerformanceData($request));
    }

    public function performanceDetails(PerformanceDetailsRequest $request): JsonResponse
    {
        return response()->json($this->reportsService->getPerformanceDetailsData($request));
    }

    public function personalBests(PersonalBestsRequest $request)
    {
        $authUser = (new TenantUserService())->getCurrentUserTenantForTenant($request->user(), $request->input('filter.tenant_id'));

        if ($request->user()->isAdmin() || $authUser?->isMember()) {
            abort(403, 'Access denied.');
        }

        $startDate = date('Y-m-d', strtotime('-29 days'));
        $endDate = date('Y-m-d');

        $boxPersonalBests = (new WODCaptureExerciseService())->getPBWodCaptureExerciseForBox($authUser?->box_id, $startDate, $endDate);

        $dataToPaginate = [];

        $userTenants = TenantUser::query()
            ->where('box_id', $authUser?->box_id)
            ->whereIn('user_id', $boxPersonalBests->pluck('capture.user_id'))
            ->get();

        /** @var WodCaptureExercise $boxPersonalBest */
        foreach ($boxPersonalBests as $boxPersonalBest) {

            $userTenant = $userTenants->firstWhere('user_id', $boxPersonalBest->capture->user_id);

            $dataToPaginate[] = [
                'user' => new UserTenantResource($userTenant),
                'exercise' => $boxPersonalBest->exercise->name,
                'score' => $boxPersonalBest->score,
            ];
        }

        return JsonResource::collection(collect($dataToPaginate)->paginate());
    }

    public function export(ExportRequest $request): JsonResponse
    {
        $filePath = null;
        $location = $request->input('filter.location_id') ? Location::query()->find($request->input('filter.location_id')) : null;
        $startDate = $request->input('filter.start_date');
        $endDate = $request->input('filter.end_date');

        $authUser = (new TenantUserService())->getCurrentUserTenantForTenant($request->user(), $request->input('filter.tenant_id'));

        switch ($request->input('filter.report_type')) {
            case 'members':
                $filePath = $this->reportsService->exportMemberData($authUser->tenant, $location);
                break;
            case 'finances':
                $filePath = $this->reportsService->exportFinanceData($authUser->tenant, $location, $startDate, $endDate);
                break;
            case 'bookings':
                $filePath = $this->reportsService->exportBookingData($authUser->tenant, $location, $startDate, $endDate);
                break;
            case 'leads':
                $filePath = $this->reportsService->exportLeadData($authUser->tenant, $location, $startDate, $endDate);
                break;
        }

        return response()->json($filePath);
    }

    public function discoveryVitality(DiscoveryVitalityRequest $request): JsonResponse
    {
        $dataToPaginate = [];

        // Get a list of box facilities that are on vitality
        $vitalityLocations = $this->reportsService->getAllVitalityLocations();

        foreach ($vitalityLocations as $vitalityLocation) {
            $dataToPaginate[] = [
                'id' => $vitalityLocation->id,
                'name' => $vitalityLocation->name,
                'totalLinkedMembers' => $this->reportsService->getTotalLinkedMembersForLocation($vitalityLocation->id)?->count ?? 0,
                'totalCheckIns' => $this->reportsService->getTotalCheckInsForLocation($vitalityLocation->id)?->count ?? 0,
            ];
        }

        return response()->json(collect($dataToPaginate)->paginate());
    }

    public function getUserMetrics(GetUserMetricsRequest $request): JsonResponse
    {
        return response()->json($this->reportsService->getUserMetrics($request));
    }

    public function getTenantAndLocationMetrics(GetLocationMetricsRequest $request): JsonResponse
    {
        return response()->json($this->reportsService->getTenantAndLocationMetrics($request));
    }

    public function getMetricsForRegions(GetMetricsForRegionsRequest $request): JsonResponse
    {
        return response()->json($this->reportsService->getMetricsForRegions($request));
    }

    public function geLocationCategoryMetrics(GetLocationCategoryMetricsRequest $request): JsonResponse
    {
        return response()->json($this->reportsService->getMetricsForLocationCategories($request));
    }
}

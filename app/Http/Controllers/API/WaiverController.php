<?php

namespace App\Http\Controllers\API;

use App\Enums\WaiverStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\WaiverAssignRequest;
use App\Http\Requests\Waivers\BulkSendWaiversRequest;
use App\Http\Requests\Waivers\DownloadWaiverRequest;
use App\Http\Requests\Waivers\ReadWaiverRequest;
use App\Http\Requests\Waivers\SendWaiverRequest;
use App\Http\Requests\Waivers\SignWaiverRequest;
use App\Http\Resources\WaiverResource;
use App\Models\LeadWaivers;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\UserBankingDetail;
use App\Services\FinanceService;
use App\Services\LeadService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class WaiverController extends Controller
{
    public function show(ReadWaiverRequest $request, LeadWaivers $waiver)
    {
        return new WaiverResource($waiver->loadMissing('user'));
    }

    public function download(DownloadWaiverRequest $request)
    {
        $financeService = resolve(FinanceService::class);

        $user = User::findOrFail($request->user_id);

        $tenantUser = TenantUser::query()
            ->where('box_id', $request->tenant_id)
            ->where('user_id', $request->user_id)
            ->firstOrFail();

        $waiver = LeadWaivers::query()
            ->select('lead_waivers.*')
            ->join('box_facility', 'box_facility.box_facility_id', '=', 'lead_waivers.box_facility_id')
            ->with('location.tenant')
            ->where('user_id', $user->getAuthIdentifier())
            ->where('box_facility.box_id', $request->tenant_id)
            ->first();

        if (! $waiver) {
            abort(404, 'Member does not have a waiver assigned to them.');
        }

        $waiver->setRelation('user', $user);

        $bankingDetails = UserBankingDetail::query()
            ->with('bank', 'debitDay')
            ->where('user_id', $user->getAuthIdentifier())
            ->where('box_id', $request->tenant_id)
            ->where('is_active', true)
            ->first();

        $userPackages = $user->userPackages()
            ->active()
            ->whereRelation('package', 'box_id', '=', $request->tenant_id)
            ->with('package')
            ->get();

        $filePath = 'waivers/';
        $fileName = $user->name.'_waiver.pdf';

        Pdf::loadView('pdf.waiver', [
            'waiver' => $waiver,
            'bankingDetails' => $tenantUser->tenant->region->name === 'South Africa' ? $bankingDetails : null,
            'memberFee' => $financeService->calculateMemberFee($tenantUser->user, $tenantUser->tenant),
            'isUserOnSpecialRateOrDiscount' => $financeService->isUserOnSpecialRateOrDiscount($waiver->user, $request->tenant_id) ? 'Yes' : 'No',
            'userPackages' => $userPackages->isNotEmpty() ? $userPackages : null,
        ])->save($filePath.$fileName, 'tmp');

        return response()->json([
            'file' => Storage::disk('tmp')->temporaryUrl($filePath.$fileName, now()->addMinutes(5)),
        ]);
    }

    public function send(SendWaiverRequest $request)
    {
        $user = User::findOrFail($request->user_id);
        $tenant = Tenant::findOrFail($request->tenant_id);

        $waiver = LeadWaivers::query()
            ->with('location.tenant')
            ->where('user_id', $user->getAuthIdentifier())
            ->whereRelation('location', 'box_id', '=', $request->tenant_id)
            ->first();

        $tenantWaiver = $tenant->leadSettings->waiver;

        //send waiver
        (new LeadService())->sendWaiver($request->tenant_id, $tenantWaiver, $user, $waiver, $request->is_latest);

        return response()->noContent();
    }

    public function sign(SignWaiverRequest $request, LeadWaivers $waiver)
    {
        $waiver->update([
            'status' => WaiverStatus::SIGNED,
            'signed_on' => now(),
            'ip_address' => $request->ip(),
        ]);

        return response()->noContent();
    }

    public function bulkSend(BulkSendWaiversRequest $request)
    {
        $leadService = resolve(LeadService::class);

        $users = User::query()
            ->whereKey($request->user_ids)
            ->whereHas('tenantUser', function ($query) use ($request) {
                $query->active()->where('box_id', $request->tenant_id);
            })
            ->get();

        $tenant = Tenant::findOrFail($request->tenant_id);

        $waivers = LeadWaivers::query()
            ->with('location.tenant')
            ->whereIn('user_id', $users->modelKeys())
            ->whereRelation('location', 'box_id', '=', $request->tenant_id)
            ->get();

        $tenantWaiver = $tenant->leadSettings->waiver;

        foreach ($users as $user) {
            $waiver = $waivers->isNotEmpty()
                ? $waivers->where('user_id', $user->getAuthIdentifier())->first()
                : null;

            $leadService->sendWaiver($request->tenant_id, $tenantWaiver, $user, $waiver, $request->is_latest);
        }

        return response()->noContent();
    }

    public function assign(WaiverAssignRequest $request)
    {
        $waiver = LeadWaivers::find($request->input('waiver_id'));

        if (LeadWaivers::query()
            ->where('parent_id', $waiver->getKey())
            ->where('user_id', $request->input('user_id'))
            ->where('box_facility_id', $request->input('location_id'))
            ->exists()) {
            abort('400', 'Waiver already exists');
        }

        $assignWaiver = LeadWaivers::query()->create([
            'user_id' => $request->input('user_id'),
            'parent_id' => $waiver->getKey(),
            'digital' => $waiver->isDigital(),
            'digital_terms_and_conditions' => $waiver->digital_terms_and_conditions,
            'status' => WaiverStatus::SENT,
            'box_facility_id' => $request->input('location_id'),
        ]);

        return new WaiverResource($assignWaiver);

    }
}

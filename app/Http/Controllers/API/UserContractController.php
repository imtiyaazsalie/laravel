<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserContract\BulkGenerateUserContractRequest;
use App\Http\Requests\UserContract\CreateUserContractRequest;
use App\Http\Requests\UserContract\DeleteUserContractRequest;
use App\Http\Requests\UserContract\DownloadUserContractAttachmentRequest;
use App\Http\Requests\UserContract\DownloadUserContractRequest;
use App\Http\Requests\UserContract\ListUserContractRequest;
use App\Http\Requests\UserContract\ReadUserContractRequest;
use App\Http\Requests\UserContract\SendCurrentContractRequest;
use App\Http\Requests\UserContract\SendUserContractAttachmentRequest;
use App\Http\Requests\UserContract\SendUserContractRequest;
use App\Http\Requests\UserContract\SignUserContractRequest;
use App\Http\Requests\UserContract\UpdateUserContractRequest;
use App\Http\Resources\UserContractResource;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\UserContract;
use App\Services\CrmService;
use App\Services\TenantUserService;
use App\Services\UserContractService;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Knuckles\Scribe\Attributes\QueryParam;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class UserContractController extends Controller
{
    #[QueryParam('filter[tenant_id]', 'integer', required: false)]
    #[QueryParam('filter[user_id]', 'integer', required: false)]
    public function list(ListUserContractRequest $request)
    {
        $userId = Arr::get($request->filter, 'user_id');
        $tenantId = Arr::get($request->filter, 'tenant_id');

        $latestContract = UserContract::query()
            ->where('user_id', $userId)
            ->where('box_id', $tenantId)
            ->latest('user_contract_id')
            ->first();

        return UserContractResource::collection(
            QueryBuilder::for(UserContract::class)
                ->allowedFilters([
                    AllowedFilter::exact('tenant_id', 'box_id'),
                    AllowedFilter::exact('user_id', 'user_id'),
                ])
                ->orderBy('user_contract_id', 'DESC')
                ->_paginate()
                ->tap()
                ->map(function ($contract) use ($latestContract) {
                    $contract->is_current = (int) $latestContract->getKey() === $contract->getKey();

                    return $contract;
                })
        );
    }

    public function show(ReadUserContractRequest $request, UserContract $contract)
    {
        $latestContract = UserContract::query()
            ->where('user_id', $contract->user_id)
            ->where(function ($query) use ($contract) {

                $query->where('box_id', $contract->tenant_id)
                    ->orWhere(function ($query) use ($contract) {
                        $query->whereNotNull('box_facility_id')
                            ->whereRelation('location', 'box_id', '=', $contract->tenant_id);
                    });

            })->orderBy('user_contract_id', 'DESC')
            ->first();

        $contract->is_current = (int) $latestContract->getKey() === $contract->getKey();

        return new UserContractResource(
            $contract->loadMissing('user')
        );
    }

    public function store(User $user, CreateUserContractRequest $request)
    {
        $tenant = Tenant::find($request->tenant_id);

        UserContract::whereUserId($user->getAuthIdentifier())
            ->whereBoxId($tenant->getKey())
            ->where('starting_on', '<=', now())
            ->where('ending_on', '>=', now())
            ->update([
                'ending_on' => Carbon::parse($request->get('start_date'))->subDay(),
            ]);

        $userContract = new UserContract([
            'user_id' => $request->user_id,
            'box_id' => $request->tenant_id,
            'location_id' => $request->location_id,
            'starting_on' => $request->input('start_date'),
            'ending_on' => $request->input('end_date'),
            'contract_terms_and_conditions' => $tenant->contract_terms_and_conditions,
        ]);

        if ($request->file) {

            $fileName = md5(time().$userContract->getKey()).'_attachment.'.$request->file('file')->getClientOriginalExtension();

            $filePath = 'user-contracts/';

            $request->file('file')->storePubliclyAs($filePath, $fileName, ['disk' => 'private']);

            $userContract->fill([
                'file_path' => $filePath.$fileName,
                'file_mime' => $request->file('file')->getMimeType(),
                'file_name' => $fileName,
            ]);
        }

        $userContract->save();

        return new UserContractResource($userContract);
    }

    public function update(UserContract $contract, UpdateUserContractRequest $request)
    {
        $data = [
            'starting_on' => $request->start_date,
            'ending_on' => $request->end_date,
        ];

        if ($request->file) {

            $fileName = md5(time().$contract->getKey()).'_attachment.'.$request->file('file')->getClientOriginalExtension();

            $filePath = 'user-contracts/';

            $request->file('file')->storePubliclyAs($filePath, $fileName, ['disk' => 'private']);

            $data['file_path'] = $filePath.$fileName;
            $data['file_mime'] = $request->file('file')->getMimeType();
            $data['file_name'] = $fileName;
        }

        $contract->update($data);

        return new UserContractResource($contract);
    }

    public function sign(SignUserContractRequest $request, UserContract $contract)
    {
        $contract->update([
            'accepted_on' => now(),
            'accepted' => true,
            'ip_address' => request()->ip(),
        ]);

        return response()->noContent();
    }

    public function sendCurrent(SendCurrentContractRequest $request)
    {
        $tenant = Tenant::findOrFail($request->tenant_id);
        $userIds = $request->get('user_ids');

        DB::transaction(function () use ($userIds, $tenant) {
            $userContractService = resolve(UserContractService::class);

            foreach ($userIds as $userId) {
                $userTenant = (new TenantUserService())->getCurrentUserTenantForTenant($userId, $tenant);

                if (! $userTenant instanceof TenantUser) {
                    continue;
                }

                $userContract = $userTenant->userContract;

                if (! $userContract) {
                    continue;
                }

                $userContractService->sendUserContract($userContract);

                $userContract->update([
                    'sent' => true,
                    'sent_on' => now(),
                ]);
            }
        });
    }

    public function sendAttachment(SendUserContractAttachmentRequest $request, UserContract $contract)
    {
        if (! $contract->file_path) {
            return response()->errorMessage('This user contract does not have an attachment.', Response::HTTP_NOT_FOUND);
        }

        $filename = $contract->user->full_name.'_contract-document.pdf';

        (new CrmService)->createScheduledEmailForNotification(
            tenantOrLocation: $contract->tenant,
            context: 'sign_contract',
            recipient: $contract->user,
            data: [
                'member_name' => $contract->user->name,
                'member_surname' => $contract->user->surname,
            ],
            attach: [
                'disk' => 'private',
                'path' => $contract->file_path,
                'filename' => $filename,
            ]
        );

        return response()->noContent();
    }

    public function downloadAttachment(DownloadUserContractAttachmentRequest $request, UserContract $contract)
    {
        if (! $contract->file_path) {
            abort(404, 'Attachment does not exist.');
        }

        return response()->json([
            'file' => Storage::disk('private')->temporaryUrl($contract->file_path, now()->addMinute()),
        ]);
    }

    public function download(DownloadUserContractRequest $request, UserContract $contract)
    {
        $path = (new UserContractService())->generateContractPdf($contract);

        return response()->json([
            'file' => Storage::disk('tmp')->temporaryUrl($path, now()->addMinute()),
        ]);
    }

    public function send(SendUserContractRequest $request, UserContract $contract)
    {
        $contractSignLink = config('octiv.web_app_url').'/sign/contract/'.$contract->getKey();

        DB::transaction(function () use ($contract, $contractSignLink) {

            $contract->update([
                'sent' => true,
                'sent_on' => now(),
            ]);

            (new CrmService)->createScheduledEmailForNotification(
                tenantOrLocation: $contract->tenant,
                context: 'accept_terms',
                recipient: $contract->user,
                data: [
                    'member_name' => $contract->user->name,
                    'member_surname' => $contract->user->surname,
                    'link' => '<a href="'.$contractSignLink.'">'.$contractSignLink.'</a>',
                ]
            );

        });

        return response()->noContent();
    }

    public function bulkGenerate(BulkGenerateUserContractRequest $request)
    {
        $tenant = Tenant::findOrFail($request->tenant_id);
        $userIds = $request->get('user_ids');

        DB::transaction(function () use ($tenant, $userIds, $request) {
            $userContractService = resolve(UserContractService::class);

            foreach ($userIds as $userId) {
                $userTenant = (new TenantUserService())->getCurrentUserTenantForTenant($userId, $tenant);

                if (! $userTenant instanceof TenantUser) {
                    continue;
                }

                $user = $userTenant->user;
                $locationUser = (new TenantUserService())->getLocationUserByTenant($user, $tenant);

                if (! $locationUser) {
                    continue;
                }

                // Deactivate existing contracts
                UserContract::whereUserId($userId)
                    ->whereBoxId($request->tenant_id)
                    ->where('starting_on', '<=', now())
                    ->where('ending_on', '>=', now())
                    ->update([
                        'ending_on' => Carbon::parse($request->get('start_date'))->subDay(),
                    ]);

                // Create new contract
                $userContract = UserContract::create([
                    'user_id' => $userId,
                    'box_id' => $request->tenant_id,
                    'location_id' => $locationUser->location_id,
                    'starting_on' => $request->start_date,
                    'ending_on' => $request->end_date,
                    'contract_terms_and_conditions' => $tenant->contract_terms_and_conditions,
                ]);

                $userContractService->sendUserContract($userContract);
            }
        });

        return response()->noContent();
    }

    public function delete(DeleteUserContractRequest $request, UserContract $contract)
    {
        $contract->delete();

        return response()->noContent();
    }
}

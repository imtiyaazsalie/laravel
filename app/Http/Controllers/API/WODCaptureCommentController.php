<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\WodCapture\Comments\DeleteWodCaptureCommentRequest;
use App\Http\Requests\WodCapture\Comments\ListWodCaptureCommentsRequest;
use App\Http\Requests\WodCapture\Comments\ShowWodCaptureCommentRequest;
use App\Http\Requests\WodCapture\Comments\StoreWodCaptureCommentRequest;
use App\Http\Requests\WodCapture\Comments\UpdateWodCaptureCommentRequest;
use App\Http\Resources\WodCaptureCommentResource;
use App\Models\WodCapture;
use App\Models\WodCaptureComments;
use App\Services\CrmService;
use App\Services\TenantUserService;
use App\Services\WODCaptureCommentService;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class WODCaptureCommentController extends Controller
{
    public function __construct(private readonly WODCaptureCommentService $wodCaptureComment)
    {
    }

    public function store(StoreWodCaptureCommentRequest $request)
    {
        $user = auth()->user();
        $capture = WodCapture::query()->with(['wod', 'user'])->find($request->wod_capture_id);
        $comment = $this->wodCaptureComment->store($request->validated());

        if ($capture->user && $user->getAuthIdentifier() !== $capture->user->getAuthIdentifier()) {
            $crmService = resolve(CrmService::class);

            $crmService->createScheduledEmailForNotification(
                tenantOrLocation: $capture->wod->tenant,
                context: 'wod_comment',
                recipient: $capture->user,
                data: [
                    'wodcapture_user_name' => $capture->user->name,
                    'wodcapture_user_surname' => $capture->user->surname,
                    'member_name' => $user->name,
                    'member_surname' => $user->surname,
                    'wod_name' => $capture->wod->name,
                    'comment' => $comment->content,
                ]
            );

            $crmService->createScheduledPushNotification(
                title: 'New Comment',
                content: "$user->name commented on your whiteboard score for the workout {$capture->wod->name} on {$capture->wod->date->format('Y-m-d')}.",
                user: $capture->user,
                tenant: $capture->tenant,
                location: $capture->tenant ? (new TenantUserService())->getLocationUserByTenant($user, $capture->tenant)?->location : null,
            );

        }

        return new WodCaptureCommentResource($comment);
    }

    public function list(ListWodCaptureCommentsRequest $request)
    {
        return WodCaptureCommentResource::collection(
            QueryBuilder::for(WodCaptureComments::class)
                ->allowedFilters([
                    AllowedFilter::exact('wod_capture_id'),
                ])
                ->with('user')
                ->_paginate()
        );
    }

    public function show(ShowWodCaptureCommentRequest $request, WodCaptureComments $comment)
    {
        return new WodCaptureCommentResource(
            $comment->loadMissing('user')
        );
    }

    public function update(UpdateWodCaptureCommentRequest $request, WodCaptureComments $comment)
    {
        return new WodCaptureCommentResource(
            $this->wodCaptureComment->update($comment, $request->validated())
        );
    }

    public function delete(DeleteWodCaptureCommentRequest $request, WodCaptureComments $comment)
    {
        $comment->delete();

        return response()->noContent();
    }
}

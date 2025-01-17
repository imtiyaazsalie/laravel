<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\PushNotifications\DeleteAllPushNotificationsRequest;
use App\Http\Requests\PushNotifications\DeletePushNotificationRequest;
use App\Http\Requests\PushNotifications\ListPushNotificationsRequest;
use App\Http\Requests\PushNotifications\MarkAllPushNotificationsAsReadRequest;
use App\Http\Requests\PushNotifications\TogglePushNotificationReadStateRequest;
use App\Http\Requests\PushNotifications\UpdatePushNotificationTokenRequest;
use App\Http\Resources\PushNotificationResource;
use App\Models\PushNotification;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\Enums\SortDirection;

class PushNotificationController extends Controller
{
    public function list(ListPushNotificationsRequest $request): AnonymousResourceCollection
    {
        $pushNotifications = PushNotification::query()
            ->forUser(auth()->user()->getAuthIdentifier())
            ->orderBy('received_on', SortDirection::DESCENDING)
            ->_paginate();

        return PushNotificationResource::collection($pushNotifications);
    }

    public function toggleReadState(TogglePushNotificationReadStateRequest $request, PushNotification $pushNotification): Response
    {
        $pushNotification->toggleReadState();

        return response()->noContent();
    }

    public function markAllAsRead(MarkAllPushNotificationsAsReadRequest $request): Response
    {
        PushNotification::query()
            ->unread()
            ->forUser(auth()->user()->getAuthIdentifier())
            ->update([
                'is_read' => true,
            ]);

        return response()->noContent();
    }

    public function update(UpdatePushNotificationTokenRequest $request): Response
    {
        auth()->user()->update([
            'push_notification_token' => $request->validated('token'),
        ]);

        return response()->noContent();
    }

    public function delete(DeletePushNotificationRequest $request, PushNotification $pushNotification): Response
    {
        $pushNotification->delete();

        return response()->noContent();
    }

    public function deleteAll(DeleteAllPushNotificationsRequest $request): Response
    {
        PushNotification::query()
            ->forUser(auth()->user()->getAuthIdentifier())
            ->delete();

        return response()->noContent();
    }
}

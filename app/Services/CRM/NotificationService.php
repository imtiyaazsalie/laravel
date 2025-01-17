<?php

namespace App\Services\CRM;

use App\Http\Resources\CRM\NotificationResource;
use App\Models\Notifications;
use App\Models\NotificationUnsubscriptions;
use App\Models\User;
use Illuminate\Support\Arr;

class NotificationService
{
    public function list()
    {
        $tenantId = Arr::get(request()->filter, 'tenant_id');

        $types = ['editable', 'copy'];

        if (auth()->user()->isAdmin()) {
            $types = ['admin', 'editable', 'copy'];
        }

        $systemNotifications = Notifications::query()
            ->whereNotNull('system_type')
            ->whereNull('parent_id')
            ->where('status', '!=', 'deleted')
            ->whereIn('system_type', $types)
            ->orderBy('name')
            ->get();

        // Loop through all system notification check if a custom notification exists
        $systemNotifications->transform(function ($notification) use ($tenantId) {

            $customNotification = Notifications::query()
                ->whereNotNull('system_type')
                ->where('box_id', $tenantId)
                ->whereNotNull('parent_id')
                ->where('system_context', $notification->system_context)
                ->where('status', '!=', 'deleted')
                ->orderBy('name')
                ->first();

            if ($customNotification) {
                return $customNotification;
            }

            return $notification;
        });

        return $systemNotifications->paginate();
    }

    public function emailSubscriptions(User $user)
    {
        $notifications = Notifications::query()
            ->whereNotNull('system_type')
            ->whereNull('parent_id')
            ->where('status', '!=', 'deleted')
            ->where('unsubscribable', '=', true)
            ->orderBy('name')
            ->get();

        $unsubscriptions = NotificationUnsubscriptions::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->get();

        $notifications->transform(function ($notification) use ($unsubscriptions) {
            $notification->is_unsubscribed = $unsubscriptions->contains('notification_id', '=', $notification->getKey());

            return $notification;
        });

        return NotificationResource::collection($notifications);
    }

    public function show(Notifications $notification)
    {
        return new NotificationResource($notification);
    }

    public function update(Notifications $notification, $data)
    {
        $tenantId = Arr::get($data, 'tenant_id');

        // Try to get a customised notification for this box
        $custom = Notifications::query()
            ->whereNotNull('system_type')
            ->whereNotNull('parent_id')
            ->where('status', '!=', 'deleted')
            ->where('box_id', $tenantId)
            ->where('system_context', $notification->context)
            ->first();

        if (! $custom) {

            $custom = $notification->replicate();

            $custom->fill([
                ...$data,
                'parent_id' => $notification->getKey(),
                'type' => 'copy',
            ]);

            $custom->save();

        } else {
            $custom->fill($data);
            $custom->save();
        }

        return $custom;
    }
}

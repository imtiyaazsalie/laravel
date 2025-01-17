<?php

namespace App\Services;

use App\Models\User;
use App\Models\WodCapture;
use App\Models\WodCaptureLikes;

class WODCaptureLikeService
{
    public function store(WodCapture $capture, User $user): WodCaptureLikes
    {
        $like = new WodCaptureLikes();

        $like->fill([
            'user_id' => $user->getKey(),
            'wod_capture_id' => $capture->getKey(),
        ]);

        $like->save();

        if ($capture->user) {
            (new CrmService)->createScheduledPushNotification(
                title: 'New Like',
                content: "$user->name liked your whiteboard score for the workout {$capture->wod->name} on {$capture->wod->date->format('Y-m-d')}.",
                user: $capture->user,
                tenant: $capture->tenant,
                location: $capture->tenant ? (new TenantUserService())->getLocationUserByTenant($user, $capture->tenant)?->location : null,
            );
        }

        return $like;
    }

    public function storeOrDelete(WodCapture $capture, User $user): void
    {
        $like = $capture->likes()->where('user_id', '=', $user->getKey())->first();

        is_null($like) ? $this->store($capture, $user) : $like->delete();
    }
}

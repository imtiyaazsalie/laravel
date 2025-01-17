<?php

namespace App\Http\Controllers\API\CRM;

use App\Http\Controllers\Controller;
use App\Http\Requests\CRM\Notifications\ListNotificationsRequest;
use App\Http\Requests\CRM\Notifications\ListSubscriptionsRequest;
use App\Http\Requests\CRM\Notifications\ReadNotificationRequest;
use App\Http\Requests\CRM\Notifications\UnsubscribeRequest;
use App\Http\Requests\CRM\Notifications\UpdateNotificationRequest;
use App\Http\Resources\CRM\NotificationResource;
use App\Models\Notifications;
use App\Models\NotificationUnsubscriptions;
use App\Models\User;
use App\Services\CRM\NotificationService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notification
    ) {
        //
    }

    public function list(ListNotificationsRequest $request): AnonymousResourceCollection
    {
        return NotificationResource::collection(
            $this->notification->list()
        );
    }

    public function emailSubscriptions(ListSubscriptionsRequest $request, User $user)
    {
        return NotificationResource::collection(
            $this->notification->emailSubscriptions($user)
        );
    }

    public function show(ReadNotificationRequest $request, Notifications $notification)
    {
        return new NotificationResource($this->notification->show($notification));
    }

    public function update(Notifications $notification, UpdateNotificationRequest $request)
    {
        return new NotificationResource(
            $this->notification->update($notification, $request->validated())
        );
    }

    public function unsubscribe(UnsubscribeRequest $request, Notifications $notification)
    {

        $unsubscription = $notification->unsubscription()->where('user_id', $request->user_id)->first();

        if ($unsubscription) {

            $unsubscription->delete();

            return response()->json([
                'message' => 'Successfully subscribed to notification.',
            ]);
        }

        // dd($unsubscription);

        $unsubscription = NotificationUnsubscriptions::create([
            'user_id' => $request->user_id,
            'notification_id' => $notification->getKey(),
        ]);

        return response()->json([
            'message' => 'Successfully unsubscribed from notification.',
        ]);
    }
}

<?php

namespace App\Http\Controllers\API\CRM;

use App\Http\Controllers\Controller;
use App\Http\Requests\CRM\Broadcasts\ListBroadcastNotificationsRequest;
use App\Http\Requests\CRM\Broadcasts\ReadBroadcastNotificationRequest;
use App\Http\Requests\CRM\Broadcasts\UpdateBroadcastNotificationRequest;
use App\Http\Resources\CRM\BroadcastMessageResource;
use App\Models\BroadcastMessages;

class BroadcastController extends Controller
{
    public function list(ListBroadcastNotificationsRequest $request): BroadcastMessageResource
    {
        $broadcastMessage = BroadcastMessages::firstOrCreate(
            ['box_id' => request()->input('filter.tenant_id')],
            ['tenant_id' => request()->input('filter.tenant_id')],
        );

        return new BroadcastMessageResource($broadcastMessage);
    }

    public function show(ReadBroadcastNotificationRequest $request, BroadcastMessages $broadcastMessage): BroadcastMessageResource
    {
        return new BroadcastMessageResource($broadcastMessage);
    }

    public function update(UpdateBroadcastNotificationRequest $request, BroadcastMessages $broadcastMessage)
    {
        $broadcastMessage->forceFill($request->all());
        $broadcastMessage->save();

        return new BroadcastMessageResource($broadcastMessage);
    }
}

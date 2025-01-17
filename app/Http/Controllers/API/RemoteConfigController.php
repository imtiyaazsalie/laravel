<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\RemoteConfig\ReadRemoteConfigRequest;
use App\Http\Requests\RemoteConfig\UpdateRemoteConfigRequest;
use App\Http\Resources\RemoteConfigResource;
use App\Models\RemoteConfig;

class RemoteConfigController extends Controller
{
    public function show(ReadRemoteConfigRequest $request)
    {
        return new RemoteConfigResource(RemoteConfig::first());
    }

    public function update(UpdateRemoteConfigRequest $request)
    {
        $remoteConfig = RemoteConfig::firstOrCreate(['id' => 1], $request->validated());

        if ($remoteConfig->exists()) {
            $remoteConfig->update($request->validated());
        }

        return new RemoteConfigResource($remoteConfig);
    }
}

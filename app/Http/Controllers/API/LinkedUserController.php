<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\LinkedUser\DeleteLinkedUserRequest;
use App\Http\Requests\LinkedUser\indexLinkedUsersRequest;
use App\Http\Requests\LinkedUser\storeLinkedUserRequest;
use App\Http\Resources\LinkedUserResource;
use App\Models\LinkedUser;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\QueryBuilder;

class LinkedUserController extends Controller
{
    public function index(indexLinkedUsersRequest $request): AnonymousResourceCollection
    {
        $data = QueryBuilder::for(LinkedUser::class)
            ->with(['user', 'linkedUser'])
            ->where(function ($query) {
                $query->where('linked_user_id', '=', auth()->user()->getAuthIdentifier())
                    ->orWhere('user_id', '=', auth()->user()->getAuthIdentifier());
            })->_paginate();

        foreach ($data as $key => $linkedUser) {
            if (! $linkedUser->user || ! $linkedUser->linkedUser) {
                $data->forget($key);

            }
        }

        return LinkedUserResource::collection($data);
    }

    public function delete(DeleteLinkedUserRequest $request, LinkedUser $linkedUser): Response
    {
        $linkedUser->delete();

        return response()->noContent();
    }

    public function store(storeLinkedUserRequest $request): LinkedUserResource
    {
        $authUser = auth()->user();

        $user = User::query()
            ->where('email', $request->email)
            ->firstOrFail();

        if ($authUser->getAuthIdentifier() == $user->getAuthIdentifier()) {
            abort(400, 'You cannot link to yourself');
        }

        if (LinkedUser::query()->where('user_id', $authUser->getAuthIdentifier())->where('linked_user_id', $user->getAuthIdentifier())->exists()) {
            abort(400, 'Linking for selected user already exists.');
        }

        $linkedUser = LinkedUser::create([
            'linked_user_id' => $user->getAuthIdentifier(),
            'user_id' => $authUser->getAuthIdentifier(),
        ]);

        return new LinkedUserResource($linkedUser);
    }
}

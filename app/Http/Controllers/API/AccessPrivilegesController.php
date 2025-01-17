<?php

namespace App\Http\Controllers\API;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\AccessPrivileges\AccessPrivilegesListRequest;
use App\Http\Resources\AccessPrivilegeResource;
use App\Services\AccessPrivilegeService;

class AccessPrivilegesController extends Controller
{
    public function list(AccessPrivilegesListRequest $request)
    {
        $accessPrivileges = (new AccessPrivilegeService())->getAccessPrivilegesByUserType(
            UserType::from($request->input('filter.user_type_id'))
        );

        return AccessPrivilegeResource::collection($accessPrivileges);

    }
}

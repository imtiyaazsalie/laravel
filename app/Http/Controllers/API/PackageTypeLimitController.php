<?php

namespace App\Http\Controllers\API;

use App\Enums\PackageType;
use App\Http\Controllers\Controller;
use App\Http\Requests\PackageLimitType\ListPackageLimitTypesRequest;

class PackageTypeLimitController extends Controller
{
    public function list(ListPackageLimitTypesRequest $request)
    {
        return response()->json(
            PackageType::asArray()
        );
    }
}

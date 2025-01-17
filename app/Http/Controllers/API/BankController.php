<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Banks\ListBanksRequest;
use App\Http\Resources\BanksResource;
use App\Models\Bank;

class BankController extends Controller
{
    public function list(ListBanksRequest $request)
    {
        return BanksResource::collection(
            Bank::query()->with('country')->get()
        );
    }
}

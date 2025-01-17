<?php

namespace App\Http\Controllers\API;

use App\Enums\Affiliate;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AffiliateController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        return response()->json(
            Affiliate::asArray()
        );
    }
}

<?php

namespace App\Http\Controllers\API\Finance\Payfast;

use App\Http\Controllers\Controller;
use App\Services\PaymentGateways\PayFastService;

class PayfastController extends Controller
{
    public function processITN()
    {
        (new PayFastService())->processItn();

        return response()->noContent();
    }
}

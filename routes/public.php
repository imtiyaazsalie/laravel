<?php

use App\Http\Controllers\API\Finance\Netcash\NetCashController;
use App\Http\Controllers\API\Finance\Payfast\PayfastController;
use App\Http\Controllers\API\Finance\PaymentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix('payments')->group(function () {
    Route::get('invoice-for-payment/{invoice}', [PaymentController::class, 'getInvoiceForPayment']);
    Route::get('/payment-gateway/{locationPaymentGateway}', [PaymentController::class, 'getLocationPaymentGateway']);
});

Route::prefix('payfast')->group(function () {
    Route::post('/subscriptions/ping', [PayfastController::class, 'processITN'])->middleware('payfast.host');
});

Route::prefix('netcash')->group(function () {
    Route::post('/accept', [NetCashController::class, 'accept']);
    Route::post('/decline', [NetCashController::class, 'decline']);
    Route::post('/notify', [NetCashController::class, 'notify']);
});

Route::prefix('public/netcash')->group(function () {
    Route::post('/accept', [NetCashController::class, 'accept']);
    Route::post('/decline', [NetCashController::class, 'decline']);
    Route::post('/notify', [NetCashController::class, 'notify']);
});

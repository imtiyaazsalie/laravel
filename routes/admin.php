<?php

use App\Http\Controllers\Admin\MembershipController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\UserController;
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

//TODO: Admin/ReportsController routes.

Route::prefix('tenants')->middleware('auth:api')->group(function () {
    Route::get('/', [TenantController::class, 'list']);
    Route::post('/', [TenantController::class, 'store']);
    Route::put('/{tenant}/update-status', [TenantController::class, 'updateStatus']);
    Route::put('/{tenant}', [TenantController::class, 'update']);
    Route::delete('/{tenant}', [TenantController::class, 'delete']);
});

Route::prefix('users')->group(function () {
    Route::post('/', [UserController::class, 'store']);
    Route::get('/', [UserController::class, 'list']);
    Route::put('/{user}', [UserController::class, 'update']);
});

Route::prefix('memberships')->group(function () {
    Route::get('/', [MembershipController::class, 'list']);
    Route::get('/export-gym-members', [MembershipController::class, 'export']);
    Route::put('/payment-type', [MembershipController::class, 'updateDebitStatus']);
    Route::put('/update-status', [MembershipController::class, 'updateUserMembershipStatus']);
    Route::put('/{tenantUser}', [MembershipController::class, 'update']);
    Route::get('/{tenantUser}', [MembershipController::class, 'show']);
});

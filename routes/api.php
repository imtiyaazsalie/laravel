<?php

use App\Http\Controllers\API\AccessPrivilegesController;
use App\Http\Controllers\API\AddressController;
use App\Http\Controllers\API\AffiliateController;
use App\Http\Controllers\API\AmenityController;
use App\Http\Controllers\API\AttendanceRecordController;
use App\Http\Controllers\API\BankController;
use App\Http\Controllers\API\BodyMeasurementsController;
use App\Http\Controllers\API\BodyWeightController;
use App\Http\Controllers\API\BulkImportController;
use App\Http\Controllers\API\ClassBookingController;
use App\Http\Controllers\API\ClassBookingWaitingController;
use App\Http\Controllers\API\ClassController;
use App\Http\Controllers\API\ClassDatesController;
use App\Http\Controllers\API\ClassRecurringBookingsController;
use App\Http\Controllers\API\CoachRateController;
use App\Http\Controllers\API\CoronavirusQuestionnaireResultsController;
use App\Http\Controllers\API\CoronavirusVaccinationDetailsController;
use App\Http\Controllers\API\CRM\BroadcastController;
use App\Http\Controllers\API\CRM\MailerController;
use App\Http\Controllers\API\CRM\NotificationController;
use App\Http\Controllers\API\CRM\SettingsController as CrmSettingsController;
use App\Http\Controllers\API\CurrencyController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\DebitBatchController;
use App\Http\Controllers\API\DropInPackageController;
use App\Http\Controllers\API\ExerciseCategoryController;
use App\Http\Controllers\API\ExerciseController;
use App\Http\Controllers\API\ExportController;
use App\Http\Controllers\API\Finance\GoCardless\GoCardlessController;
use App\Http\Controllers\API\Finance\PaymentController;
use App\Http\Controllers\API\Finance\PaymentGatewayController;
use App\Http\Controllers\API\Finance\PaymentTokenController;
use App\Http\Controllers\API\Finance\Paystack\PaystackController;
use App\Http\Controllers\API\Finance\StatementController;
use App\Http\Controllers\API\Finance\StripeConnectController;
use App\Http\Controllers\API\Finance\StripeController;
use App\Http\Controllers\API\FinanceCreditNoteController;
use App\Http\Controllers\API\FinanceDiscountController;
use App\Http\Controllers\API\FinanceInvoiceItemTypeController;
use App\Http\Controllers\API\FinanceUserInvoiceController;
use App\Http\Controllers\API\HealthProviderController;
use App\Http\Controllers\API\InjuryController;
use App\Http\Controllers\API\LeadController;
use App\Http\Controllers\API\LeaderboardController;
use App\Http\Controllers\API\LinkedUserController;
use App\Http\Controllers\API\LocationCategoryController;
use App\Http\Controllers\API\LocationController;
use App\Http\Controllers\API\LocationPaymentGatewayController;
use App\Http\Controllers\API\LogBookController;
use App\Http\Controllers\API\LoginController;
use App\Http\Controllers\API\MandateController;
use App\Http\Controllers\API\MeasuringUnitController;
use App\Http\Controllers\API\OnHoldUserController;
use App\Http\Controllers\API\OperatingHoursController;
use App\Http\Controllers\API\OwnBenchmarkController;
use App\Http\Controllers\API\PackageController;
use App\Http\Controllers\API\PackageTypeLimitController;
use App\Http\Controllers\API\PasswordsController;
use App\Http\Controllers\API\PersonalWODController;
use App\Http\Controllers\API\POS\ReportController;
use App\Http\Controllers\API\POS\SaleController;
use App\Http\Controllers\API\POS\StockItemController;
use App\Http\Controllers\API\PrivacyPolicyController;
use App\Http\Controllers\API\ProgrammeController;
use App\Http\Controllers\API\ProgrammeMarketController;
use App\Http\Controllers\API\PushNotificationController;
use App\Http\Controllers\API\RefreshTokenController;
use App\Http\Controllers\API\RegionController;
use App\Http\Controllers\API\RemoteConfigController;
use App\Http\Controllers\API\Reports\AttendanceController;
use App\Http\Controllers\API\Reports\DashboardController as ReportsDashboardController;
use App\Http\Controllers\API\Reports\FinanceController;
use App\Http\Controllers\API\Reports\ReportController as ReportsReportController;
use App\Http\Controllers\API\ScheduledUserActionController;
use App\Http\Controllers\API\SettingsController as ControllersSettingsController;
use App\Http\Controllers\API\TagController;
use App\Http\Controllers\API\TaskController;
use App\Http\Controllers\API\TenantAffiliationController;
use App\Http\Controllers\API\TenantController;
use App\Http\Controllers\API\TermsAndConditionsController;
use App\Http\Controllers\API\TermsOfUseController;
use App\Http\Controllers\API\TimezoneController;
use App\Http\Controllers\API\UserBatchController;
use App\Http\Controllers\API\UserContractController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\UserPackagesController;
use App\Http\Controllers\API\UserTenantController;
use App\Http\Controllers\API\WaiverController;
use App\Http\Controllers\API\WhiteboardController;
use App\Http\Controllers\API\WODCaptureCommentController;
use App\Http\Controllers\API\WODCaptureController;
use App\Http\Controllers\API\WODCaptureExercisesController;
use App\Http\Controllers\API\WODCaptureLikeController;
use App\Http\Controllers\API\WODController;
use App\Http\Controllers\EnumController;
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

Route::prefix('addresses')->middleware(['auth:api'])->group(function () {
    Route::get('search', [AddressController::class, 'search']);
    Route::get('{address}', [AddressController::class, 'show'])->withoutMiddleware('auth:api');
    Route::post('{type}/{id}', [AddressController::class, 'store'])->withoutMiddleware(['auth:api']);
    Route::put('{address}', [AddressController::class, 'update']);
    Route::delete('{address}', [AddressController::class, 'destroy']);
});

Route::prefix('amenities')->middleware(['auth:api'])->group(function () {
    Route::get('', [AmenityController::class, 'index'])->withoutMiddleware('auth:api');
    Route::post('', [AmenityController::class, 'store'])->withoutMiddleware('auth:api');
    Route::get('{amenity}', [AmenityController::class, 'show']);
    Route::put('{amenity}', [AmenityController::class, 'update']);
    Route::delete('{amenity}', [AmenityController::class, 'destroy']);
});

Route::apiResource('operating-hours', OperatingHoursController::class);

Route::prefix('tags')->middleware(['auth:api'])->group(function () {
    Route::get('', [TagController::class, 'list']);
    Route::get('types', [TagController::class, 'types']);
    Route::post('', [TagController::class, 'create']);
    Route::put('{tag}', [TagController::class, 'update']);
    Route::delete('{tag}', [TagController::class, 'delete']);
});

Route::get('/access-privileges', [AccessPrivilegesController::class, 'list'])->middleware(['auth:api']);

Route::post('login', [LoginController::class, 'login']);

Route::post('token/refresh', [RefreshTokenController::class, 'refresh']);

Route::prefix('password')->group(function () {
    Route::post('reset', [PasswordsController::class, 'reset'])->name('password.reset');
    Route::post('forget', [PasswordsController::class, 'forget'])->name('password.email');

});

Route::prefix('settings')->middleware(['auth:api'])->group(function () {
    Route::get('/tenants/{tenant}/bookings', [ControllersSettingsController::class, 'showTenantBookingSettings']);
    Route::put('/tenants/{tenant}/bookings', [ControllersSettingsController::class, 'updateTenantBookingSettings']);
    Route::get('/tenants/{tenant}/contracts', [ControllersSettingsController::class, 'showTenantContractSettings']);
    Route::put('/tenants/{tenant}/contracts', [ControllersSettingsController::class, 'updateTenantContractSettings']);
    Route::get('/tenants/{tenant}/finances', [ControllersSettingsController::class, 'showTenantFinanceSettings']);
    Route::put('/tenants/{tenant}/finances', [ControllersSettingsController::class, 'updateTenantFinanceSettings']);
    Route::get('/tenants/{tenant}/finances/debit-orders', [ControllersSettingsController::class, 'showTenantFinanceDebitOrderSettings']);
    Route::put('/tenants/{tenant}/finances/debit-orders', [ControllersSettingsController::class, 'updateTenantFinanceDebitOrderSettings']);
    Route::get('/tenants/{tenant}/registrations', [ControllersSettingsController::class, 'showTenantRegistrationSettings']);
    Route::put('/tenants/{tenant}/registrations', [ControllersSettingsController::class, 'updateTenantRegistrationSettings']);
    Route::get('/tenants/{tenant}/waivers', [ControllersSettingsController::class, 'showTenantWaiverSettings']);
    Route::post('/tenants/{tenant}/waivers/{waiver}', [ControllersSettingsController::class, 'updateTenantWaiverSettings']);
    Route::get('/tenants/{tenant}/workouts', [ControllersSettingsController::class, 'showTenantWorkoutSettings']);
    Route::put('/tenants/{tenant}/workouts', [ControllersSettingsController::class, 'updateTenantWorkoutSettings']);
    Route::get('/tenants/{tenant}/widgets', [ControllersSettingsController::class, 'showTenantLocationWidgetSettings']);
    Route::put('/tenants/{tenant}/widgets', [ControllersSettingsController::class, 'updateTenantLocationWidgetSettings']);

    Route::get('/locations/{location}/finances/invoices', [ControllersSettingsController::class, 'showLocationFinanceInvoiceSettings']);
    Route::post('/locations/{location}/finances/invoices', [ControllersSettingsController::class, 'updateLocationFinanceInvoiceSettings']);
    Route::get('/locations/{location}/point-of-sale', [ControllersSettingsController::class, 'showLocationPointOfSaleSettings']);
    Route::put('/locations/{location}/point-of-sale', [ControllersSettingsController::class, 'updateLocationPointOfSaleSettings']);
});

Route::prefix('class-booking-waiting')->middleware(['auth:api'])->group(function () {
    Route::get('{booking}', [ClassBookingWaitingController::class, 'getById']);
    Route::put('{booking}/leave-waiting-list/', [ClassBookingWaitingController::class, 'leaveWaitingList']);
    Route::post('{booking}/send-athlete-message/', [ClassBookingWaitingController::class, 'sendMessageToAthleteOnWaitingList']);
    Route::post('', [ClassBookingWaitingController::class, 'store']);
});

Route::prefix('class-dates')->middleware(['auth:api'])->group(function () {
    Route::get('', [ClassDatesController::class, 'list'])
        ->withoutMiddleware('auth:api')
        ->middleware(['auth.guest', 'auth:api']);

    Route::get('{classDate}/class-booking-details/', [ClassDatesController::class, 'getClassBookingDetails']);

    Route::post('{classDate}/send-message-to-coach/', [ClassDatesController::class, 'sendMessageToCoach']);
    Route::post('{classDate}/send-message-to-class-bookings/', [ClassDatesController::class, 'sendMessageToBookedMembers']);
    Route::post('{classDate}/send-message-to-waiting-list/', [ClassDatesController::class, 'sendMessageToWaitingMembers']);

    Route::put('bulk-coach-change', [ClassDatesController::class, 'bulkCoachChange']);
    Route::put('bulk-limit-change', [ClassDatesController::class, 'bulkChangeLimit']);
    Route::delete('bulk-delete', [ClassDatesController::class, 'bulkDelete']);

    Route::get('{classDate}', [ClassDatesController::class, 'show'])->withoutMiddleware('auth:api');
    Route::put('{classDate}', [ClassDatesController::class, 'update']);
    Route::delete('{classDate}', [ClassDatesController::class, 'delete']);
});

Route::prefix('class-recurring-bookings')->middleware(['auth:api'])->group(function () {
    // unused routes
    //  "delete: /api/class-recurring-bookings/${id}",
    //  "get: /api/class-recurring-bookings/${id}",
    Route::put('{booking}/deactivate/', [ClassRecurringBookingsController::class, 'deactivate']);
    Route::put('{booking}/re-activate/', [ClassRecurringBookingsController::class, 'reactivate']);
    Route::get('', [ClassRecurringBookingsController::class, 'list']);
    Route::post('', [ClassRecurringBookingsController::class, 'store']);
    Route::put('{booking}', [ClassRecurringBookingsController::class, 'update']);
    Route::delete('{booking}', [ClassRecurringBookingsController::class, 'delete']);
});

Route::prefix('class-bookings')->middleware(['auth:api'])->group(function () {
    Route::get('my-class-bookings', [ClassBookingController::class, 'getMyClassBooking']);
    //unused routes
    // "get: /api/class-bookings/${id}",
    // "put: /api/class-bookings/status-update/${id}",
    Route::get('', [ClassBookingController::class, 'list']);
    Route::get('stats', [ClassBookingController::class, 'getClassBookingsStats']);

    Route::get('/{classBooking}', [ClassBookingController::class, 'show']);
    Route::get('package/{package}', [ClassBookingController::class, 'getClassBookingsByPackage']);
    Route::post('', [ClassBookingController::class, 'store']);
    Route::post('{booking}/send-athlete-message/', [ClassBookingController::class, 'sendMessageToAthlete']);
    Route::put('{booking}/cancel/', [ClassBookingController::class, 'cancelBooking']);
    Route::put('{booking}/check-in/', [ClassBookingController::class, 'checkIn']);
    Route::put('{booking}/no-show/', [ClassBookingController::class, 'noShow']);
});

Route::prefix('classes')->middleware(['auth:api'])->group(function () {
    Route::get('', [ClassController::class, 'list']);
    Route::put('{class}', [ClassController::class, 'update']);
    Route::get('{class}', [ClassController::class, 'show']);
    Route::post('', [ClassController::class, 'store']);
    Route::delete('{class}', [ClassController::class, 'delete']);
});

Route::prefix('whiteboard')->middleware(['auth:api'])->group(function () {
    Route::get('', [WhiteboardController::class, 'get']);
    Route::get('results', [WhiteboardController::class, 'results']);
});

Route::prefix('crm-mailers')->middleware(['auth:api'])->group(function () {
    Route::get('', [MailerController::class, 'list']);
    Route::get('{mailer}/copy', [MailerController::class, 'copy']);
    Route::get('{mailer}/recipients', [MailerController::class, 'recipients']);
    Route::get('{mailer}', [MailerController::class, 'show']);
    Route::post('', [MailerController::class, 'create']);
    Route::post('{mailer}', [MailerController::class, 'update']);
    Route::delete('{mailer}', [MailerController::class, 'delete']);
});

Route::prefix('crm-settings')->middleware(['auth:api'])->group(function () {
    Route::get('', [CrmSettingsController::class, 'show']);
    Route::post('/{setting}', [CrmSettingsController::class, 'update']);
});

Route::prefix('crm-notifications')->middleware(['auth:api'])->group(function () {
    Route::get('/', [NotificationController::class, 'list']);
    Route::put('/{notification}/unsubscribe', [NotificationController::class, 'unsubscribe']);
    Route::get('/{user}/email-subscriptions', [NotificationController::class, 'emailSubscriptions']);
    Route::get('/{notification}', [NotificationController::class, 'show']);
    Route::put('/{notification}', [NotificationController::class, 'update']);
});

Route::prefix('crm-broadcast-messages')->middleware(['auth:api'])->group(function () {
    Route::get('/', [BroadcastController::class, 'list']);
    Route::get('{broadcastMessage}', [BroadcastController::class, 'show']);
    Route::put('{broadcastMessage}', [BroadcastController::class, 'update']);
});

Route::get('/banks', [BankController::class, 'list'])->withoutMiddleware(['auth:api']);

Route::prefix('body-measurements')->middleware(['auth:api'])->group(function () {
    Route::post('/', [BodyMeasurementsController::class, 'store']);
    Route::get('/', [BodyMeasurementsController::class, 'list']);
    Route::get('/{bodyMeasurement}', [BodyMeasurementsController::class, 'show']);
    Route::put('/{bodyMeasurement}', [BodyMeasurementsController::class, 'update']);
    Route::delete('/{bodyMeasurement}', [BodyMeasurementsController::class, 'delete']);
});

Route::prefix('body-weights')->middleware(['auth:api'])->group(function () {
    Route::post('/', [BodyWeightController::class, 'store']);
    Route::get('/', [BodyWeightController::class, 'list']);
    Route::get('/{bodyWeight}', [BodyWeightController::class, 'show']);
    Route::put('/{bodyWeight}', [BodyWeightController::class, 'update']);
    Route::delete('/{bodyWeight}', [BodyWeightController::class, 'delete']);
});

Route::prefix('tenants')->middleware(['auth:api'])->group(function () {
    Route::get('/{token}/token', [TenantController::class, 'getByPublicToken'])->withoutMiddleware(['auth:api']);
    Route::get('/', [TenantController::class, 'list']);
    Route::get('/{tenant}', [TenantController::class, 'show']);
    Route::post('/', [TenantController::class, 'create']);
    Route::put('/{tenant}', [TenantController::class, 'update']);
    Route::put('/', [TenantController::class, 'bulkAssignCategory']);
});

Route::prefix('location-categories')->middleware(['auth:api'])->group(function () {
    Route::get('/', [LocationCategoryController::class, 'list']);
    Route::post('/', [LocationCategoryController::class, 'store']);
    Route::put('/{locationCategory}', [LocationCategoryController::class, 'update']);
    Route::delete('/{locationCategory}', [LocationCategoryController::class, 'delete']);
});

Route::prefix('locations')->middleware(['auth:api'])->group(function () {
    Route::get('/{location}/export-parameters', [LocationController::class, 'getExportParameters']);
    Route::get('/{location}/attendance-code', [LocationController::class, 'getAttendanceCode']);
    Route::put('/{location}/export-parameters', [LocationController::class, 'updateExportParameter']);
    Route::post('/process-payments', [LocationController::class, 'processPayments']);

    Route::get('/', [LocationController::class, 'list'])->withoutMiddleware(['auth:api']);
    Route::post('/', [LocationController::class, 'store']);
    Route::get('/{location}', [LocationController::class, 'show'])->withoutMiddleware(['auth:api']);
    Route::post('/{location}', [LocationController::class, 'update']);
    Route::delete('/{location}', [LocationController::class, 'delete']);

    Route::prefix('addresses')->middleware(['auth:api'])->group(function () {
        Route::post('{location}', [LocationController::class, 'addAddress']);
        Route::get('{location}', [LocationController::class, 'showAddress'])->withoutMiddleware(['auth:api']);
        Route::put('{address}', [LocationController::class, 'updateAddress']);
        Route::delete('{address}', [LocationController::class, 'deleteAddress']);
    });
});

Route::prefix('finances')->middleware(['auth:api'])->group(function () {

    // Route::prefix('location-invoices')->group(function () {
    //     Route::get('/', [LocationInvoiceController::class, 'list']);
    //     Route::post('/', [LocationInvoiceController::class, 'store']);
    //     Route::get('/export', [LocationInvoiceController::class, 'export']);
    //     Route::get('/{invoice}', [LocationInvoiceController::class, 'show']);
    //     Route::put('/{invoice}', [LocationInvoiceController::class, 'update']);
    //     Route::delete('/{invoice}', [LocationInvoiceController::class, 'delete']);
    // });

    Route::prefix('discounts')->group(function () {
        Route::get('/', [FinanceDiscountController::class, 'list']);
        Route::post('/', [FinanceDiscountController::class, 'store']);
        Route::get('/{financeDiscount}', [FinanceDiscountController::class, 'show']);
        Route::put('/{financeDiscount}', [FinanceDiscountController::class, 'update']);
        Route::delete('/{financeDiscount}', [FinanceDiscountController::class, 'delete']);
    });

    Route::prefix('credit-notes')->group(function () {
        Route::get('/export', [FinanceCreditNoteController::class, 'export']);
        Route::get('/', [FinanceCreditNoteController::class, 'list']);
        Route::get('/{userInvoice}', [FinanceCreditNoteController::class, 'show']);
        Route::post('', [FinanceCreditNoteController::class, 'store']);
        Route::put('/{userInvoice}', [FinanceCreditNoteController::class, 'update']);
    });

    Route::prefix('user-invoices')->group(function () {
        Route::get('/', [FinanceUserInvoiceController::class, 'list']);
        Route::get('/{userInvoice}/download', [FinanceUserInvoiceController::class, 'download']);
        Route::get('/export', [FinanceUserInvoiceController::class, 'export']);
        Route::get('/{userInvoice}/send', [FinanceUserInvoiceController::class, 'send']);
        Route::post('/', [FinanceUserInvoiceController::class, 'store']);
        Route::put('/{userInvoice}/reverse', [FinanceUserInvoiceController::class, 'reverse']);
        Route::post('/bulk-reverse', [FinanceUserInvoiceController::class, 'bulkReverse']);
        Route::post('/bulk-create', [FinanceUserInvoiceController::class, 'bulkCreate']);
        Route::post('/generate', [FinanceUserInvoiceController::class, 'generate']);
        Route::put('/{userInvoice}/release-top-up', [FinanceUserInvoiceController::class, 'releaseTopUp']);
        Route::post('/send-payment-request', [FinanceUserInvoiceController::class, 'sendPaymentRequests']);
        Route::post('/bulk-send-user', [FinanceUserInvoiceController::class, 'bulkSendByUser']);
        Route::post('/bulk-send-invoice', [FinanceUserInvoiceController::class, 'bulkSendByInvoice']);
        Route::get('/user/{user}', [FinanceUserInvoiceController::class, 'getInvoicesForUser']);
        Route::get('/{userInvoice}', [FinanceUserInvoiceController::class, 'show']);
        Route::put('/{userInvoice}', [FinanceUserInvoiceController::class, 'update']);
        Route::put('/{userInvoice}/add-to-debit-batch', [FinanceUserInvoiceController::class, 'addToDebitBatch']);
        Route::delete('/{userInvoice}', [FinanceUserInvoiceController::class, 'delete']);
    });

    Route::prefix('invoice-item-types')->group(function () {
        Route::get('/', [FinanceInvoiceItemTypeController::class, 'list']);
        Route::post('/', [FinanceInvoiceItemTypeController::class, 'store']);
        Route::get('/{invoiceItemType}', [FinanceInvoiceItemTypeController::class, 'show']);
        Route::put('/{invoiceItemType}', [FinanceInvoiceItemTypeController::class, 'update']);
        Route::delete('/{invoiceItemType}', [FinanceInvoiceItemTypeController::class, 'delete']);
    });

    Route::prefix('statements')->group(function () {
        Route::get('', [StatementController::class, 'list']);
        Route::get('print', [StatementController::class, 'print']);
        Route::get('download', [StatementController::class, 'download']);
        Route::post('send', [StatementController::class, 'send']);
        Route::post('bulk-send', [StatementController::class, 'bulkSend']);
    });

    Route::prefix('go-cardless')->group(function () {
        Route::prefix('mandates')->group(function () {
            Route::get('', [GoCardlessController::class, 'listDebitOrderMembers']);
            Route::get('on-boarding-link', [GoCardlessController::class, 'getOnboardingLink'])->withoutMiddleware('auth:api');
            Route::put('send-on-boarding-link', [GoCardlessController::class, 'sendOnBoardingLink']);
            Route::put('import-mandates/{location}', [GoCardlessController::class, 'importMandates'])->middleware(['admin']);
            Route::delete('/{mandate}', [GoCardlessController::class, 'deleteMandate']);
        });

        Route::prefix('payments')->group(function () {
            Route::get('/payouts', [GoCardlessController::class, 'getPayouts']);
            Route::get('/payouts-items', [GoCardlessController::class, 'getPayoutsItems']);
            Route::get('/{id}', [GoCardlessController::class, 'getPaymentById']);
        });

        // public routes
        Route::withoutMiddleware(['auth:api'])->group(function () {
            Route::get('merchant-oauth-callback', [GoCardlessController::class, 'merchantCallback']);
            Route::post('mandate-callback', [GoCardlessController::class, 'mandateCallback']);
            Route::post('events', [GoCardlessController::class, 'events']);
            Route::post('process-payment', [GoCardlessController::class, 'processPaymentForInvoice']);
        });

        Route::post('manually-process-event', [GoCardlessController::class, 'manuallyProcessEvent']);
    });

    Route::prefix('stripe')->group(function () {
        Route::withoutMiddleware(['auth:api'])->group(function () {
            Route::post('create-checkout-session', [StripeController::class, 'createCheckoutSession']);
        });
    });

    Route::prefix('stripe-connect')->group(function () {
        Route::get('/debit-order-members', [StripeConnectController::class, 'listDebitOrderMembers']);

        Route::post('/{location}/account-session', [StripeConnectController::class, 'postAccountSession']);

        Route::post('/{location}/account-link-url', [StripeConnectController::class, 'postAccountLinkUrl']);
        Route::get('/{location}/refresh-account-link-url', [StripeConnectController::class, 'getRefreshAccountLinkUrl']);

        Route::get('/{location}/account-capabilities', [StripeConnectController::class, 'getAccountCapabilities']);
        Route::put('/{location}/account-capabilities', [StripeConnectController::class, 'putAccountCapabilities']);

        Route::delete('/{location}/account', [StripeConnectController::class, 'deleteAccount']);

        Route::post('/setup-intent', [StripeConnectController::class, 'setupIntent']);
        Route::put('/setup-intent/send-link', [StripeConnectController::class, 'sendSetupIntentLink']);
        Route::delete('/setup-intent/{financePaymentToken:token}', [StripeConnectController::class, 'deleteSetupIntent']);

        Route::post('/attach-payment-method', [StripeConnectController::class, 'attachPaymentMethod']);
        Route::get('/payment-methods', [StripeConnectController::class, 'paymentMethods']);
        Route::get('/platform-card-payments/{user}', [StripeConnectController::class, 'listUserPlatformCards']);

        Route::post('/location-setup-intent', [StripeConnectController::class, 'createLocationSetupIntent']);

        // public routes
        Route::withoutMiddleware(['auth:api'])->group(function () {
            Route::post('/debit-order-setup-intent', [StripeConnectController::class, 'postDebitOrderSetupIntent']);
            Route::post('create-checkout-session', [StripeConnectController::class, 'createCheckoutSession']);

            // Platform and connected account webhooks
            Route::post('webhook', [StripeConnectController::class, 'webhook']);
            Route::post('connected-webhook', [StripeConnectController::class, 'connectedWebhook']);
        });
    });

    Route::prefix('payment-tokens')->group(function () {
        Route::get('/locations', [PaymentTokenController::class, 'listLocationPaymentTokens']);

        Route::delete('/{financePaymentToken}', [PaymentTokenController::class, 'delete']);
    });
});

// Has to be like this so because webhooks point to this url
Route::prefix('public/stripe')->withoutMiddleware(['auth:api'])->group(function () {
    Route::post('events/{token}', [StripeController::class, 'events']);
});

Route::prefix('coach-rates')->middleware(['auth:api'])->group(function () {
    Route::get('/', [CoachRateController::class, 'list']);
    Route::post('/', [CoachRateController::class, 'store']);
    Route::delete('/{rate}', [CoachRateController::class, 'delete']);
});

Route::prefix('leaderboard')->middleware(['auth:api'])->group(function () {
    Route::get('/', [LeaderboardController::class, 'list']);
    Route::delete('/{leaderboard}', [LeaderboardController::class, 'delete']);
});

Route::prefix('tenant-settings')->middleware(['auth:api'])->group(function () {
    Route::get('/{tenant}', [ControllersSettingsController::class, 'showTenantSettings']);
    Route::post('/{tenant}', [ControllersSettingsController::class, 'updateTenantSettings']);
    Route::get('/{tenant}/coronavirus', [ControllersSettingsController::class, 'showTenantCoronavirusSettings']);
    Route::put('/{tenant}/coronavirus', [ControllersSettingsController::class, 'updateTenantCoronavirusSettings']);
});

Route::prefix('currencies')->middleware('auth:api')->group(function () {
    Route::get('/', [CurrencyController::class, 'list']);
    Route::get('/{currency}', [CurrencyController::class, 'show']);
});

Route::prefix('drop-in-packages')->middleware(['auth:api'])->group(function () {
    Route::post('/', [DropInPackageController::class, 'store']);
    Route::get('/', [DropInPackageController::class, 'list']);
    Route::get('/settings', [DropInPackageController::class, 'getSettings']);
    Route::put('/settings', [DropInPackageController::class, 'updateSettings']);
    Route::get('/{dropInPackage}', [DropInPackageController::class, 'show']);
    Route::put('/{dropInPackage}', [DropInPackageController::class, 'update']);
    Route::put('/activate-deactivate/{dropInPackage}', [DropInPackageController::class, 'toggleStatus']);
    Route::get('/classes/{dropInPackage}', [DropInPackageController::class, 'getDropInClasses']);
    Route::put('/classes/{dropInPackage}', [DropInPackageController::class, 'updateDropInClasses']);
});

Route::prefix('exercises')->middleware(['auth:api'])->group(function () {
    Route::post('/', [ExerciseController::class, 'store']);
    Route::get('/', [ExerciseController::class, 'list']);
    Route::get('/{exercise}', [ExerciseController::class, 'show']);
    Route::put('/{exercise}', [ExerciseController::class, 'update']);
});

Route::prefix('exercise-categories')->middleware('auth:api')->group(function () {
    Route::post('/', [ExerciseCategoryController::class, 'store']);
    Route::get('/', [ExerciseCategoryController::class, 'list']);
    Route::put('/bulk', [ExerciseCategoryController::class, 'updateBulk']);
    Route::get('/{exerciseCategory}', [ExerciseCategoryController::class, 'show']);
    Route::put('/{exerciseCategory}', [ExerciseCategoryController::class, 'update']);
    Route::put('/{exerciseCategory}/assign-to-exercises', [ExerciseCategoryController::class, 'assignToExercises']);
});

Route::prefix('health-providers')->middleware(['auth:api'])->group(function () {
    Route::get('/', [HealthProviderController::class, 'list']);
    Route::post('/', [HealthProviderController::class, 'store']);
    Route::put('/{healthCareProvider}', [HealthProviderController::class, 'update']);
    Route::delete('/{healthCareProvider}', [HealthProviderController::class, 'delete']);
});

Route::prefix('injuries')->middleware(['auth:api'])->group(function () {
    Route::get('/', [InjuryController::class, 'list']);
    Route::get('/{injury}', [InjuryController::class, 'show']);
    Route::post('/', [InjuryController::class, 'store']);
    Route::put('/{injury}/healed', [InjuryController::class, 'markAsHealed']);
    Route::put('/{injury}', [InjuryController::class, 'update']);
    Route::delete('/{injury}', [InjuryController::class, 'delete']);
});

Route::prefix('measuring-units')->middleware(['auth:api'])->group(function () {
    Route::get('/', [MeasuringUnitController::class, 'list']);
    Route::post('/', [MeasuringUnitController::class, 'store']);
    Route::put('{measurementUnit}/activate-deactivate', [MeasuringUnitController::class, 'toggleStatus']);
    Route::put('/{measurementUnit}', [MeasuringUnitController::class, 'update']);
    Route::delete('/{measurementUnit}', [MeasuringUnitController::class, 'delete']);
});

Route::prefix('privacy-policy')->group(function () {
    Route::get('/', [PrivacyPolicyController::class, 'show']);
    Route::post('/', [PrivacyPolicyController::class, 'store']);
});

Route::prefix('programmes')->middleware(['auth:api'])->group(function () {
    Route::post('/', [ProgrammeController::class, 'store']);
    Route::get('/', [ProgrammeController::class, 'list'])->withoutMiddleware('auth:api');
    Route::put('/{programme}', [ProgrammeController::class, 'update']);
    Route::put('/{programme}/activate-deactivate', [ProgrammeController::class, 'toggleStatus']);

    Route::prefix('marketplace')->group(function () {
        Route::get('/', [ProgrammeMarketController::class, 'index']);
        Route::post('', [ProgrammeMarketController::class, 'store']);
    });

});

Route::prefix('push-notifications')->middleware('auth:api')->group(function () {
    Route::get('/', [PushNotificationController::class, 'list']);
    Route::put('{pushNotification}/read-unread/', [PushNotificationController::class, 'toggleReadState']);
    Route::put('/mark-all-as-read', [PushNotificationController::class, 'markAllAsRead']);
    Route::put('/token', [PushNotificationController::class, 'update']);
    Route::delete('/delete-all', [PushNotificationController::class, 'deleteAll']);
    Route::delete('/{pushNotification}', [PushNotificationController::class, 'delete']);
});

Route::prefix('regions')->middleware(['auth:api'])->group(function () {
    Route::get('/', [RegionController::class, 'list']);
    Route::post('/', [RegionController::class, 'store']);
    Route::put('/{region}', [RegionController::class, 'update']);
    Route::delete('/{region}', [RegionController::class, 'delete']);
    Route::put('/activate-deactivate/{region}', [RegionController::class, 'toggleStatus']);
});

Route::prefix('remote-config')->group(function () {
    Route::get('/v2', [RemoteConfigController::class, 'show']);
    Route::put('/', [RemoteConfigController::class, 'update']);
    Route::put('/v2', [RemoteConfigController::class, 'update']);
});

Route::prefix('timezones')->middleware('auth:api')->group(function () {
    Route::get('/', [TimezoneController::class, 'list']);
    Route::get('/{timezone}', [TimezoneController::class, 'show']);
});

Route::prefix('affiliates')->middleware(['auth:api'])->group(function () {
    Route::get('/', [AffiliateController::class, '__invoke']);

    Route::post('link', [TenantAffiliationController::class, 'link'])->middleware('admin');
    Route::post('unlink', [TenantAffiliationController::class, 'unlink'])->middleware('admin');
});

Route::prefix('wods')->middleware(['auth:api'])->group(function () {
    Route::post('/import', [WODController::class, 'import']);
    Route::post('/', [WODController::class, 'store']);
    Route::get('/', [WODController::class, 'list'])->withoutMiddleware(['auth:api']);
    Route::get('/{wod}', [WODController::class, 'show']);
    Route::put('/{wod}', [WODController::class, 'update']);
    Route::delete('/{wod}', [WODController::class, 'delete']);
});

Route::prefix('personal-wods')->middleware(['auth:api'])->group(function () {
    Route::post('/', [PersonalWODController::class, 'store']);
    Route::get('/', [PersonalWODController::class, 'list']);
    Route::get('/{personalWod}', [PersonalWODController::class, 'show']);
    Route::put('/{personalWod}', [PersonalWODController::class, 'update']);
    Route::delete('/{personalWod}', [PersonalWODController::class, 'delete']);
});

Route::prefix('wod-captures')->middleware(['auth:api'])->group(function () {
    Route::get('/', [WODCaptureController::class, 'list']);
    Route::get('/{capture}', [WODCaptureController::class, 'show']);
});

Route::prefix('wod-capture-exercises')->middleware(['auth:api'])->group(function () {
    Route::post('/', [WODCaptureExercisesController::class, 'store']);
    Route::get('/', [WODCaptureExercisesController::class, 'list']);
    Route::get('/{wodCaptureExercise}', [WODCaptureExercisesController::class, 'show']);
    Route::put('/{wodCaptureExercise}/reject', [WODCaptureExercisesController::class, 'reject']);
    Route::put('/{wodCaptureExercise}/approve', [WODCaptureExercisesController::class, 'approve']);
    Route::put('/approve-all', [WODCaptureExercisesController::class, 'approveAll']);
    Route::put('/{wodCaptureExercise}', [WODCaptureExercisesController::class, 'update']);
});

Route::prefix('wod-capture-comments')->middleware(['auth:api'])->group(function () {
    Route::post('/', [WODCaptureCommentController::class, 'store']);
    Route::get('/', [WODCaptureCommentController::class, 'list']);
    Route::get('/{comment}', [WODCaptureCommentController::class, 'show']);
    Route::put('/{comment}', [WODCaptureCommentController::class, 'update']);
    Route::delete('/{comment}', [WODCaptureCommentController::class, 'delete']);
});

Route::prefix('wod-capture-likes')->middleware(['auth:api'])->group(function () {
    Route::put('/', [WODCaptureLikeController::class, 'storeOrDelete']);
    Route::get('/', [WODCaptureLikeController::class, 'list']);
});

Route::prefix('packages')->middleware(['auth:api'])->group(function () {
    Route::get('/{package}/classes', [PackageController::class, 'getPackageClasses']);
    Route::get('/', [PackageController::class, 'list'])->withoutMiddleware(['auth:api']);
    Route::post('/', [PackageController::class, 'store']);
    Route::put('/actions', [PackageController::class, 'actions']);
    Route::put('/{package}/classes', [PackageController::class, 'updatePackageClasses']);
    Route::get('/{package}/programmes', [PackageController::class, 'getProgrammes']);
    Route::put('/{package}/programmes', [PackageController::class, 'updateProgrammes']);
    Route::get('/{package}/locations', [PackageController::class, 'getActiveLocations'])->withoutMiddleware('auth:api');
    Route::put('/{package}/locations', [PackageController::class, 'updateActiveLocations']);
    Route::get('/{package}', [PackageController::class, 'show'])->withoutMiddleware(['auth:api']);
    Route::put('/{package}', [PackageController::class, 'update']);
});

Route::prefix('user-packages')->middleware(['auth:api'])->group(function () {
    Route::get('', [UserPackagesController::class, 'list']);
    Route::get('export', [UserPackagesController::class, 'export']);
    Route::get('{userPackage}', [UserPackagesController::class, 'show']);
    Route::post('buy', [UserPackagesController::class, 'buyPackage']);
    Route::post('/packages-available-for-class', [UserPackagesController::class, 'packagesAvailableForClass']);

    Route::put('{userPackage}/deactivate', [UserPackagesController::class, 'deactivate']);
    Route::put('{userPackage}/member-top-up', [UserPackagesController::class, 'memberTopUp']);
    Route::put('{userPackage}/coach-top-up', [UserPackagesController::class, 'coachTopUp']);

    // Route::get('{id}', [UserPackagesController::class, 'getById']);
    Route::post('', [UserPackagesController::class, 'store']);
    Route::put('{userPackage}', [UserPackagesController::class, 'update']);
    Route::delete('{userPackage}', [UserPackagesController::class, 'delete']);
});

Route::get('/package-limit-types', [PackageTypeLimitController::class, 'list'])->middleware('auth:api');

Route::prefix('own-benchmarks')->middleware(['auth:api'])->group(function () {
    Route::put('/approve-all', [OwnBenchmarkController::class, 'approveAll']);
    Route::put('/{ownBenchmark}/approve', [OwnBenchmarkController::class, 'approve']);
    Route::put('/{ownBenchmark}/reject', [OwnBenchmarkController::class, 'rejectBenchmark']);

    Route::get('/', [OwnBenchmarkController::class, 'list']);
    Route::post('/', [OwnBenchmarkController::class, 'store']);
    Route::get('/{ownBenchmark}', [OwnBenchmarkController::class, 'show']);
    Route::put('/{ownBenchmark}', [OwnBenchmarkController::class, 'update']);
    Route::delete('/{ownBenchmark}', [OwnBenchmarkController::class, 'delete']);
});

Route::prefix('waivers')->middleware(['auth:api'])->group(function () {
    Route::post('/assign', [WaiverController::class, 'assign']);
    Route::get('/download', [WaiverController::class, 'download']);
    Route::put('/send', [WaiverController::class, 'send']);
    Route::put('/bulk-send', [WaiverController::class, 'bulkSend']);
    Route::put('/{waiver}/sign', [WaiverController::class, 'sign']);
    Route::get('/{waiver}', [WaiverController::class, 'show']);
});

Route::prefix('logbook')->middleware(['auth:api'])->group(function () {
    Route::get('/', [LogBookController::class, 'list']);
});

Route::prefix('tasks')->middleware(['auth:api'])->group(function () {
    Route::get('/', [TaskController::class, 'list']);
    Route::get('/{task}', [TaskController::class, 'show']);
    Route::put('/{task}/completed-uncompleted', [TaskController::class, 'toggleComplete']);
    Route::delete('/{task}', [TaskController::class, 'delete']);
    Route::post('/', [TaskController::class, 'store']);
    Route::put('/{task}', [TaskController::class, 'update']);
});

Route::prefix('coronavirus-questionnaire-results')->middleware(['auth:api'])->group(function () {
    Route::get('/', [CoronavirusQuestionnaireResultsController::class, 'list']);
    Route::post('/', [CoronavirusQuestionnaireResultsController::class, 'store']);
    Route::put('/{result}', [CoronavirusQuestionnaireResultsController::class, 'update']);
    Route::delete('/{result}', [CoronavirusQuestionnaireResultsController::class, 'delete']);
});

Route::prefix('coronavirus-vaccination-details')->middleware(['auth:api'])->group(function () {
    Route::post('/', [CoronavirusVaccinationDetailsController::class, 'store']);
    Route::get('/{result}/download', [CoronavirusVaccinationDetailsController::class, 'download']);
    Route::get('/', [CoronavirusVaccinationDetailsController::class, 'list']);
    Route::post('/{result}', [CoronavirusVaccinationDetailsController::class, 'update']);
    Route::delete('/{result}', [CoronavirusVaccinationDetailsController::class, 'delete']);
});

Route::prefix('enums')->middleware(['auth:api'])->group(function () {
    Route::get('/', [EnumController::class, 'list']);
    Route::get('/values', [EnumController::class, 'show']);
});

Route::prefix('finances')->group(function () {
    Route::prefix('paystack')->group(function () {
        Route::get('/banks', [PaystackController::class, 'listBanks'])->middleware('auth:api');
        Route::get('/settlements/transactions', [PaystackController::class, 'getSettlementTransactions'])->middleware('auth:api');
        Route::get('/{location}/sub-account', [PaystackController::class, 'getSubAccount'])->middleware('auth:api');
        Route::get('/{location}/settlements', [PaystackController::class, 'fetchSettlements'])->middleware('auth:api');
        Route::post('/initialize-transaction', [PaystackController::class, 'initializeTransaction']);
        Route::get('/verify-transaction', [PaystackController::class, 'verifyTransaction']);
        Route::post('/events', [PaystackController::class, 'events'])->withoutMiddleware(['auth:api', 'auth', 'auth:web']);
        Route::put('/{location}/percentage-charge', [PaystackController::class, 'updatePercentageCharge'])->middleware('auth:api');
    });

    Route::prefix('payments')->middleware(['auth:api'])->group(function () {
        Route::get('/', [PaymentController::class, 'list']);
        Route::post('/bulk-create', [PaymentController::class, 'bulkCreate']);
        Route::post('/{invoice}', [PaymentController::class, 'store']);
        Route::get('/export', [PaymentController::class, 'export']);
        Route::delete('/{payment}', [PaymentController::class, 'delete']);
        Route::put('/{payment}', [PaymentController::class, 'update']);
    });

    Route::prefix('payment-gateways')->middleware(['auth:api'])->group(function () {
        // Route::get('/payfast-details', [PaymentGatewayController::class, 'getDetails']);
        Route::get('/payfast-details', [PaymentGatewayController::class, 'getPayfastDetails']);
        Route::delete('/payfast-details', [PaymentGatewayController::class, 'deletePayfastDetails']);
    });
});

Route::prefix('attendance-records')->middleware(['auth.guest:api'])->group(function () {
    Route::post('/check-out', [AttendanceRecordController::class, 'checkOut']);
    Route::post('/check-in', [AttendanceRecordController::class, 'checkIn']);
    Route::delete('/cancel-check-in/{classBooking}', [AttendanceRecordController::class, 'cancelCheckIn']);
});

Route::prefix('dashboard')->middleware(['auth:api'])->group(function () {
    Route::get('/schedule-stats', [DashboardController::class, 'getScheduleStats']);
    Route::get('/non-attendance', [DashboardController::class, 'getNonAttendance']);
    Route::get('/contracts-expiring', [DashboardController::class, 'getContractsExpiring']);
    Route::get('/new-sign-ups', [DashboardController::class, 'getNewSignUps']);
});

Route::prefix('user-contracts')->middleware(['auth:api'])->group(function () {
    Route::post('/', [UserContractController::class, 'store']);
    Route::post('/bulk-generate', [UserContractController::class, 'bulkGenerate']);
    Route::post('/send-current', [UserContractController::class, 'sendCurrent']);

    Route::get('/', [UserContractController::class, 'list']);
    Route::delete('/{contract}', [UserContractController::class, 'delete']);

    Route::post('/{contract}/send', [UserContractController::class, 'send']);

    Route::get('/{contract}/download', [UserContractController::class, 'download']);
    Route::post('/{contract}/send-attachment', [UserContractController::class, 'sendAttachment']);
    Route::get('/{contract}/download-attachment', [UserContractController::class, 'downloadAttachment']);
    Route::get('/{contract}', [UserContractController::class, 'show']);

    Route::put('/{contract}/sign', [UserContractController::class, 'sign']);
    Route::post('/{contract}', [UserContractController::class, 'update']);
});

Route::prefix('user-batches')->middleware(['auth:api'])->group(function () {
    Route::get('/', [UserBatchController::class, 'list']);
    Route::post('/', [UserBatchController::class, 'store']);
});

Route::prefix('location-payment-gateways')->middleware(['auth:api'])->group(function () {
    Route::get('/go-cardless-details', [LocationPaymentGatewayController::class, 'getGoCardlessDetails']);
    Route::get('/stripe-connect-details', [LocationPaymentGatewayController::class, 'getStripeConnectDetails']);
    Route::get('/debit-order/netcash-details', [LocationPaymentGatewayController::class, 'getDebitOrderNetcashDetails']);
    Route::put('/debit-order/{locationPaymentGateway}/netcash-details', [LocationPaymentGatewayController::class, 'putNetcashDebitOrderDetails']);
    Route::get('/debit-order/three-peaks-details', [LocationPaymentGatewayController::class, 'getDebitOrderThreePeaksDetails']);
    Route::put('/debit-order/{locationPaymentGateway}/three-peaks-details', [LocationPaymentGatewayController::class, 'putDebitOrderThreePeaksDetails']);
    Route::get('/debit-order/sepa-details', [LocationPaymentGatewayController::class, 'getDebitOrderSepaSettings']);
    Route::put('/debit-order/{locationPaymentGateway}/sepa-details', [LocationPaymentGatewayController::class, 'putDebitOrderSepaSettings']);
    Route::get('/adhoc-details', [LocationPaymentGatewayController::class, 'getAdHocSettings']);
    Route::post('/adhoc-details', [LocationPaymentGatewayController::class, 'postAdHocSettings']);
    Route::put('/adhoc-details/{locationPaymentGateway}', [LocationPaymentGatewayController::class, 'putAdhocSettings']);
});

Route::prefix('scheduled-user-actions')->middleware(['auth:api'])->group(function () {
    Route::get('/', [ScheduledUserActionController::class, 'list']);
    Route::delete('/{action}', [ScheduledUserActionController::class, 'delete']);
    Route::post('/', [ScheduledUserActionController::class, 'store']);
});

Route::prefix('point-of-sale')->middleware(['auth:api'])->group(function () {
    Route::prefix('stock-items')->middleware(['auth:api'])->group(function () {
        Route::post('/', [StockItemController::class, 'store']);
        Route::get('/', [StockItemController::class, 'list']);
        Route::get('/categories', [StockItemController::class, 'categories']);
        Route::get('/{stockItem}', [StockItemController::class, 'show']);
        Route::post('/{stockItem}', [StockItemController::class, 'update']);
        Route::delete('/{stockItem}', [StockItemController::class, 'delete']);
    });

    Route::prefix('sales')->middleware(['auth:api'])->group(function () {
        Route::post('/', [SaleController::class, 'store']);
        Route::get('/{sale}', [SaleController::class, 'show']);
        Route::put('/{sale}/cancel', [SaleController::class, 'cancel']);
    });

    Route::prefix('reports')->middleware(['auth:api'])->group(function () {
        Route::get('/', [ReportController::class, 'sales']);
        Route::get('/{stockItem}/stock-items-details/', [ReportController::class, 'getStockItemDetails']);
        Route::get('/sales-details/{sale}', [ReportController::class, 'getSaleDetails']);
        Route::get('/export-sales', [ReportController::class, 'exportSalesReport']);
        Route::get('/stock-items', [ReportController::class, 'stockItems']);
    });
});

Route::prefix('users')->middleware(['auth:api'])->group(function () {

    Route::post('duplicate-users/merge', [UserController::class, 'merge']);
    Route::get('duplicate-users', [UserController::class, 'duplicateUsers']);
    Route::get('duplicate-users/user-tenants', [UserController::class, 'duplicateUserTenants']);

    Route::post('/sign-up', [UserController::class, 'signUp'])->withoutMiddleware('auth:api');

    Route::get('/discovery-vitality/tokens/{user}', [UserController::class, 'getAccessToken'])
        ->middleware(['client', 'discovery'])
        ->withoutMiddleware(['auth:api']);

    Route::post('/discovery-vitality', [UserController::class, 'storeFromPartner'])
        ->middleware(['client', 'discovery'])
        ->withoutMiddleware(['auth:api']);

    Route::get('/validate', [UserController::class, 'validateUser'])->withoutMiddleware(['auth:api'])->middleware('throttle:auth');
    Route::get('me', [UserController::class, 'me']);
    Route::get('/', [UserController::class, 'list']);
    Route::get('/export', [UserController::class, 'export']);
    Route::post('/', [UserController::class, 'store'])->withoutMiddleware(['auth:api']);
    Route::get('/{user}', [UserController::class, 'show']);
    Route::post('/{user}', [UserController::class, 'update']);
    Route::get('/{user}/switch', [UserController::class, 'switch']);
    Route::get('/lead-types', [UserController::class, 'getLeadTypes']);

    Route::put('/change-password', [UserController::class, 'changePassword']);

});

Route::prefix('on-hold-users')->middleware(['auth:api'])->group(function () {
    Route::get('/', [OnHoldUserController::class, 'list']);
    Route::post('/', [OnHoldUserController::class, 'store']);
    Route::put('/{userOnHold}', [OnHoldUserController::class, 'update']);
    Route::put('/{userOnHold}/release', [OnHoldUserController::class, 'releaseMember']);
    Route::put('/{userOnHold}/cancel', [OnHoldUserController::class, 'cancel']);
});

Route::prefix('user-tenants')->middleware('auth:api')->group(function () {
    Route::get('/finances', [UserTenantController::class, 'finances']);
    Route::get('/contracts-waivers', [UserTenantController::class, 'contractsAndWaivers']);
    Route::get('/export', [UserTenantController::class, 'exportMembers']);
    Route::put('/approve-all-pending', [UserTenantController::class, 'approveAllPendingMemberships']);
    Route::put('/payment-type', [UserTenantController::class, 'updateDebitStatus']);
    Route::put('/programme', [UserTenantController::class, 'updateProgramme']);
    Route::put('/status', [UserTenantController::class, 'updateStatus']);
    Route::put('/high-risk', [UserTenantController::class, 'updateHighRisk']);
    Route::get('/{userTenant}', [UserTenantController::class, 'show']);
    Route::put('/{userTenant}', [UserTenantController::class, 'update']);
    Route::post('/', [UserTenantController::class, 'store']);
    Route::post('/send-welcome-email', [UserTenantController::class, 'sendWelcomeEmail']);
    Route::delete('/bulk', [UserTenantController::class, 'bulkDelete']);
    Route::delete('/{userTenant}', [UserTenantController::class, 'delete']);
    Route::get('/{userTenant}/payment-details', [UserTenantController::class, 'getMemberPaymentDetails']);
    Route::post('/{userTenant}/payment-details', [UserTenantController::class, 'updateMemberPaymentDetails']);
    Route::put('/{userTenant}/renew-upfront-payment', [UserTenantController::class, 'renewUpfrontPayment']);
});

Route::prefix('debit-batches')->middleware(['auth:api'])->group(function () {
    // Admin routes
    Route::middleware('admin')->group(function () {
        Route::get('/tenants', [DebitBatchController::class, 'getDebitBatchesForTenants']);

        Route::get('/three-peaks-submissions/{location}', [DebitBatchController::class, 'threePeaksListSubmissions']);
        Route::get('/{debitBatch}/three-peaks-submission-debits', [DebitBatchController::class, 'threePeaksAllDebitsInSubmission']);
        Route::get('/{debitBatch}/three-peaks-submission-info', [DebitBatchController::class, 'threePeaksSubmissionInformation']);
        Route::get('/{debitBatch}/three-peaks-validation-info', [DebitBatchController::class, 'threePeaksValidateInformation']);
        Route::get('/{debitBatch}/three-peaks-recall-submission', [DebitBatchController::class, 'threePeaksRecallSubmission']);
    });

    Route::get('/location', [DebitBatchController::class, 'getDebitBatchesForLocation']);
    Route::get('/{debitBatch}/export', [DebitBatchController::class, 'export']);

    Route::post('/{debitBatch}/reconcile-three-peaks', [DebitBatchController::class, 'reconcileThreePeaksBatch']);
    Route::post('/{debitBatch}/regenerate-invoices', [DebitBatchController::class, 'regenerateDebitBatchInvoices']);
    Route::post('/{debitBatch}/ensure-users-on-batch', [DebitBatchController::class, 'ensureUserOnBatch']);

    Route::post('/{debitBatch}/resubmit', [DebitBatchController::class, 'resubmit']);
    Route::post('/{debitBatch}/mark-as-unprocessed/', [DebitBatchController::class, 'markAsUnprocessed']);
    Route::post('/{debitBatch}/request-netcash-statement', [DebitBatchController::class, 'requestNetcashStatement']);
    Route::post('/reconcile-sepa-bank-statement', [DebitBatchController::class, 'reconcileSepaBankStatement']);
});

Route::prefix('reports')->middleware(['auth:api'])->group(function () {
    Route::get('coaches-sessions', [ReportsReportController::class, 'coachesSessions']);
    Route::get('coaches-class-session-details', [ReportsReportController::class, 'coachesClassesOrSessionsDetails']);
    Route::get('performance', [ReportsReportController::class, 'performance']);
    Route::get('performance-details', [ReportsReportController::class, 'performanceDetails']);
    Route::get('personal-bests', [ReportsReportController::class, 'personalBests']);
    Route::get('export', [ReportsReportController::class, 'export']);
    Route::get('discovery-vitality', [ReportsReportController::class, 'discoveryVitality']);
    Route::get('user-metrics', [ReportsReportController::class, 'getUserMetrics']);
    Route::get('tenant-and-location-metrics', [ReportsReportController::class, 'getTenantAndLocationMetrics']);
    Route::get('region-metrics', [ReportsReportController::class, 'getMetricsForRegions']);
    Route::get('location-category-metrics', [ReportsReportController::class, 'geLocationCategoryMetrics']);

    Route::prefix('attendance')->middleware('auth:api')->group(function () {
        Route::get('overview', [AttendanceController::class, 'overview']);
        Route::get('class-attendance', [AttendanceController::class, 'classAttendance']);

        Route::get('class-attendance-details', [AttendanceController::class, 'classAttendanceDetails']);
        Route::get('member-attendance-details', [AttendanceController::class, 'memberAttendanceDetails']);

        Route::get('member-attendance', [AttendanceController::class, 'memberAttendance']);
        Route::get('member-non-attendance', [AttendanceController::class, 'memberNonAttendance']);
        Route::post('mail-non-attendance-members', [AttendanceController::class, 'mailNonAttendanceMembers']);
        Route::get('first-bookings', [AttendanceController::class, 'firstBookings']); // todo
        Route::get('class-attendance-over-24-hours', [AttendanceController::class, 'classAttendanceOver24Hours']);
    });

    Route::prefix('dashboard')->group(function () {
        Route::get('members-metrics', [ReportsDashboardController::class, 'memberMetrics']);
        Route::get('members-metric-details', [ReportsDashboardController::class, 'getMembersDetailsForMetric']);
        Route::get('members-per-package', [ReportsDashboardController::class, 'getMembersPerPackage']);
        Route::get('members-per-programme', [ReportsDashboardController::class, 'getMembersPerProgramme']);
        Route::get('scheduling-metrics', [ReportsDashboardController::class, 'getSchedulingMetrics']);
        Route::get('account-metrics', [ReportsDashboardController::class, 'getAccountsMetrics']);
        Route::get('total-sales-and-payments', [ReportsDashboardController::class, 'getTotalSalesAndPayments']);
        Route::get('sales-by-type', [ReportsDashboardController::class, 'getSalesByType']);
        Route::get('payments-by-type', [ReportsDashboardController::class, 'getPaymentsByType']);
        Route::get('payments-by-tag', [ReportsDashboardController::class, 'getPaymentsByTag']);
        Route::get('members-by-payment-type', [ReportsDashboardController::class, 'getMembersByPaymentType']);
        Route::get('leads-metrics', [ReportsDashboardController::class, 'getLeadsMetrics']);
        Route::get('debtors-summary', [ReportsDashboardController::class, 'getDebtorsSummaryByMonth']);
        Route::get('member-movement', [ReportsDashboardController::class, 'getMemberMovement']);
        Route::get('inactive-mandates-count', [ReportsDashboardController::class, 'inactiveMandatesCount']);
    });

    Route::prefix('finance')->group(function () {
        Route::get('debtors', [FinanceController::class, 'debtors']);
        Route::get('packages-sales-and-revenue', [FinanceController::class, 'getPackagesSalesAndRevenueMetrics']);
        Route::get('discounts', [FinanceController::class, 'discount']);
        Route::get('payment-types', [FinanceController::class, 'paymentTypes']);
    });
});

Route::prefix('terms-of-use')->group(function () {
    Route::get('', [TermsOfUseController::class, 'show']);
    Route::post('', [TermsOfUseController::class, 'store']);
});

Route::prefix('terms-and-conditions')->group(function () {
    Route::get('/', [TermsAndConditionsController::class, 'show']);
    Route::put('/accept', [TermsAndConditionsController::class, 'accept'])->middleware('auth:api');
    Route::post('', [TermsAndConditionsController::class, 'storeTermsAndConditions']);
});

Route::prefix('linked-users')->middleware('auth:api')->group(function () {
    Route::get('/', [LinkedUserController::class, 'index']);
    Route::post('/', [LinkedUserController::class, 'store'])->middleware('throttle:auth');
    Route::delete('/{linkedUser}', [LinkedUserController::class, 'delete']);
});

Route::prefix('mandates')->middleware('auth:api')->group(function () {
    Route::get('/', [MandateController::class, 'list']);
    Route::get('/user/{userId}', [MandateController::class, 'getAllMandatesForUser']);
    Route::get('/user/{userId}/latest', [MandateController::class, 'getLatestMandateForUser']);
    Route::get('/{mandate}', [MandateController::class, 'show']);
    Route::post('/', [MandateController::class, 'store']);
    Route::put('/send-onboarding-link', [MandateController::class, 'sendOnboardingLink']);
    Route::put('/{mandate}', [MandateController::class, 'update']);
});

Route::prefix('export')->middleware('auth:api')->group(function () {
    Route::get('/locations', [ExportController::class, 'locations']);
    Route::get('/users', [ExportController::class, 'users']);

});

Route::prefix('bulk-upload')->middleware('auth:api')->group(function () {
    Route::post('users', [BulkImportController::class, 'users']);
    Route::post('banking-details', [BulkImportController::class, 'userBankingDetails']);
});

Route::prefix('leads')->group(function () {
    Route::middleware('auth:api')->group(function () {
        Route::get('', [LeadController::class, 'listLeadMembers']);
        Route::post('', [LeadController::class, 'store']);
        Route::post('/sign-up', [LeadController::class, 'signUp'])->withoutMiddleware('auth:api');
        Route::put('/{leadMember}', [LeadController::class, 'update']);
        Route::get('/export', [LeadController::class, 'exportLeadMembers']);
        Route::post('/import', [LeadController::class, 'importLeadMembers']);
        Route::post('/{leadMember}/generate-invoice', [LeadController::class, 'generateLeadInvoice']);
        Route::put('/bulk-status-change', [LeadController::class, 'bulkStatusChange']);
        Route::delete('/bulk-delete', [LeadController::class, 'bulkDelete']);
        Route::put('/{leadMember}/convert', [LeadController::class, 'convertLeadToGymMember']);
    });

    // Widget endpoints
    Route::middleware(['auth.guest'])->group(function () {
        Route::get('/drop-in/packages', [LeadController::class, 'getDropInPackages']);
        Route::post('/create-drop-in', [LeadController::class, 'createDropIn']);
        Route::post('/create-class-booking', [LeadController::class, 'createClassBooking']);
        Route::post('/cancel-class-booking', [LeadController::class, 'cancelClassBooking']);
    });
});

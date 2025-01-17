<?php

namespace App\Http\Controllers\API;

use App\Enums\DebitOrderSetting as EnumsDebitOrderSetting;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ShowLocationFinanceInvoiceSettingsRequest;
use App\Http\Requests\Settings\ShowLocationPointOfSaleSettingsRequest;
use App\Http\Requests\Settings\ShowTenantBookingSettingsRequest;
use App\Http\Requests\Settings\ShowTenantContractSettingsRequest;
use App\Http\Requests\Settings\ShowTenantFinanceDebitOrderSettingsRequest;
use App\Http\Requests\Settings\ShowTenantFinanceSettingsRequest;
use App\Http\Requests\Settings\ShowTenantLocationWidgetSettingsRequest;
use App\Http\Requests\Settings\ShowTenantRegistrationSettingsRequest;
use App\Http\Requests\Settings\ShowTenantWaiverSettingsRequest;
use App\Http\Requests\Settings\ShowTenantWorkoutSettingsRequest;
use App\Http\Requests\Settings\UpdateLocationFinanceInvoiceSettingsRequest;
use App\Http\Requests\Settings\UpdateLocationPointOfSaleSettingsRequest;
use App\Http\Requests\Settings\UpdateTenantBookingSettingsRequest;
use App\Http\Requests\Settings\UpdateTenantContractSettingsRequest;
use App\Http\Requests\Settings\UpdateTenantFinanceDebitOrderSettingsRequest;
use App\Http\Requests\Settings\UpdateTenantFinanceSettingsRequest;
use App\Http\Requests\Settings\UpdateTenantLocationWidgetSettingsRequest;
use App\Http\Requests\Settings\UpdateTenantRegistrationSettingsRequest;
use App\Http\Requests\Settings\UpdateTenantWaiverSettingsRequest;
use App\Http\Requests\Settings\UpdateTenantWorkoutSettingsRequest;
use App\Http\Requests\TenantSettings\ListTenantCoronavirusSettingsRequest;
use App\Http\Requests\TenantSettings\ListTenantSettingsRequest;
use App\Http\Requests\TenantSettings\UpdateTenantCoronavirusSettingsRequest;
use App\Http\Requests\TenantSettings\UpdateTenantSettingsRequest;
use App\Http\Resources\CurrencyResource;
use App\Http\Resources\DebitDayResource;
use App\Http\Resources\TenantSettingCoronavirusResource;
use App\Http\Resources\TenantSettingResource;
use App\Models\DebitDay;
use App\Models\DebitOrderSetting;
use App\Models\LeadWaivers;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    public function showTenantSettings(ListTenantSettingsRequest $request, Tenant $tenant)
    {
        return new TenantSettingResource($tenant->settings);
    }

    public function updateTenantSettings(UpdateTenantSettingsRequest $request, Tenant $tenant)
    {
        $tenant->settings()->updateOrCreate(
            ['box_id' => $tenant->getKey()],
            [
                'theme' => $request->has('theme') ? $request->get('theme') : $tenant->settings->theme,
                'hidden_features' => $request->has('hidden_features') ? $request->get('hidden_features') : $tenant->settings->hidden_features,
                'locale_language' => $request->has('locale_language') ? $request->get('locale_language') : $tenant->settings->locale_language,
            ]

        );

        if ($request->has('logo')) {
            $logoPath = $tenant->settings?->logo_path;

            if (is_null($request->logo)) {

                if ($logoPath && Storage::disk('public')->exists($logoPath)) {
                    Storage::disk('public')->delete($logoPath);
                }

                $tenant->settings()->update([
                    'logo_path' => null,
                ]);
            } else {
                if ($logoPath && Storage::disk('public')->exists($logoPath)) {
                    Storage::disk('public')->delete($logoPath);
                }
                $fileName = uniqid(rand(), true).'.'.$request->file('logo')->getClientOriginalExtension();
                $filePath = 'box-logos/'.$tenant->getKey().'/';

                $request->file('logo')->storePubliclyAs($filePath, $fileName, ['disk' => 'public']);

                $tenant->settings()->update([
                    'logo_path' => $filePath.$fileName,
                ]);
            }
        }

        return new TenantSettingResource($tenant->settings->fresh());
    }

    public function showTenantCoronavirusSettings(ListTenantCoronavirusSettingsRequest $request, Tenant $tenant): TenantSettingCoronavirusResource
    {
        $tenant->load('settings');

        $settings = $tenant->settings ?? $tenant->settings()->create();

        return new TenantSettingCoronavirusResource($settings);
    }

    public function updateTenantCoronavirusSettings(UpdateTenantCoronavirusSettingsRequest $request, Tenant $tenant): TenantSettingCoronavirusResource
    {
        $tenant->loadMissing('settings');

        if (! $tenant->settings) {
            $tenant->settings()->create([
                'coronavirus_is_enabled' => $request->get('is_enabled'),
                'coronavirus_is_enabled_questionnaire_member_app' => $request->get('is_enabled_questionnaire_member_app'),
                'coronavirus_can_display_vaccination_details_roster' => $request->get('is_display_vaccination_details_roster'),
                'coronavirus_can_display_vaccination_details_class' => $request->get('is_display_vaccination_details_class'),
            ]);
        } else {

            $tenant->settings()->update([
                'coronavirus_is_enabled' => $request->get('is_enabled'),
                'coronavirus_is_enabled_questionnaire_member_app' => $request->get('is_enabled_questionnaire_member_app'),
                'coronavirus_can_display_vaccination_details_roster' => $request->get('is_display_vaccination_details_roster'),
                'coronavirus_can_display_vaccination_details_class' => $request->get('is_display_vaccination_details_class'),
            ]);
        }

        return new TenantSettingCoronavirusResource($tenant->settings->fresh());
    }

    public function showTenantFinanceSettings(ShowTenantFinanceSettingsRequest $request, Tenant $tenant): JsonResponse
    {
        $tenant->loadMissing(['memberCurrency']);

        return response()->json([
            'member_billing_currency' => new CurrencyResource($tenant->memberCurrency),
            'deactivate_member_on_contract_end' => $tenant->deactivate_contracts_ended,
            'cash_member_invoice_generation_day' => $tenant->cash_member_invoice_generation_day,
            'cash_member_invoice_due_day' => $tenant->cash_member_invoice_due_day,
            'cash_member_invoice_strategy' => $tenant->cash_member_invoice_strategy,
        ]);
    }

    public function updateTenantFinanceSettings(UpdateTenantFinanceSettingsRequest $request, Tenant $tenant): JsonResponse
    {
        $tenant->fill([
            ...$request->safe()->except(['member_billing_currency', 'deactivate_member_on_contract_end']),
            'deactivate_contracts_ended' => $request->deactivate_member_on_contract_end,
            'member_billing_currency_id' => $request->member_billing_currency,
        ])->save();

        if (is_null($request->cash_member_invoice_generation_day)) {
            $tenant->update([
                'cash_member_invoice_generation_day' => null,
                'cash_member_invoice_due_day' => null,
                'cash_member_invoice_strategy' => null,
            ]);
        }

        return response()->json([
            'member_billing_currency' => new CurrencyResource($tenant->memberCurrency),
            'deactivate_member_on_contract_end' => $tenant->deactivate_contracts_ended,
            'cash_member_invoice_generation_day' => $tenant->cash_member_invoice_generation_day,
            'cash_member_invoice_due_day' => $tenant->cash_member_invoice_due_day,
            'cash_member_invoice_strategy' => $tenant->cash_member_invoice_strategy,
        ]);
    }

    public function showLocationFinanceInvoiceSettings(ShowLocationFinanceInvoiceSettingsRequest $request, Location $location): JsonResponse
    {
        return response()->json([
            'name' => $location->name,
            'business_name' => $location->business_name,
            'invoice_prefix' => $location->box_facility_prefix,
            'vat_number' => $location->vat,
            'vat_percent' => $location->vat_percent,
            'invoice_information' => $location->invoiceinfo,
            'show_invoice_totals' => $location->show_invoice_totals,
            'logo' => $location->logofile ? Storage::url($location->logofile) : null,
        ]);
    }

    public function updateLocationFinanceInvoiceSettings(UpdateLocationFinanceInvoiceSettingsRequest $request, Location $location): JsonResponse
    {
        if ($request->has('logo')) {
            // Delete the file from the server
            if ($location->logofile) {
                Storage::disk('public')->delete($location->logofile);
            }

            if (is_null($request->file('logo'))) {
                // Set the logo to null
                $location->logofile = null;
            } else {
                $fileName = $location->getKey().'.'.$request->file('logo')->getClientOriginalExtension();
                $logoPath = 'logos/';

                $request->file('logo')->storePubliclyAs(
                    $logoPath,
                    $fileName,
                    [
                        'disk' => 'public',
                        'visibility' => 'public',
                    ]
                );

                $location->logofile = $logoPath.$fileName;
            }
        }

        $location->update($request->safe()->except('logo'));

        return response()->json([
            'name' => $location->name,
            'business_name' => $location->business_name,
            'invoice_prefix' => $location->box_facility_prefix,
            'vat_number' => $location->vat,
            'vat_percent' => $location->vat_percent,
            'invoice_information' => $location->invoiceinfo,
            'show_invoice_totals' => $location->show_invoice_totals,
            'logo' => $location->logofile ? Storage::url($location->logofile) : null,
        ]);
    }

    public function showTenantFinanceDebitOrderSettings(ShowTenantFinanceDebitOrderSettingsRequest $request, Tenant $tenant): JsonResponse
    {
        // Get debit days
        $debitDays = DebitDay::query()->get();

        // Get debit-order settings for box
        $boxDebitOrderSettings = DebitOrderSetting::query()
            ->where('box_id', $tenant->getKey())
            ->get();

        // Check if debit-order settings exist for box. If not, then create settings for this box
        if ($boxDebitOrderSettings->isEmpty()) {
            foreach ($debitDays as $debitDay) {
                // Create new settings for each debit day.
                DebitOrderSetting::create([
                    'box_id' => $tenant->getKey(),
                    'debit_day_id' => $debitDay->getKey(),
                ]);
            }
        }

        $settings = [];

        foreach ($boxDebitOrderSettings as $boxDebitOrderSetting) {
            $settings[] = [
                'id' => $boxDebitOrderSetting->getKey(),
                'debit_day' => new DebitDayResource($boxDebitOrderSetting->debitDay),
                'invoice_due_on_date' => $boxDebitOrderSetting->debit_order_invoice_date?->toArray(),
            ];
        }

        return response()->json($settings);
    }

    public function updateTenantFinanceDebitOrderSettings(UpdateTenantFinanceDebitOrderSettingsRequest $request, Tenant $tenant): JsonResponse
    {
        $debitOrderSettings = $request->debit_order_settings;

        $settings = [];

        foreach ($debitOrderSettings as $debitOrderSettingData) {

            $debitOrderSetting = DebitOrderSetting::query()
                ->where('box_id', $tenant->getKey())
                ->whereKey($debitOrderSettingData['id'])
                ->first();

            if (! $debitOrderSetting) {
                continue;
            }

            // Update debit order settings
            if (array_key_exists('invoice_due_on_date', $debitOrderSettingData)) {
                $debitOrderSetting->debit_order_invoice_date = EnumsDebitOrderSetting::tryFrom($debitOrderSettingData['invoice_due_on_date']);
                $debitOrderSetting->save();
            }

            $settings[] = [
                'id' => $debitOrderSetting->getKey(),
                'debit_day' => new DebitDayResource($debitOrderSetting->debitDay),
                'invoice_due_on_date' => $debitOrderSetting->debit_order_invoice_date?->toArray(),
            ];
        }

        return response()->json($settings);
    }

    public function showTenantBookingSettings(ShowTenantBookingSettingsRequest $request, Tenant $tenant): JsonResponse
    {
        $settings = [
            'booking_threshold' => $tenant->booking_threshold,
            'is_limit_inter_location_bookings' => $tenant->limit_inter_facility_bookings,
            'location_settings' => [],
        ];

        foreach ($tenant->locations()->get() as $location) {
            $settings['location_settings'][] = [
                'id' => $location->getKey(),
                'name' => $location->name,
                'max_bookings_per_athlete_per_day' => $location->max_bookings_per_athlete_per_day,
                'is_view_class_bookings' => $location->view_class_bookings,
                'is_display_booking_details' => $location->display_booking_details,
                'is_visible_in_app_sessions' => $location->visible_in_app_sessions,
            ];
        }

        return response()->json($settings);
    }

    public function updateTenantBookingSettings(UpdateTenantBookingSettingsRequest $request, Tenant $tenant): JsonResponse
    {
        $facilitySettings = collect($request->location_settings);

        $facilities = Location::query()
            ->whereKey($facilitySettings->pluck('id')->toArray())
            ->get();

        DB::transaction(function () use (&$tenant, &$facilities, $facilitySettings, $request) {
            $tenant->fill([
                'booking_threshold' => $request->booking_threshold,
                'limit_inter_facility_bookings' => $request->is_limit_inter_facility_bookings,
            ])->save();

            foreach ($facilities as $facility) {

                $setting = $facilitySettings->where('id', $facility->getKey())->first();

                $facility->fill([
                    'max_bookings_per_athlete_per_day' => $setting['max_bookings_per_athlete_per_day'],
                    'view_class_bookings' => $setting['is_view_class_bookings'],
                    'display_booking_details' => $setting['is_display_booking_details'],
                    'visible_in_app_sessions' => $setting['is_visible_in_app_sessions'],
                ])->save();
            }
        });

        $settings = [
            'booking_threshold' => $tenant->booking_threshold,
            'is_limit_inter_location_bookings' => $tenant->limit_inter_facility_bookings,
            'location_settings' => [],
        ];

        foreach ($facilities as $location) {
            $settings['location_settings'][] = [
                'id' => $location->getKey(),
                'name' => $location->name,
                'max_bookings_per_athlete_per_day' => $location->max_bookings_per_athlete_per_day,
                'is_view_class_bookings' => $location->view_class_bookings,
                'is_display_booking_details' => $location->display_booking_details,
                'is_visible_in_app_sessions' => $location->is_visible_in_app_sessions,
            ];
        }

        return response()->json($settings);
    }

    public function showTenantContractSettings(ShowTenantContractSettingsRequest $request, Tenant $tenant): JsonResponse
    {
        return response()->json([
            'contract_expiry_notification_days' => $tenant->contract_expiry_notfication_days,
            'contract_terms_and_conditions' => $tenant->contract_terms_and_conditions,
            'is_user_contract_visible_in_app' => $tenant->settings?->is_user_contract_visible_in_app ?: false,
        ]);
    }

    public function updateTenantContractSettings(UpdateTenantContractSettingsRequest $request, Tenant $tenant): JsonResponse
    {
        $tenant->loadMissing('settings');

        DB::transaction(function () use (&$tenant, $request) {

            $tenant->fill([
                'contract_expiry_notfication_days' => $request->contract_expiry_notification_days,
                'contract_terms_and_conditions' => $request->contract_terms_and_conditions,
            ])->save();

            if ($tenant->settings) {
                $tenant->settings->fill([
                    'is_user_contract_visible_in_app' => $request->is_user_contract_visible_in_app,
                ])->save();
            }
        });

        return response()->json([
            'contract_expiry_notification_days' => $tenant->contract_expiry_notfication_days,
            'contract_terms_and_conditions' => $tenant->contract_terms_and_conditions,
            'is_user_contract_visible_in_app' => $tenant->settings?->is_user_contract_visible_in_app ?: false,
        ]);
    }

    public function showTenantWaiverSettings(ShowTenantWaiverSettingsRequest $request, Tenant $tenant): JsonResponse
    {
        $tenant->loadMissing(['leadSettings.waiver']);

        $user = auth()->user();

        $tenantUser = TenantUser::query()
            // ->active()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('box_id', $tenant->getKey())
            ->when(
                ! $user->isAdmin(),
                fn ($q) => $q->firstOrFail(),
                fn ($q) => $q->first()
            );

        if ($tenantUser?->isMember()) {
            if (! $tenant->leadSettings || ! $tenant->leadSettings->waiver?->isDigital()) {
                abort(404, 'Digital waiver settings not found');
            }

            return response()->json([
                'waiver_terms_and_conditions' => $tenant->leadSettings->waiver->digital_terms_and_conditions,
            ]);

        }

        if (! $tenant->leadSettings?->waiver) {
            DB::transaction(function () use (&$tenant) {
                $waiver = LeadWaivers::create([
                    'user_id' => auth()->user()->getAuthIdentifier(),
                    'digital' => true,
                    'digital_terms_and_conditions' => '<p><strong>Terms and Conditions</strong><br>You need to accept our terms and conditions before proceeding.</p>',
                ]);

                if (! $tenant->leadSettings) {
                    $tenant->leadSettings()->create([
                        'hash' => uniqid(),
                        'waiver_id' => $waiver->getKey(),
                    ]);
                } else {
                    $tenant->leadSettings()->update([
                        'waiver_id' => $waiver->getKey(),
                    ]);
                }

                $tenant->refresh();

                $tenant->leadSettings->setRelation('waiver', $waiver);
            });
        }

        return response()->json([
            'id' => $tenant->leadSettings->getKey(),
            'hash' => $tenant->leadSettings->hash,
            'waiver' => [
                'id' => $tenant->leadSettings->waiver->getKey(),
                'is_digital' => $isDigital = $tenant->leadSettings->waiver->isDigital(),
                'digital_terms_and_conditions' => $isDigital ? $tenant->leadSettings->waiver->digital_terms_and_conditions : null,
                'file' => $isDigital ? null : $tenant->leadSettings->waiver->file_url,
            ],
        ]);
    }

    public function updateTenantWaiverSettings(UpdateTenantWaiverSettingsRequest $request, Tenant $tenant, LeadWaivers $waiver): JsonResponse
    {
        if ($request->is_digital) {

            $waiver->digital = true;
            $waiver->digital_terms_and_conditions = $request->digital_terms_and_conditions;
            $waiver->file_path = null;
            $waiver->file_name = null;
            $waiver->file_mime = null;
            $waiver->save();

            if ($waiver->getOriginal('file_path')) {
                Storage::disk('public')->delete($waiver->file_path);
            }

        } else {

            $waiver->digital = false;
            $waiver->digital_terms_and_conditions = null;

            $fileName = uniqid().'.'.$request->file('file')->getClientOriginalExtension();
            $filePath = 'waivers/';

            $request->file('file')->storePubliclyAs(
                $filePath,
                $fileName,
                [
                    'disk' => 'public',
                    'visibility' => 'public',
                ]
            );

            if ($waiver->file_path) {
                Storage::disk('public')->delete($waiver->file_path);
            }

            $waiver->file_path = $filePath.$fileName;
            $waiver->file_name = $fileName;
            $waiver->file_mime = $request->file('file')->getMimeType();

            $waiver->save();
        }

        $tenant->loadMissing(['leadSettings.waiver']);

        return response()->json([
            'id' => $tenant->leadSettings->getKey(),
            'hash' => $tenant->leadSettings->hash,
            'waiver' => [
                'id' => $waiver->getKey(),
                'is_digital' => $isDigital = $waiver->isDigital(),
                'digital_terms_and_conditions' => $isDigital ? $waiver->digital_terms_and_conditions : null,
                'file' => $isDigital ? null : Storage::url($waiver->file_path),
            ],
        ]);
    }

    public function showTenantWorkoutSettings(ShowTenantWorkoutSettingsRequest $request, Tenant $tenant): JsonResponse
    {
        return response()->json([
            'workout_threshold' => $tenant->workout_threshold,
        ]);
    }

    public function updateTenantWorkoutSettings(UpdateTenantWorkoutSettingsRequest $request, Tenant $tenant): JsonResponse
    {
        $tenant->fill([
            'workout_threshold' => $request->workout_threshold,
        ])->save();

        return response()->json([
            'workout_threshold' => $tenant->workout_threshold,
        ]);
    }

    public function showTenantLocationWidgetSettings(ShowTenantLocationWidgetSettingsRequest $request, Tenant $tenant): JsonResponse
    {
        $location = $request->has('location_id') ? Location::findOrFail($request->location_id) : null;
        $entity = $location ?? $tenant;

        if (! Arr::get($tenant->extra_parameters, 'dropInPackageSettings')) {
            $tenant->extra_parameters = [
                'dropInPackageSettings' => [
                    'showAllClasses' => 'no',
                    'paymentType' => 'cash',
                ],
            ];

            $tenant->save();
        }

        if (empty($entity->public_token)) {
            $entity->public_token = sha1(uniqid('', true));
            $entity->save();
        }

        return response()->json([
            'tenant' => [
                'id' => $tenant->getKey(),
            ],
            'public_token' => $entity->public_token,
            'sign_up_redirect_url' => $entity->sign_up_redirect_url,
            'lead_redirect_url' => $entity->lead_redirect_url,
            'lead_request_demo_redirect_url' => $entity->lead_request_demo_redirect_url,
        ]);
    }

    public function updateTenantLocationWidgetSettings(UpdateTenantLocationWidgetSettingsRequest $request, Tenant $tenant): JsonResponse
    {
        $location = $request->has('location_id') ? Location::findOrFail($request->location_id) : null;
        $entity = $location ?? $tenant;

        if (empty($entity->public_token)) {
            $entity->public_token = sha1(uniqid('', true));
        }

        $entity->fill([
            'sign_up_redirect_url' => $request->sign_up_redirect_url,
            'lead_redirect_url' => $request->lead_redirect_url,
            'lead_request_demo_redirect_url' => $request->lead_request_demo_redirect_url,
        ])->save();

        return response()->json([
            'tenant' => [
                'id' => $tenant->getKey(),
            ],
            'public_token' => $entity->public_token,
            'sign_up_redirect_url' => $entity->sign_up_redirect_url,
            'lead_redirect_url' => $entity->lead_redirect_url,
            'lead_request_demo_redirect_url' => $entity->lead_request_demo_redirect_url,
        ]);
    }

    public function showLocationPointOfSaleSettings(ShowLocationPointOfSaleSettingsRequest $request, Location $location): JsonResponse
    {
        $settings = explode(',', Arr::get($location->extra_parameters, 'pos_public_payment_options'));

        return response()->json($settings);
    }

    public function updateLocationPointOfSaleSettings(UpdateLocationPointOfSaleSettingsRequest $request, Location $location): JsonResponse
    {
        $extraParameters = $location->extra_parameters;
        $extraParameters['pos_public_payment_options'] = implode(',', $request->payment_options);

        $location->extra_parameters = $extraParameters;
        $location->save();

        $settings = explode(',', Arr::get($location->extra_parameters, 'pos_public_payment_options'));

        return response()->json($settings);
    }

    public function showTenantRegistrationSettings(ShowTenantRegistrationSettingsRequest $request, Tenant $tenant): JsonResponse
    {
        return response()->json([
            'id' => $tenant->getKey(),
            'sign_up_payment_options' => $tenant->signup_payment_options,
            'sign_up_debit_day_options' => $tenant->signup_debit_day_options,
            'is_sign_up_use_contracts_and_waivers' => $tenant->signup_use_contract_and_waivers,
            'sign_up_required_fields' => $tenant->sign_up_required_fields ?: [],
            'pro_rate_strategy' => $tenant->pro_rate_strategy,
        ]);
    }

    public function updateTenantRegistrationSettings(UpdateTenantRegistrationSettingsRequest $request, Tenant $tenant): JsonResponse
    {
        $tenant->fill([
            'signup_payment_options' => $request->sign_up_payment_options ?: [1, 2, 5],
            'signup_debit_day_options' => $request->sign_up_debit_day_options ?: [1, 2, 3, 4, 5, 6, 7],
            'signup_use_contract_and_waivers' => $request->is_sign_up_use_contracts_and_waivers,
            'sign_up_required_fields' => $request->sign_up_required_fields ?: [],
            'pro_rate_strategy' => $request->pro_rate_strategy ?: 'manual',
        ])->save();

        return response()->json([
            'id' => $tenant->getKey(),
            'sign_up_payment_options' => $tenant->signup_payment_options,
            'sign_up_debit_day_options' => $tenant->signup_debit_day_options,
            'is_sign_up_use_contracts_and_waivers' => $tenant->signup_use_contract_and_waivers,
            'sign_up_required_fields' => $tenant->sign_up_required_fields ?: [],
            'pro_rate_strategy' => $tenant->pro_rate_strategy,
        ]);
    }
}

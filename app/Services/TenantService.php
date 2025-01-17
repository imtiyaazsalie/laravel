<?php

namespace App\Services;

use App\Enums\TenantStatus;
use App\Enums\UserType;
use App\Models\BroadcastMessages;
use App\Models\LeadSettings;
use App\Models\LeadWaivers;
use App\Models\Location;
use App\Models\LocationCategory;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Hash;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class TenantService
{
    public function list()
    {
        return QueryBuilder::for(Tenant::class)
            ->allowedFilters([
                AllowedFilter::exact('tenant_id', 'box_id'),
                AllowedFilter::exact('region_id', 'region_id'),
                AllowedFilter::exact('status_id', 'box_status_id'),
                AllowedFilter::exact('affiliate_id', 'affiliations.affiliate_id'),
                AllowedFilter::exact('location_category_id', 'locations.box_facility_category_id'),
                AllowedFilter::partial('search', 'box_desc'),
            ])
            ->allowedIncludes([
                'affiliations',
                AllowedInclude::relationship('tenant_currency', 'tenantCurrency'),
                AllowedInclude::relationship('member_currency', 'memberCurrency'),
                AllowedInclude::relationship('region', 'region'),
                AllowedInclude::relationship('locations.category'),
                AllowedInclude::count('nonDeactivatedUsersCount'),
            ])
            ->allowedSorts([
                AllowedSort::field('name', 'box_desc'),
                AllowedSort::field('status', 'box_status_id'),
            ])
            ->where('box_id', '!=', 0)
            ->whereNotNull('box_desc')
            ->defaultSort('box_desc')
            ->_paginate();
    }

    public function store($request)
    {
        $tenant = Tenant::create([
            'box_desc' => $request->input('name'),
            'description' => $request->input('description'),
            'region_id' => $request->input('region_id'),
            'box_status_id' => TenantStatus::ACTIVE->value,
            'is_trial' => $request->input('is_trial'),
            'timezone_id' => $request->input('timezone_id'),
            'box_billing_currency_id' => $request->input('billing_currency_id'),
            'member_billing_currency_id' => $request->input('member_billing_currency_id'),
            'website_url' => $request->input('website_url'),
            'instagram_url' => $request->input('instagram_url'),
            'facebook_url' => $request->input('facebook_url'),
            'signup_payment_options' => [1, 2, 5], // defaults: cannot be done in the attributes value on the model
            'signup_debit_day_options' => [1, 2, 3, 4, 5, 6, 7], // defaults: cannot be done in the attributes value on the model
        ]);

        Setting::create([
            'box_id' => $tenant->box_id,
            'created_by_id' => $request->user()->getKey(),
        ]);

        $password = uniqid();

        $user = User::create([
            'name' => $request->input('user_name'),
            'surname' => $request->input('user_surname'),
            'gender_id' => $request->input('user_gender_id'),
            'email' => $request->input('user_email'),
            'mobile' => $request->input('user_mobile'),
            'dob' => $request->input('user_date_of_birth'),
            'password' => Hash::make($password),
        ]);

        $userTenant = (new TenantUserService())->createUserBoxMembership($user, $tenant, UserType::HEAD_COACH);

        BroadcastMessages::create([
            'box_id' => $tenant->getKey(),
            'message' => 'Welcome to Octiv',
            'is_active' => false,
        ]);

        $leadWaiver = LeadWaivers::create([
            'user_id' => $user->getKey(),
            'digital_terms_and_conditions' => '<p><strong>Terms and Conditions</strong><br>You need to accept our terms and conditions before proceeding.</p>',
        ]);

        LeadSettings::create([
            'box_id' => $tenant->getKey(),
            'hash' => uniqid(),
            'waiver_id' => $leadWaiver->getKey(),
        ]);

        // Send notification to coaches
        $emailContent = Markdown::parse(view('emails.tenant-activation', [
            'user' => $user,
            'password' => $password,
            'tenant' => $tenant,
        ]))->__toString();

        (new CrmService())->createScheduledEmail(
            $emailContent,
            'Facility Activation - Octiv',
            $user->email,
            'noreply@octivfitness.com'
        );

        (new AccessPrivilegeService())->initializeUserPrivileges($userTenant, $userTenant->type);
        (new AccessPrivilegeService())->initializeUserFacilityPrivileges($userTenant);

        return $tenant;
    }

    public function update($request, Tenant $tenant): Tenant
    {
        $tenant->update([
            ...$request->safe()->except('category_override'),
            'deactivated_on' => $request->has('status') && ($request->input('status') != TenantStatus::ACTIVE->value)
                ? now()
                : null,
        ]);

        $consistentFacilityCategory = $tenant->getConsistentBoxFacilityCategory();
        $categoryOverride = $request->input('category_override');

        if ($categoryOverride !== null && (! $consistentFacilityCategory || (int) $categoryOverride !== $consistentFacilityCategory->getKey())) {
            $categoryOverride = LocationCategory::query()->find($categoryOverride);

            /** @var Location $facility */
            foreach ($tenant->locations()->get() as $facility) {
                $facility->update(['category_id' => $categoryOverride->getKey()]);
            }
        }

        return $tenant->refresh();
    }
}

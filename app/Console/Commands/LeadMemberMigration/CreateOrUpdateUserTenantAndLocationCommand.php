<?php

namespace App\Console\Commands\LeadMemberMigration;

use App\Enums\Gender;
use App\Enums\UserDebitStatus;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\LeadMember;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\TenantUserService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

ini_set('memory_limit', '16G');

class CreateOrUpdateUserTenantAndLocationCommand extends Command
{
    protected $signature = 'lead-member:create-or-update-user-tenant-and-location';

    protected $description = 'Command description';

    public function handle(): void
    {
        DB::table('user_types')->updateOrInsert(['user_type_desc' => 'Lead']);
        DB::table('user_types')->updateOrInsert(['user_type_desc' => 'Discovery']);
        DB::table('user_types')->updateOrInsert(['user_type_desc' => 'User']);

        $this->migrateDuplicateLeads();
        $this->migrateNonDuplicateLeads();

    }

    private function migrateNonDuplicateLeads(): void
    {
        $leadAccounts = LeadMember::query()
            ->select(
                'email_address',
                'lead_members.box_facility_id',
                'lead_members.date_of_birth',
                'box_id',
                'email_address',
                'first_name',
                'last_name',
                'gender',
                'mobile_number',
                'created_on',
                'updated_on'
            )
            ->whereNull('user_id')
            ->join('box_facility', 'box_facility.box_facility_id', '=', 'lead_members.box_facility_id')
            ->groupBy([
                'lead_members.box_facility_id',
                'first_name',
                'last_name',
                'email_address',
            ])
            ->having(DB::raw('COUNT(*)'), '=', 1)
            ->orderBy('email_address')
            ->orderBy('first_name')
            ->orderBy('last_name');

        $paginator = $leadAccounts->cursorPaginate(10000, ['*'], 'myCursorId');

        while ($paginator->hasPages()) {
            foreach ($paginator->items() as $leadAccount) {
                // if lead does not have an existing utb and a user account does not exist
                if (! $this->leadHasUserTenant($leadAccount->email_address, $leadAccount->box_id) > 0 && ! $this->userHasEmail($leadAccount->email_address, $leadAccount->first_name, $leadAccount->last_name)) {
                    // then add an utb and user account, add user to facility

                    $user = $this->createUser($leadAccount);

                    $tenant = Tenant::withoutGlobalScopes()
                        ->where('box_id', '=', $leadAccount->box_id)
                        ->first();

                    $location = Location::withoutGlobalScopes()
                        ->where('box_facility_id', '=', $leadAccount->box_facility_id)
                        ->first();

                    $this->createLeadUserTenant($user, $tenant, $leadAccount->created_on);

                    $leadUsers = $this->getLeadUsers($leadAccount->email_address, $leadAccount->box_facility_id, $leadAccount->box_id, $leadAccount->first_name, $leadAccount->last_name);

                    foreach ($leadUsers as $leadUser) {
                        $leadUser->update(['user_id' => $user->user_id], ['timestamps' => false]);
                        $this->createLeadUserLocation($leadUser, $location);
                    }

                }
                // if lead has an existing utb and user account
                elseif ($this->leadHasUserTenant($leadAccount->email_address, $leadAccount->box_id) > 0 && $this->userHasEmail($leadAccount->email_address, $leadAccount->first_name, $leadAccount->last_name)) {
                    // then only add user_id to lead_member table, add user to facility

                    $user = User::withoutGlobalScopes()
                        ->where('email', $leadAccount->email_address)
                        ->where('name', $leadAccount->first_name)
                        ->where('surname', $leadAccount->last_name)
                        ->first();

                    $location = Location::withoutGlobalScopes()
                        ->where('box_facility_id', '=', $leadAccount->box_facility_id)
                        ->first();

                    $leadUsers = $this->getLeadUsers($leadAccount->email_address, $leadAccount->box_facility_id, $leadAccount->box_id, $leadAccount->first_name, $leadAccount->last_name);

                    foreach ($leadUsers as $leadUser) {
                        $leadUser->update(['user_id' => $user->user_id], ['timestamps' => false]);
                        $this->createLeadUserLocation($leadUser, $location);
                    }

                }
                // if lead has an existing utb and user does not have an account
                else {
                    // then create an alias user, create a lead utb, add user_to_facility

                    $user = $this->createUser($leadAccount, $this->setAliasEmail($leadAccount->email_address));

                    $tenant = Tenant::withoutGlobalScopes()
                        ->where('box_id', '=', $leadAccount->box_id)
                        ->first();

                    $location = Location::withoutGlobalScopes()
                        ->where('box_facility_id', '=', $leadAccount->box_facility_id)
                        ->first();

                    $this->createLeadUserTenant($user, $tenant, $leadAccount->created_on);

                    $leadUsers = $this->getLeadUsers($leadAccount->email_address, $leadAccount->box_facility_id, $leadAccount->box_id, $leadAccount->first_name, $leadAccount->last_name);

                    foreach ($leadUsers as $leadUser) {
                        $leadUser->update(['user_id' => $user->user_id], ['timestamps' => false]);
                        $this->createLeadUserLocation($leadUser, $location);
                    }
                }
            }

            $next = $paginator->nextCursor();
            $paginator = $leadAccounts->cursorPaginate(10000, ['*'], 'myCursorId', $next);
        }

    }

    private function migrateDuplicateLeads(): void
    {

        $leadAccounts = LeadMember::query()
            ->select(
                'email_address',
                'lead_members.box_facility_id',
                'lead_members.date_of_birth',
                'box_id',
                'email_address',
                'first_name',
                'last_name',
                'gender',
                'mobile_number',
                'created_on',
                'updated_on'
            )
            ->whereNull('user_id')
            ->join('box_facility', 'box_facility.box_facility_id', '=', 'lead_members.box_facility_id')
            ->groupBy([
                'lead_members.box_facility_id',
                'first_name',
                'last_name',
                'email_address',
            ])
            ->having(DB::raw('COUNT(*)'), '>', 1)
            ->orderBy('email_address')
            ->orderBy('first_name')
            ->orderBy('last_name');

        $paginator = $leadAccounts->cursorPaginate(10000, ['*'], 'myCursorId');

        while ($paginator->hasPages()) {
            foreach ($paginator->items() as $leadAccount) {
                // if lead does not have an existing utb and a user account does not exist
                if (! $this->leadHasUserTenant($leadAccount->email_address, $leadAccount->box_id) > 0 && ! $this->userHasEmail($leadAccount->email_address, $leadAccount->first_name, $leadAccount->last_name)) {
                    // then add an utb and user account, add user to facility

                    $user = $this->createUser($leadAccount);

                    $tenant = Tenant::withoutGlobalScopes()
                        ->where('box_id', '=', $leadAccount->box_id)
                        ->first();

                    $location = Location::withoutGlobalScopes()
                        ->where('box_facility_id', '=', $leadAccount->box_facility_id)
                        ->first();

                    $this->createLeadUserTenant($user, $tenant, $leadAccount->created_on);

                    $leadUsers = $this->getLeadUsers($leadAccount->email_address, $leadAccount->box_facility_id, $leadAccount->box_id, $leadAccount->first_name, $leadAccount->last_name);

                    foreach ($leadUsers as $leadUser) {
                        $leadUser->update(['user_id' => $user->user_id], ['timestamps' => false]);
                        $this->createLeadUserLocation($leadUser, $location);
                    }

                }
                // if lead has an existing utb and user account
                elseif ($this->leadHasUserTenant($leadAccount->email_address, $leadAccount->box_id) > 0 && $this->userHasEmail($leadAccount->email_address, $leadAccount->first_name, $leadAccount->last_name)) {
                    // then only add user_id to lead_member table, add user to facility

                    $user = User::withoutGlobalScopes()
                        ->where('email', $leadAccount->email_address)
                        ->where('name', $leadAccount->first_name)
                        ->where('surname', $leadAccount->last_name)
                        ->first();

                    $location = Location::withoutGlobalScopes()
                        ->where('box_facility_id', '=', $leadAccount->box_facility_id)
                        ->first();

                    $leadUsers = $this->getLeadUsers($leadAccount->email_address, $leadAccount->box_facility_id, $leadAccount->box_id, $leadAccount->first_name, $leadAccount->last_name);

                    foreach ($leadUsers as $leadUser) {
                        $leadUser->update(['user_id' => $user->user_id], ['timestamps' => false]);
                        $this->createLeadUserLocation($leadUser, $location);
                    }

                }
                // if lead has an existing utb and user does not have an account
                else {
                    // then create an alias user, create a lead utb, add user_to_facility

                    $user = $this->createUser($leadAccount, $this->setAliasEmail($leadAccount->email_address));

                    $tenant = Tenant::withoutGlobalScopes()
                        ->where('box_id', '=', $leadAccount->box_id)
                        ->first();

                    $location = Location::withoutGlobalScopes()
                        ->where('box_facility_id', '=', $leadAccount->box_facility_id)
                        ->first();

                    $this->createLeadUserTenant($user, $tenant, $leadAccount->created_on);

                    $leadUsers = $this->getLeadUsers($leadAccount->email_address, $leadAccount->box_facility_id, $leadAccount->box_id, $leadAccount->first_name, $leadAccount->last_name);

                    foreach ($leadUsers as $leadUser) {
                        $leadUser->update(['user_id' => $user->user_id], ['timestamps' => false]);
                        $this->createLeadUserLocation($leadUser, $location);
                    }
                }
            }

            $next = $paginator->nextCursor();
            $paginator = $leadAccounts->cursorPaginate(10000, ['*'], 'myCursorId', $next);
        }

    }

    private function leadHasUserTenant($email, $tenantId): bool
    {
        $has = TenantUser::query()
            ->withoutGlobalScopes()
            ->join('users', 'users.user_id', '=', 'user_to_box.user_id')
            ->where('users.email', '=', $email)
            ->where('box_id', '=', $tenantId)
            ->count();

        return $has > 0;

    }

    private function userHasEmail($email, $name, $surname): bool
    {
        $has = User::withoutGlobalScopes()
            ->where('email', $email)
            ->where('name', $name)
            ->where('surname', $surname)
            ->count();

        return $has > 0;
    }

    private function createLeadUserTenant(User $user, Tenant $tenant, $createdOn): void
    {
        (new TenantUserService())->createUserBoxMembership(
            $user,
            $tenant,
            UserType::LEAD_MEMBER,
            [],
            Carbon::parse($createdOn),
            null,
            null,
            UserDebitStatus::NO_PAYMENT,
            UserStatus::ACTIVE
        );
    }

    private function createUser($leadUser, $email = null): User|Builder
    {
        $gender = null;

        if (str($leadUser->gender)->startsWith('m')) {
            $gender = Gender::MALE->value;
        } elseif (str($leadUser->gender)->startsWith('f')) {
            $gender = Gender::FEMALE->value;
        }

        return User::query()->create([
            'name' => $leadUser->first_name,
            'surname' => $leadUser->last_name,
            'gender' => $gender,
            'dob' => $leadUser->date_of_birth,
            'email' => $email != null ? $email : $leadUser->email_address,
            'mobile' => $leadUser->mobile_number,
            'created_on' => $leadUser->created_on,
            'updated_on' => $leadUser->updated_on,
        ]);
    }

    private function getLeadUsers($email, $boxFacilityId, $boxId, $firstName, $lastName)
    {
        return LeadMember::join('box_facility', 'box_facility.box_facility_id', '=', 'lead_members.box_facility_id')
            ->where('email_address', $email)
            ->where('lead_members.box_facility_id', $boxFacilityId)
            ->where('box_facility.box_id', $boxId)
            ->where('lead_members.first_name', $firstName)
            ->where('lead_members.last_name', $lastName)
            ->get();
    }

    private function createLeadUserLocation($leadUser, $location): void
    {
        (new TenantUserService())->createUserFacilityMembership(
            $leadUser,
            $location,
            Carbon::parse($leadUser->created_on)
        );
    }

    private function setAliasEmail($emailAddress): string
    {
        $email = $emailAddress;
        $alias = random_int(100000, 999999);
        $prefix = substr($email, 0, strrpos($email, '@'));
        $domain = substr($email, strpos($email, '@') + 1);

        return $prefix.'+'.$alias.'@'.$domain;
    }
}

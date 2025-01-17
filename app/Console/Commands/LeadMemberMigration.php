<?php

namespace App\Console\Commands;

use App\Enums\Gender;
use App\Enums\PackageType;
use App\Enums\UserDebitStatus;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\ClassBooking;
use App\Models\ClassPackage;
use App\Models\DropInPackage;
use App\Models\DropInPackageClasses;
use App\Models\DropInPackageLeadMember;
use App\Models\LeadMember;
use App\Models\LeadWaivers;
use App\Models\Location;
use App\Models\LocationUser;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\UserInvoice;
use App\Models\UserPackage;
use App\Services\TenantUserService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

ini_set('memory_limit', '8G');

class LeadMemberMigration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:lead-member-migration';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        DB::table('user_types')->updateOrInsert(['user_type_desc' => 'Lead']);

        $leadMembers = LeadMember::query()
            ->leftJoin('users', function ($join) {
                $join->on('users.email', '=', 'lead_members.email_address')
                    ->whereNull('users.primary_user_account_id');
            })
            ->whereNotNull('box_facility_id')
            ->groupBy('lead_members.member_id')
            ->select('lead_members.*')
            ->addSelect('users.user_id as u_user_id')
            ->get();

        $this->createOrUpdateUserForLeads($leadMembers);
        $this->addUserIdToLeadWaiverTable();
        $this->updateLeadClassBookingWithUserId();

        $this->migrateDropInPackagesToPackages();
        $this->migrateDropInClassPackagesToClassToPackages();

        $this->createOrUpdateUserTenantAndLocation($leadMembers);
        $this->updateLeadInvoiceWithUserLocationId();
        $this->migrateDropInLeadPackagesToUserToPackages();
        $this->migrationUserToPackageIdToLeadInvoices();
    }

    private function createUser($leadUser): Model|Builder
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
            'email' => $leadUser->email_address,
            'mobile' => $leadUser->mobile_number,
            'created_on' => $leadUser->created_on,
            'updated_on' => $leadUser->updated_on,
        ]);
    }

    private function addUserIdToLeadWaiverTable(): void
    {

        $leadUsers = LeadMember::query()
            ->join('lead_waivers', 'lead_waivers.waiver_id', '=', 'lead_members.waiver_id')
            ->whereNotNull('lead_members.user_id')
            ->get();

        $this->info('Add user_id to Lead Waiver table');
        $progress = $this->output->createProgressBar(count($leadUsers));
        $progress->start();

        foreach ($leadUsers as $leadUser) {
            $leadWaiver = LeadWaivers::query()->where('waiver_id', $leadUser->waiver_id)->first();
            $leadWaiver?->update(['user_id' => $leadUser->user_id]);
            $progress->advance();
        }

        $progress->finish();
    }

    private function createOrUpdateUserForLeads($leadMembers): void
    {
        $this->info('Create or Update Lead User to User Table');
        $progress = $this->output->createProgressBar(count($leadMembers));

        $progress->start();
        foreach ($leadMembers as $leadMember) {
            if ($leadMember->u_user_id) {
                $leadMember->update(['user_id' => $leadMember->u_user_id]);
            } else {
                $user = User::query()->where('email', $leadMember->email_address)->first();

                if (! $user) {
                    $user = $this->createUser($leadMember);
                }

                $leadMember->update(['user_id' => $user->getKey()]);
            }
            $progress->advance();

        }
        $progress->finish();
    }

    private function createOrUpdateUserTenantAndLocation($leadMembers): void
    {
        $this->info('Create or Update Lead User Tenant and Location Table');
        $progress = $this->output->createProgressBar(count($leadMembers));

        $progress->start();
        foreach ($leadMembers as $leadMember) {
            $location = Location::query()->withoutGlobalScopes()->find($leadMember->box_facility_id);
            $tenant = Tenant::query()->withoutGlobalScopes()->find($location->box_id);
            $user = User::query()->withoutGlobalScopes()->find($leadMember->user_id);
            $memberDetails = [
                'notes' => $leadMember->notes ?? null,
            ];

            if (! $location || ! $tenant || ! $user) {
                continue;
            }

            $locationUser = LocationUser::query()
                ->withoutGlobalScopes()
                ->where('box_facility_id', $leadMember->box_facility_id)
                ->where('user_id', $leadMember->user_id)
                ->first();

            $userTenant = TenantUser::query()
                ->withoutGlobalScopes()
                ->where('box_id', $tenant->getKey())
                ->where('user_id', $leadMember->user_id)
                ->first();

            if (! $userTenant) {
                $memberDetails['lead_member_id'] = $leadMember->getKey();
                $memberDetails['deleted'] = $leadMember->deleted ? 1 : 0;
                (new TenantUserService())->createUserBoxMembership(
                    $user,
                    $tenant,
                    UserType::LEAD_MEMBER,
                    $memberDetails,
                    Carbon::parse($leadMember->created_on),
                    null,
                    null,
                    UserDebitStatus::NO_PAYMENT,
                    UserStatus::ACTIVE
                );
            }

            if (! $locationUser) {
                (new TenantUserService())->createUserFacilityMembership(
                    $user,
                    $location,
                    Carbon::parse($leadMember->created_on)
                );
            }

            $progress->advance();
        }
        $progress->finish();
    }

    private function updateLeadInvoiceWithUserLocationId(): void
    {
        $financeInvoices = UserInvoice::query()
            ->withoutGlobalScopes()
            ->whereNotNull('lead_member_id')
            ->whereNull('finance_invoices.user_to_facility_id')
            ->leftJoin('box_facility', 'box_facility.box_facility_id', '=', 'finance_invoices.box_facility_id')
            ->leftJoin('lead_members', 'finance_invoices.lead_member_id', '=', 'lead_members.member_id')
            ->join('user_to_facility', function ($join) {
                $join->on('finance_invoices.box_facility_id', '=', 'user_to_facility.box_facility_id')
                    ->on('user_to_facility.user_id', '=', 'lead_members.user_id');
            })
            ->select([
                'finance_invoices.invoice_id',
                'finance_invoices.box_facility_id',
                'box_facility.box_id',
                'lead_members.user_id',
                'user_to_facility.user_to_facility_id as utf',
            ])
            ->groupBy('finance_invoices.invoice_id')
            ->get();

        $this->info('Update lead invoice with utf_id');
        $progress = $this->output->createProgressBar(count($financeInvoices));
        $progress->start();

        foreach ($financeInvoices as $financeInvoice) {
            if ($financeInvoice->utf) {
                $financeInvoice->update([
                    'user_to_facility_id' => $financeInvoice->utf,
                ]);
                $progress->advance();
            }
        }
        $progress->finish();
    }

    private function updateLeadClassBookingWithUserId(): void
    {
        $classBookings = ClassBooking::query()
            ->whereNotNull('lead_member_id')
            ->whereNull('user_id')
            ->get();

        $this->info('Update Class Booking Table with Lead user_id');
        $progress = $this->output->createProgressBar(count($classBookings));
        $progress->start();

        foreach ($classBookings as $classBooking) {
            if ($classBooking->leadMember) {
                $classBooking->update(['user_id' => $classBooking->leadMember->user_id]);
                $progress->advance();
            }
        }

        $progress->finish();
    }

    private function migrateDropInPackagesToPackages(): void
    {
        DB::table('package_limit_types')->updateOrInsert([
            'package_limit_type_descr' => 'Drop In Package',
            'is_active' => 1,
        ]);

        $dropInPackages = DropInPackage::query()->get();

        $this->info('Migrate drop in packages table to packages table');
        $progress = $this->output->createProgressBar(count($dropInPackages));
        $progress->start();

        foreach ($dropInPackages as $dropInPackage) {
            Package::query()->firstOrCreate([
                'package_name' => $dropInPackage->name,
                'package_limit' => 1,
                'package_limit_type_id' => PackageType::DROP_IN,
                'package_price' => $dropInPackage->price,
                'package_descr' => $dropInPackage->description,
                'dt_added' => $dropInPackage->created_at,
                'dt_modified' => $dropInPackage->updated_at,
                'is_active' => $dropInPackage->is_active,
                'is_displayed' => 1,
                'is_display_on_buy_packages' => 1,
                'priority' => $dropInPackage->priority,
            ], [
                'drop_in_package_id' => $dropInPackage->id,
                'box_id' => $dropInPackage->box_id,
            ]);
            $progress->advance();
        }

        $progress->finish();
    }

    private function migrateDropInClassPackagesToClassToPackages(): void
    {
        $dropInClassPackages = DropInPackageClasses::query()
            ->join('packages', 'packages.drop_in_package_id', '=', 'drop_in_packages_to_classes.drop_in_package_id')
            ->join('classes', 'drop_in_packages_to_classes.class_id', '=', 'classes.class_id')
            ->select([
                'drop_in_packages_to_classes.class_id',
                'packages.package_id',
                'packages.drop_in_package_id',
                'classes.dt_added',
                'classes.dt_modified',
                'classes.is_active',
            ])
            ->get();

        $this->info('Migrate drop in class packages table to class packages table');
        $progress = $this->output->createProgressBar(count($dropInClassPackages));
        $progress->start();

        foreach ($dropInClassPackages as $dropInClassPackage) {
            ClassPackage::query()->updateOrInsert([
                'class_id' => $dropInClassPackage->class_id,
                'package_id' => $dropInClassPackage->package_id,
                'drop_in_package_id' => $dropInClassPackage->drop_in_package_id,
                'dt_added' => $dropInClassPackage->dt_added,
                'dt_modified' => $dropInClassPackage->dt_modified,
                'is_active' => $dropInClassPackage->is_active,
            ]);
            $progress->advance();
        }
        $progress->finish();
    }

    private function migrateDropInLeadPackagesToUserToPackages(): void
    {
        $dropInUsers = DropInPackageLeadMember::query()
            ->join('lead_members', 'drop_in_packages_to_lead_members.lead_member_id', '=', 'lead_members.member_id')
            ->join('packages', 'packages.drop_in_package_id', '=', 'drop_in_packages_to_lead_members.drop_in_package_id')
            ->join('drop_in_packages', 'drop_in_packages.id', '=', 'drop_in_packages_to_lead_members.drop_in_package_id')
            ->select(['lead_members.user_id', 'packages.package_id', 'drop_in_packages.created_at AS effective_date', 'sessions_remaining AS sessions_available'])
            ->get();

        $this->info('Migrate drop in lead packages table to user packages table');
        $progress = $this->output->createProgressBar(count($dropInUsers));
        $progress->start();

        foreach ($dropInUsers as $dropInUser) {
            UserPackage::query()->updateOrInsert([
                'user_id' => $dropInUser->user_id,
                'package_id' => $dropInUser->package_id,
                'effective_date' => $dropInUser->effective_date,
                'sessions_available' => $dropInUser->sessions_available,
                'deleted' => 0,
            ]);
            $progress->advance();
        }

        $progress->finish();
    }

    private function migrationUserToPackageIdToLeadInvoices(): void
    {
        $userPackages = DropInPackageLeadMember::query()
            ->select([
                'drop_in_packages_to_lead_members.lead_member_id',
                'drop_in_packages_to_lead_members.drop_in_package_id',
                'packages.package_id',
                'lead_members.user_id',
                'packages.box_id',
                'box_facility.box_id',
                'finance_invoices.invoice_id',
                'finance_invoices.box_facility_id',
                DB::raw('(
                    SELECT
                        user_to_package_id
                    FROM
                        user_to_package
                    WHERE
                        user_id = lead_members.user_id
                        AND package_id = packages.package_id
                    ORDER BY
                        user_to_package_id DESC
                    LIMIT 1) as user_to_package_id_insert'
                ),
            ])
            ->join('lead_members', 'lead_members.member_id', '=', 'drop_in_packages_to_lead_members.lead_member_id')
            ->join('packages', 'packages.drop_in_package_id', '=', 'drop_in_packages_to_lead_members.drop_in_package_id')
            ->join('finance_invoices', 'finance_invoices.invoice_id', '=', 'drop_in_packages_to_lead_members.invoice_id')
            ->join('box_facility', 'box_facility.box_facility_id', '=', 'finance_invoices.box_facility_id')
            ->get();

        $this->info('Migrate user packages to lead invoices');
        $progress = $this->output->createProgressBar(count($userPackages));
        $progress->start();

        foreach ($userPackages as $userPackage) {
            if ($userPackage->userInvoice) {
                $userPackage->userInvoice->update([
                    'user_to_package_id' => $userPackage->user_to_package_id_insert,
                ]);
                $progress->advance();
            }
        }

        $progress->finish();
    }
}

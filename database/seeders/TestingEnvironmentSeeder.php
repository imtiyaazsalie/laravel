<?php

namespace Database\Seeders;

use App\Enums\CoachType;
use App\Models\ClassCoach;
use App\Models\ClassDate;
use App\Models\ClassDay;
use App\Models\Classes;
use App\Models\ClassPackage;
use App\Models\Exercise;
use App\Models\LeadMember;
use App\Models\Location;
use App\Models\LocationUser;
use App\Models\Package;
use App\Models\Programme;
use App\Models\ProgrammePackageVisibility;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\UserPackage;
use App\Models\Wod;
use App\Models\WodCapture;
use Carbon\CarbonPeriod;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class TestingEnvironmentSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed test tenant and users
     */
    public function run(): void
    {
        $superAdmin = User::factory()->create([
            'name' => 'Super',
            'surname' => 'Admin',
            'email' => 'mark@boxchamp.co.za',
        ]);

        $headCoach = User::factory()->create([
            'name' => 'John',
            'surname' => 'Doe',
            'email' => 'john.doe@octivfitness.com',
        ]);

        $gym = Tenant::factory()->create([
            'box_desc' => 'Test Gym',
        ]);

        /**
         * Setup a role to attach permissions to on the fly.
         */
        setPermissionsTeamId($gym->getKey());

        $testRole = Role::create([
            'name' => 'Test Role',
            'team_id' => $gym->getKey(),
            'description' => 'This is a custom test role.',
        ]);

        /**
         * Head coach
         */
        $headCoachMembership = TenantUser::factory()->headCoach()->create([
            'box_id' => $gym->getKey(),
            'user_id' => $headCoach->getKey(),
        ]);

        $headCoachMembership->assignRole(
            Role::whereName(config('octiv.gym_super_admin_role'))->firstOrFail()
        );

        /**
         * Packages
         */
        $limitedPackage = Package::factory()->limited()->create([
            'box_id' => $gym->getKey(),
        ]);

        $weeklyPackage = Package::factory()->weekly()->create([
            'box_id' => $gym->getKey(),
        ]);

        $monthlyPackage = Package::factory()->monthly()->create([
            'box_id' => $gym->getKey(),
        ]);

        /**
         * Programmes and WODs
         */
        $programme = Programme::factory()->create([
            'box_id' => $gym->getKey(),
            'name' => 'Test Programme',
            'description' => 'Test programme description',
            'is_active' => true,
            'created_by_id' => $headCoach->getAuthIdentifier(),
        ]);

        $secondProgramme = Programme::factory()->create([
            'box_id' => $gym->getKey(),
            'name' => 'Second Test Programme',
            'description' => 'Second test programme description',
            'is_active' => true,
            'created_by_id' => $headCoach->getAuthIdentifier(),
        ]);

        ProgrammePackageVisibility::create([
            'programme_id' => $programme->getKey(),
            'package_id' => $limitedPackage->getKey(),
        ]);

        ProgrammePackageVisibility::create([
            'programme_id' => $programme->getKey(),
            'package_id' => $weeklyPackage->getKey(),
        ]);

        ProgrammePackageVisibility::create([
            'programme_id' => $programme->getKey(),
            'package_id' => $monthlyPackage->getKey(),
        ]);

        ProgrammePackageVisibility::create([
            'programme_id' => $secondProgramme->getKey(),
            'package_id' => $limitedPackage->getKey(),
        ]);

        // known wod for first programme
        $wod = Wod::factory()->create([
            'wod_name' => 'Test WOD',
            'wod_date' => today()->subDay(),
            'box_id' => $gym->getKey(),
            'programme_id' => $programme->getKey(),
        ]);

        // more wods for first programme
        $secondWod = Wod::factory()->create([
            'wod_name' => 'Second Test WOD',
            'wod_date' => today(),
            'box_id' => $gym->getKey(),
            'programme_id' => $programme->getKey(),
        ]);

        // wods for second programme
        $otherProgrammeWod = Wod::factory()->create([
            'box_id' => $gym->getKey(),
            'wod_date' => today()->subDay(),
            'programme_id' => $secondProgramme->getKey(),
        ]);

        $exercises = Exercise::factory(5)->create([
            'box_id' => $gym->getKey(),
        ]);

        /**
         * Create locations.
         */
        $location = Location::factory()->create([
            'box_id' => $gym->getKey(),
            'box_facility_name' => 'Test location',
        ]);

        $secondLocation = Location::factory()->create([
            'box_id' => $gym->getKey(),
            'box_facility_name' => 'Second test location',
        ]);

        /**
         * Head coach facility connections.
         */
        LocationUser::factory()->create([
            'box_facility_id' => $location->getKey(),
            'user_id' => $headCoach->getAuthIdentifier(),
        ]);

        LocationUser::factory()->create([
            'box_facility_id' => $secondLocation->getKey(),
            'user_id' => $headCoach->getAuthIdentifier(),
        ]);

        /**
         * Gym coach
         */
        $gymCoach = User::factory()->create([
            'name' => 'Jane',
            'surname' => 'Doe',
            'email' => 'jane.doe@octivfitness.com',
        ]);

        $gymCoachMembership = TenantUser::factory()->gymCoach()->create([
            'box_id' => $gym->getKey(),
            'user_id' => $gymCoach->getKey(),
        ]);

        $gymCoachMembership->assignRole($testRole);

        LocationUser::factory()->create([
            'box_facility_id' => $location->getKey(),
            'user_id' => $gymCoach->getAuthIdentifier(),
        ]);

        /**
         * Classes
         */
        $this->seedClasses($gym, $location, $headCoach, $gymCoach, [$monthlyPackage, $weeklyPackage, $limitedPackage]);

        $this->seedClasses($gym, $secondLocation, $headCoach, $gymCoach, [$monthlyPackage, $weeklyPackage, $limitedPackage]);

        /**
         * Members of primary location
         */
        $member = User::factory()->create([
            'name' => 'Jimi',
            'surname' => 'Hendrix',
            'email' => 'jimi.hendrix@octivfitness.com',
        ]);

        TenantUser::factory()->member()->create([
            'box_id' => $gym->getKey(),
            'user_id' => $member->getAuthIdentifier(),
        ])->assignRole($testRole);

        LocationUser::factory()->create([
            'box_facility_id' => $location->getKey(),
            'user_id' => $member->getAuthIdentifier(),
        ]);

        //monthly package
        UserPackage::factory()->create([
            'user_id' => $member->getAuthIdentifier(),
            'package_id' => $monthlyPackage->getKey(),
        ]);

        $member = User::factory()->create([
            'name' => 'Bob',
            'surname' => 'Dylan',
            'email' => 'bob.dylan@octivfitness.com',
        ]);

        TenantUser::factory()->member()->create([
            'box_id' => $gym->getKey(),
            'user_id' => $member->getAuthIdentifier(),
        ])->assignRole($testRole);

        LocationUser::factory()->create([
            'box_facility_id' => $location->getKey(),
            'user_id' => $member->getAuthIdentifier(),
        ]);

        //monthly package
        UserPackage::factory()->create([
            'user_id' => $member->getAuthIdentifier(),
            'package_id' => $monthlyPackage->getKey(),
        ]);

        $member = User::factory()->create([
            'name' => 'BB',
            'surname' => 'King',
            'email' => 'bb.king@octivfitness.com',
        ]);

        TenantUser::factory()->member()->create([
            'box_id' => $gym->getKey(),
            'user_id' => $member->getAuthIdentifier(),
        ])->assignRole($testRole);

        LocationUser::factory()->create([
            'box_facility_id' => $location->getKey(),
            'user_id' => $member->getAuthIdentifier(),
        ]);

        //limited package
        UserPackage::factory()->create([
            'user_id' => $member->getAuthIdentifier(),
            'package_id' => $limitedPackage->getKey(),
        ]);

        /**
         * Secondary location member
         */
        $member = User::factory()->create([
            'name' => 'Jim',
            'surname' => 'Morrison',
            'email' => 'jim.morrison@octivfitness.com',
        ]);

        TenantUser::factory()->member()->create([
            'box_id' => $gym->getKey(),
            'user_id' => $member->getAuthIdentifier(),
        ])->assignRole($testRole);

        LocationUser::factory()->create([
            'box_facility_id' => $secondLocation->getKey(),
            'user_id' => $member->getAuthIdentifier(),
        ]);

        UserPackage::factory()->create([
            'user_id' => $member->getAuthIdentifier(),
            'package_id' => $limitedPackage->getKey(),
        ]);

        /**
         * Lead members
         */
        LeadMember::factory()->create([
            'first_name' => 'Peter',
            'last_name' => 'Tosh',
            'email_address' => 'peter.tosh@octivfitness.com',
            'box_facility_id' => $location->getKey(),
        ]);

        LeadMember::factory()->create([
            'first_name' => 'Dolly',
            'last_name' => 'Parton',
            'email_address' => 'dolly.parton@octivfitness.com',
            'box_facility_id' => $secondLocation->getKey(),
        ]);

        $this->createTestingTenant();
    }

    protected function createTestingTenant()
    {
        /**
         * Gym setup
         */
        $headCoach = User::factory()->create();

        $gym = Tenant::factory()->create([
            'box_desc' => 'Second Test Box',
        ]);

        $headCoachMembership = TenantUser::factory()->headCoach()->create([
            'box_id' => $gym->getKey(),
            'user_id' => $headCoach->getAuthIdentifier(),
        ]);

        $headCoachMembership->assignRole(
            Role::whereName(config('octiv.gym_super_admin_role'))->firstOrFail()
        );

        /**
         * Create locations.
         */
        $firstLocation = Location::factory()->create([
            'box_id' => $gym->getKey(),
            'box_facility_name' => 'Second Test Box First Location',
        ]);

        $secondLocation = Location::factory()->create([
            'box_id' => $gym->getKey(),
        ]);

        /**
         * Gym coach
         */
        $gymCoach = User::factory()->create();

        $gymCoachMembership = TenantUser::factory()->gymCoach()->create([
            'box_id' => $gym->getKey(),
            'user_id' => $headCoach->getAuthIdentifier(),
        ]);

        LocationUser::factory()->create([
            'box_facility_id' => $firstLocation->getKey(),
            'user_id' => $gymCoach->getAuthIdentifier(),
        ]);

        /**
         * Packages
         */
        $limitedPackage = Package::factory()->limited()->create([
            'box_id' => $gym->getKey(),
        ]);

        $weeklyPackage = Package::factory()->weekly()->create([
            'box_id' => $gym->getKey(),
        ]);

        $monthlyPackage = Package::factory()->monthly()->create([
            'box_id' => $gym->getKey(),
        ]);

        /**
         * Classes
         */
        $this->seedClasses($gym, $firstLocation, $headCoach, $gymCoach, [$limitedPackage, $monthlyPackage, $weeklyPackage]);

        /**
         * Programmes
         */
        $programmes = Programme::factory(5)->create([
            'box_id' => $gym->getKey(),
            'created_by_id' => $headCoach->getAuthIdentifier(),
        ]);

        foreach ($programmes as $programme) {

            ProgrammePackageVisibility::create([
                'programme_id' => $programme->getKey(),
                'package_id' => $weeklyPackage->getKey(),
            ]);

            ProgrammePackageVisibility::create([
                'programme_id' => $programme->getKey(),
                'package_id' => $monthlyPackage->getKey(),
            ]);
        }

        /**
         * Gym members
         */
        $tenantUsers = collect();

        $users = User::factory(5)->create();

        foreach ($users as $user) {

            $tenantUsers->add(
                TenantUser::factory()->member()->create([
                    'box_id' => $gym->getKey(),
                    'user_id' => $user->getAuthIdentifier(),
                ])
            );

            LocationUser::factory()->create([
                'box_facility_id' => $firstLocation->getKey(),
                'user_id' => $user->getAuthIdentifier(),
            ]);

            UserPackage::factory()->create([
                'user_id' => $user->getAuthIdentifier(),
                'package_id' => $monthlyPackage->getKey(),
            ]);
        }

        foreach ($programmes as $programme) {
            $wods = Wod::factory(5)->create([
                'box_id' => $gym->getKey(),
                'programme_id' => $programme->getKey(),
            ]);

            foreach ($wods as $wod) {

                WodCapture::factory(5)->create([
                    'wod_id' => $wod->getKey(),
                    'user_id' => $tenantUsers->random()->first()->user_id,
                ]);

            }
        }

        /**
         * Lead members
         */
        LeadMember::factory()->create([
            'box_facility_id' => $firstLocation->getKey(),
        ]);

        LeadMember::factory()->create([
            'box_facility_id' => $secondLocation->getKey(),
        ]);
    }

    protected function seedClasses(Tenant $gym, Location $location, User $headCoach, User $supportingCoach, array $packages)
    {
        $classData = [
            'box_id' => $gym->getKey(),
            'box_facility_id' => $location->getKey(),
        ];

        $this->seedOnceOffClassData(
            Classes::factory()->capturedBy($headCoach)->create($classData),
            $headCoach,
            $supportingCoach,
            $packages
        );

        $this->seedOnceOffClassData(
            Classes::factory()->session()->capturedBy($headCoach)->create($classData),
            $headCoach,
            $supportingCoach,
            $packages
        );

        $this->seedOnceOffClassData(
            Classes::factory()->free()->capturedBy($headCoach)->create($classData),
            $headCoach,
            $supportingCoach,
            $packages
        );

        $this->seedOnceOffClassData(
            Classes::factory()->virtual()->capturedBy($headCoach)->create($classData),
            $headCoach,
            $supportingCoach,
            $packages
        );

        $this->seedOnceOffClassData(
            Classes::factory()->inactive()->capturedBy($headCoach)->create($classData),
            $headCoach,
            $supportingCoach,
            $packages
        );

        $this->seedOnceOffClassData(
            Classes::factory()->notVisibleInApp()->capturedBy($headCoach)->create($classData),
            $headCoach,
            $supportingCoach,
            $packages
        );

        /**
         * Recurring class
         */
        $class = Classes::factory()->recurring($endDate = today()->addWeeks(2))->capturedBy($headCoach)->create($classData);

        /**
         * Dates
         */
        $daysOfWeek = ClassDay::whereKey([1, 2, 4, 5])->get();

        $period = CarbonPeriod::start(now()->addDays(7))->end($endDate)
            ->filter(fn ($date) => in_array($date->dayOfWeekIso, $daysOfWeek->modelKeys()));

        foreach ($daysOfWeek as $day) {
            DB::table('class_to_days')->insert([
                'class_id' => $class->getKey(),
                'day_id' => $day->getKey(),
                'is_active' => true,
                'dt_added' => now(),
                'dt_modified' => now(),
            ]);
        }

        foreach ($period as $date) {
            ClassDate::create([
                'class_id' => $class->getKey(),
                'class_date' => $date,
                'is_active' => true,
            ]);
        }

        $this->seedCommonClassData($class, $headCoach, $supportingCoach, $packages);

    }

    public function seedCommonClassData(Classes $class, User $headCoach, User $supportingCoach, array $packages)
    {
        /**
         * Coaches
         */
        ClassCoach::create([
            'class_id' => $class->getKey(),
            'coach_id' => $headCoach->getAuthIdentifier(),
            'coach_type_id' => CoachType::HEAD_COACH->value,
            'is_active' => true,
        ]);

        ClassCoach::create([
            'class_id' => $class->getKey(),
            'coach_id' => $supportingCoach->getAuthIdentifier(),
            'coach_type_id' => CoachType::SUPPORTING_COACH->value,
            'is_active' => true,
        ]);

        /**
         * Class package links
         */
        foreach ($packages as $package) {
            ClassPackage::create([
                'class_id' => $class->getKey(),
                'package_id' => $package->getKey(),
            ]);
        }
    }

    protected function seedOnceOffClassData(Classes $class, User $headCoach, User $supportingCoach, array $packages)
    {
        /**
         * Dates
         */
        ClassDate::create([
            'class_id' => $class->getKey(),
            'class_date' => now()->addDays(7),
            'is_active' => true,
        ]);

        $this->seedCommonClassData($class, $headCoach, $supportingCoach, $packages);
    }
}

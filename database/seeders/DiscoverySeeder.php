<?php

namespace Database\Seeders;

use App\Enums\ClassType;
use App\Enums\CoachType;
use App\Enums\Day;
use App\Enums\PackageType;
use App\Enums\PaymentGateway;
use App\Enums\UserDebitStatus;
use App\Enums\UserType;
use App\Models\Address;
use App\Models\BroadcastMessages;
use App\Models\ClassCoach;
use App\Models\ClassDay;
use App\Models\Classes;
use App\Models\ClassPackage;
use App\Models\ClassToDay;
use App\Models\CrmSetting;
use App\Models\HealthCareProvider;
use App\Models\Location;
use App\Models\LocationAmenity;
use App\Models\LocationCategory;
use App\Models\LocationHealthProvider;
use App\Models\LocationUser;
use App\Models\OperatingHour;
use App\Models\Package;
use App\Models\Programme;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\TenantUserService;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Symfony\Component\Uid\Uuid;

class DiscoverySeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::factory()->create([
            'name' => 'Super',
            'surname' => 'Admin',
            'email' => 'super-admin@octivfitness.com',
            'user_type_id' => UserType::SUPER_ADMINISTRATOR,
        ]);

        $templateDataForTenants = [
            [
                'name' => 'CrossFit Masiphumelele',
                'slug' => str('CrossFit Masiphumelele')->slug(),
                'locations' => [
                    [
                        'name' => 'Kommetjie',
                        'categoryId' => 1,
                        'attendanceCode' => fake()->uuid,
                        'numberOfMembers' => 10,
                        'address' => [
                            'streetAddress' => '10 Wildevloevlie Road, Kommetjie',
                            'city' => 'Cape Town',
                            'stateProvinceRegion' => 'Western Cape',
                            'postalCode' => '7975',
                            'countryCode' => 'ZA',
                            'latitude' => '18.36410467',
                            'longitude' => '-34.13569764',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'CTG CrossFit',
                'slug' => str('CTG CrossFit')->slug(),
                'locations' => [
                    [
                        'name' => 'Pineslopes',
                        'categoryId' => 1,
                        'attendanceCode' => fake()->uuid,
                        'numberOfMembers' => 10,
                        'address' => [
                            'streetAddress' => '18B Design and Decore Centre, Fourways',
                            'city' => 'Johannesburg',
                            'stateProvinceRegion' => 'Gauteng',
                            'postalCode' => '2194',
                            'countryCode' => 'ZA',
                            'latitude' => '28.01463737',
                            'longitude' => '-26.01962427',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Fitcube SA',
                'slug' => str('Fitcube SA')->slug(),
                'locations' => [
                    [
                        'name' => 'Pomona',
                        'categoryId' => 2,
                        'attendanceCode' => fake()->uuid,
                        'numberOfMembers' => 10,
                        'address' => [
                            'streetAddress' => '105 EP Malan rd, Pomona',
                            'city' => 'Johannesburg',
                            'stateProvinceRegion' => 'Gauteng',
                            'postalCode' => '1618',
                            'countryCode' => 'ZA',
                            'latitude' => '28.26283257',
                            'longitude' => '-26.1022269',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Grip and Rip Fitness Crossfit',
                'slug' => str('Grip and Rip Fitness Crossfit')->slug(),
                'locations' => [
                    [
                        'name' => 'Secunda',
                        'categoryId' => 2,
                        'attendanceCode' => fake()->uuid,
                        'numberOfMembers' => 10,
                        'address' => [
                            'streetAddress' => '122 Danie Theron, Extension 22',
                            'city' => 'Secunda',
                            'stateProvinceRegion' => 'Mpumalanga',
                            'postalCode' => '2302',
                            'countryCode' => 'ZA',
                            'latitude' => '29.1859645',
                            'longitude' => '-26.49981905',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'House of Yoga',
                'slug' => str('House of Yoga')->slug(),
                'locations' => [
                    [
                        'name' => 'Claremont',
                        'categoryId' => 4,
                        'attendanceCode' => fake()->uuid,
                        'numberOfMembers' => 10,
                        'address' => [
                            'streetAddress' => '43 Heather Street, Claremont',
                            'city' => 'Cape Town',
                            'stateProvinceRegion' => 'Western Cape',
                            'postalCode' => '7708',
                            'countryCode' => 'ZA',
                            'latitude' => '18.475054',
                            'longitude' => '-33.98288111',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'HS Fitness Hub',
                'slug' => str('HS Fitness Hub')->slug(),
                'locations' => [
                    [
                        'name' => 'Mouille Point',
                        'categoryId' => 10,
                        'attendanceCode' => fake()->uuid,
                        'numberOfMembers' => 10,
                        'address' => [
                            'streetAddress' => '169 Beach rd, Mouille Point',
                            'city' => 'Cape Town',
                            'stateProvinceRegion' => 'Western Cape',
                            'postalCode' => '8005',
                            'countryCode' => 'ZA',
                            'latitude' => '18.399101',
                            'longitude' => '-33.903995',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Life Retreat Studio',
                'slug' => str('Life Retreat Studio')->slug(),
                'locations' => [
                    [
                        'name' => 'Somerset West',
                        'categoryId' => 4,
                        'attendanceCode' => fake()->uuid,
                        'numberOfMembers' => 10,
                        'address' => [
                            'streetAddress' => 'Lourensford Road, Somerset West',
                            'city' => 'Cape Town',
                            'stateProvinceRegion' => 'Western Cape',
                            'postalCode' => '7130',
                            'countryCode' => 'ZA',
                            'latitude' => '18.89421402',
                            'longitude' => '-34.06797498',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Prime Human Performance Institute',
                'slug' => str('Prime Human Performance Institute')->slug(),
                'locations' => [
                    [
                        'name' => 'Durban',
                        'categoryId' => 9,
                        'attendanceCode' => fake()->uuid,
                        'numberOfMembers' => 10,
                        'address' => [
                            'streetAddress' => '44 Isaiah Ntshangasse Rd, Durban',
                            'city' => 'Durban',
                            'stateProvinceRegion' => 'Kwazulu Natal',
                            'postalCode' => '4023',
                            'countryCode' => 'ZA',
                            'latitude' => '18.89421402',
                            'longitude' => '-34.06797498',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Reikiworx Pilates & Rehab Centre',
                'slug' => str('Reikiworx Pilates & Rehab Centre')->slug(),
                'locations' => [
                    [
                        'name' => 'Rustenburg',
                        'categoryId' => 4,
                        'attendanceCode' => fake()->uuid,
                        'numberOfMembers' => 10,
                        'address' => [
                            'streetAddress' => 'Plot 415 Portion 305JQ, Waterkloof',
                            'city' => 'Rustenburg',
                            'stateProvinceRegion' => 'North West',
                            'postalCode' => '299',
                            'countryCode' => 'ZA',
                            'latitude' => '27.26129784',
                            'longitude' => '-25.72417166',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'RTF CrossFit Krugersdorp',
                'slug' => str('RTF CrossFit Krugersdorp')->slug(),
                'locations' => [
                    [
                        'name' => 'Krugersdorp',
                        'categoryId' => 1,
                        'attendanceCode' => fake()->uuid,
                        'numberOfMembers' => 10,
                        'address' => [
                            'streetAddress' => '4 Louis Friedman Street, Factoria',
                            'city' => 'Factoria',
                            'stateProvinceRegion' => 'Gauteng',
                            'postalCode' => '1740',
                            'countryCode' => 'ZA',
                            'latitude' => '27.81562631',
                            'longitude' => '-26.11844754',
                        ],
                    ],
                ],
            ],

        ];

        foreach ($templateDataForTenants as $templateDataForTenant) {
            $data = $this->createTenant($templateDataForTenant);

            $tenant = $data['tenant'];
            $headCoachTenantUser = $data['headCoachTenantUser'];
            $gymCoachTenantUser = $data['gymCoachTenantUser'];

            $this->createTenantLocations($tenant, $templateDataForTenant['locations']);
            $this->createPackages($tenant);
            $this->createProgrammes($tenant, $headCoachTenantUser->user);
            $this->createClasses($tenant, $headCoachTenantUser->user, $gymCoachTenantUser->user);
            $this->createStaffAndMembersForTenant($tenant, $templateDataForTenant);
        }

        // $this->createUsersWithMoreThanOneUTB();
    }

    private function createTenant(array $tenantTemplate): array
    {
        $tenant = Tenant::factory()->create([
            'box_desc' => $tenantTemplate['name'],
        ]);

        Setting::create([
            'box_id' => $tenant->getKey(),
        ]);

        $headCoach = User::factory()->create([
            'name' => 'Owner',
            'surname' => $tenantTemplate['name'],
            'email' => "tech+{$tenantTemplate['slug']}-owner@octivfitness.com",
        ]);

        $headCoachTenantUser = TenantUser::factory()->headCoach()->create([
            'box_id' => $tenant->getKey(),
            'user_id' => $headCoach->getKey(),
        ]);

        $gymCoach = User::factory()->create([
            'name' => 'Instructor',
            'surname' => $tenantTemplate['name'],
            'email' => "tech+{$tenantTemplate['slug']}-instructor@octivfitness.com",
        ]);

        $gymCoachTenantUser = TenantUser::factory()->gymCoach()->create([
            'box_id' => $tenant->getKey(),
            'user_id' => $gymCoach->getKey(),
        ]);

        BroadcastMessages::factory()->create([
            'box_id' => $tenant->getKey(),
        ]);

        return ['tenant' => $tenant, 'headCoachTenantUser' => $headCoachTenantUser, 'gymCoachTenantUser' => $gymCoachTenantUser];
    }

    private function createTenantLocations(Tenant $tenant, $locationsTemplate): void
    {
        $attendanceCodeExpiryDate = today()->addYears(5);

        foreach ($locationsTemplate as $locationTemplate) {
            $location = Location::factory()->create([
                'box_id' => $tenant->getKey(),
                'box_facility_name' => $locationTemplate['name'],
                'prefix' => str($locationTemplate['name'])->substr(0, 3)->upper(),
                'business_name' => $tenant->box_desc,
                'category_id' => LocationCategory::find($locationTemplate['categoryId']),
                'timezone_id' => $tenant->timezone_id,
                'payment_gateway_id' => PaymentGateway::NO_GATEWAY,
                'attendance_code' => Uuid::fromString($locationTemplate['attendanceCode'])->toBinary(),
                'attendance_code_expires_on' => $attendanceCodeExpiryDate,
            ]);

            Address::factory()->create([
                'location_id' => $location->getKey(),
                'street_address' => $locationTemplate['address']['streetAddress'],
                'city' => $locationTemplate['address']['city'],
                'state_province_region' => $locationTemplate['address']['stateProvinceRegion'],
                'postal_code' => $locationTemplate['address']['postalCode'],
                'country_code' => $locationTemplate['address']['countryCode'],
                'latitude' => $locationTemplate['address']['latitude'],
                'longitude' => $locationTemplate['address']['longitude'],
            ]);

            for ($day = 1; $day < 7; $day++) {
                OperatingHour::query()->create([
                    'location_id' => $location->getKey(),
                    'day' => $day,
                    'opening_time' => '07:00',
                    'closing_time' => '17:00',
                ]);
            }

            OperatingHour::query()->create([
                'location_id' => $location->getKey(),
                'day' => Day::PUBLIC_HOLIDAY->value,
                'opening_time' => '09:00',
                'closing_time' => '12:00',
            ]);

            $numbers = range(1, 12);

            // Shuffle the array to randomize the order
            shuffle($numbers);

            // Use array_slice to get the first 6 elements (randomly selected)
            $randomNumbers = array_slice($numbers, 0, 6);

            foreach ($randomNumbers as $amenityId) {
                LocationAmenity::create([
                    'location_id' => $location->getKey(),
                    'amenity_id' => $amenityId,
                ]);
            }

            LocationHealthProvider::create([
                'location_id' => $location->getKey(),
                'health_provider_id' => HealthCareProvider::first()->getKey(),
            ]);

            CrmSetting::factory()->create([
                'sender_name' => $tenant->name,
                'location_id' => $location->getKey(),
            ]);
        }
    }

    private function createPackages(Tenant $tenant): void
    {
        $packagesData = [
            /* [
                'name' => 'Unlimited',
                'limit' => 0,
                'limitTypeId' => 1,
                'price' => 2000,
                'topUpPrice' => null,
                'default_period_in_months' => 12,
                'default_period_interval' => 'P12M',
            ],*/
            [
                'name' => '15 Sessions Per Month',
                'limit' => 15,
                'limitTypeId' => 1,
                'price' => 1500,
                'topUpPrice' => 150,
                'default_period_in_months' => 3,
                'default_period_interval' => 'P3M',
            ],
            [
                'name' => '3 Sessions Per Week',
                'limit' => 3,
                'limitTypeId' => 2,
                'price' => 1200,
                'topUpPrice' => 120,
                'default_period_in_months' => null,
                'default_period_interval' => 'P1W',
            ],
            /*[
                'name' => '1 Session',
                'limit' => 1,
                'limitTypeId' => 3,
                'price' => 150,
                'healthProviderPrice' => 100,
                'topUpPrice' => null,
                'default_period_in_months' => null,
                'default_period_interval' => 'P1W',
            ],*/
            [
                'name' => '5 Sessions',
                'limit' => 5,
                'limitTypeId' => 3,
                'price' => 750,
                'healthProviderPrice' => 500,
                'topUpPrice' => null,
                'default_period_in_months' => 3,
                'default_period_interval' => 'P3M',
            ],
            [
                'name' => '10 Sessions',
                'limit' => 10,
                'limitTypeId' => 3,
                'price' => 1500,
                'healthProviderPrice' => 1000,
                'topUpPrice' => 150,
                'default_period_in_months' => 12,
                'default_period_interval' => 'P12M',
            ],
        ];

        foreach ($packagesData as $packageData) {
            Package::factory()->create([
                'box_id' => $tenant->getKey(),
                'package_name' => $packageData['name'],
                'package_limit' => $packageData['limit'],
                'package_limit_type_id' => PackageType::from($packageData['limitTypeId']),
                'package_price' => $packageData['price'],
                'package_topup_price' => $packageData['topUpPrice'],
                'health_provider_price' => $packageData['healthProviderPrice'] ?? null,
                'default_period_in_months' => $packageData['default_period_in_months'],
                'default_period_interval' => $packageData['default_period_interval'],
            ]);
        }
    }

    private function createProgrammes(Tenant $tenant, User $headCoach): void
    {
        $programmeNames = ['Crossfit', 'Boxing', 'Yoga'];

        foreach ($programmeNames as $programmeName) {
            Programme::factory()->create([
                'box_id' => $tenant->getKey(),
                'name' => $programmeName,
                'is_active' => true,
                'created_by_id' => $headCoach->getAuthIdentifier(),
            ]);
        }
    }

    private function createClasses(Tenant $tenant, User $headCoach, User $supportingCoach): void
    {
        $packages = $tenant->packages;

        $classesData = [
            [
                'class_type_id' => ClassType::RECURRING,
                'class_name' => 'Morning',
                'start_time' => Carbon::parse('08:00'),
                'end_time' => Carbon::parse('09:00'),
                'class_limit' => 15,
                'booking_threshold' => 60,
                'cancellation_threshhold' => 30,
                'meeting_url' => null,
                'recurring_end_date' => null,
            ],
            [
                'class_type_id' => ClassType::RECURRING,
                'class_name' => 'Midday',
                'start_time' => Carbon::parse('12:00'),
                'end_time' => Carbon::parse('13:00'),
                'class_limit' => 10,
                'booking_threshold' => 60,
                'cancellation_threshhold' => 30,
                'meeting_url' => null,
                'recurring_end_date' => null,
            ],
            [
                'class_type_id' => ClassType::RECURRING,
                'class_name' => 'Afternoon',
                'start_time' => Carbon::parse('17:00'),
                'end_time' => Carbon::parse('18:00'),
                'class_limit' => 6,
                'booking_threshold' => 60,
                'cancellation_threshhold' => 30,
                'meeting_url' => null,
                'recurring_end_date' => null,
            ],
        ];

        foreach ($classesData as $index => $classData) {
            foreach (Location::where('box_id', $tenant->getKey())->get() as $location) {
                $class = Classes::factory()->capturedBy($headCoach)->create(array_merge($classData, ['box_id' => $tenant->getKey(), 'box_facility_id' => $location->getKey()]));

                ClassCoach::create([
                    'class_id' => $class->getKey(),
                    'coach_id' => $headCoach->getkey(),
                    'coach_type_id' => CoachType::HEAD_COACH,
                    'is_active' => true,
                ]);

                if ($index % 2 === 0) {
                    ClassCoach::create([
                        'class_id' => $class->getKey(),
                        'coach_id' => $supportingCoach->getkey(),
                        'coach_type_id' => CoachType::SUPPORTING_COACH,
                        'is_active' => true,
                    ]);
                }

                foreach ($packages as $package) {
                    ClassPackage::create([
                        'class_id' => $class->getKey(),
                        'package_id' => $package->getKey(),
                    ]);
                }

                $daysOfTheWeek = ClassDay::whereKey([1, 2, 3, 4, 5, 6])->get();

                foreach ($daysOfTheWeek as $dayOfTheWeek) {
                    ClassToDay::insert([
                        'class_id' => $class->getKey(),
                        'day_id' => $dayOfTheWeek->getKey(),
                        'is_active' => true,
                        'dt_added' => now(),
                        'dt_modified' => now(),
                    ]);
                }

                $class->ensureClassDates();
            }
        }
    }

    private function createStaffAndMembersForTenant(Tenant $tenant, array $tenantTemplate): void
    {
        $tenantUserService = (new TenantUserService());
        $packages = $tenant->packages;
        $programmes = $tenant->programmes;

        $tenantAdmin = User::factory()->create([
            'name' => 'Admin',
            'surname' => $tenantTemplate['name'],
            'email' => "tech+{$tenantTemplate['slug']}-admin@octivfitness.com",
        ]);

        TenantUser::factory()->tenantAdmin()->create([
            'box_id' => $tenant->getKey(),
            'user_id' => $tenantAdmin->getKey(),
        ]);

        foreach ($tenant->locations as $index => $location) {
            $locationAdmin = User::factory()->create([
                'name' => 'Location Admin',
                'surname' => $tenantTemplate['name'],
                'email' => "tech+{$tenantTemplate['slug']}-location-admin@octivfitness.com",
            ]);

            TenantUser::factory()->locationAdmin()->create([
                'box_id' => $tenant->getKey(),
                'user_id' => $locationAdmin->getKey(),
            ]);

            LocationUser::factory()->create([
                'box_facility_id' => $location->getKey(),
                'user_id' => $locationAdmin->getKey(),
            ]);

            $numberOfMembers = $tenantTemplate['locations'][$index]['numberOfMembers'];

            for ($i = 1; $i <= $numberOfMembers; $i++) {
                $gymMember = User::factory()->create([
                    'email' => "tech+{$tenantTemplate['slug']}-member-$i@octivfitness.com",
                ]);

                $tenantUserService->createGymMemberBoxMembership($gymMember, UserType::GYM_MEMBER, [
                    'programme_id' => $programmes->random()->getKey(),
                    'package_id' => $packages->random()->getKey(),
                    'package_start_date' => Carbon::today()->subDays(rand(0, 365)),
                    'payment_details' => [
                        'debit_status_id' => UserDebitStatus::CASH->value,
                        'invoicing_type' => 'facilityInvoicing',
                        'auto_invoicing_day' => null,
                        'auto_invoicing_due_day' => null,
                    ],
                    'discount_details' => null,
                    'contract_details' => [
                        'start_date' => today(),
                        'end_date' => today()->addYear(),
                    ],
                ], $tenant, $location);
            }
        }
    }
}

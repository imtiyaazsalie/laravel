<?php

namespace Database\Seeders\Discovery;

use App\Enums\ClassType;
use App\Enums\CoachType;
use App\Enums\Day;
use App\Enums\PaymentGateway;
use App\Models\Address;
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
use App\Models\OperatingHour;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Symfony\Component\Uid\Uuid;

class ThirteenLocationsSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {

        $tenant = Tenant::query()->find(1);
        $headCoachTenantUser = TenantUser::query()->find(1);
        $gymCoachTenantUser = TenantUser::query()->find(2);

        $locations = [];

        for ($x = 0; $x <= 13; $x++) {
            $locations[] = [
                'name' => fake()->city,
                'categoryId' => 1,
                'attendanceCode' => fake()->uuid,
                'numberOfMembers' => 10,
                'address' => [
                    'streetAddress' => fake()->streetAddress,
                    'city' => 'Cape Town',
                    'stateProvinceRegion' => 'Western Cape',
                    'postalCode' => fake()->postcode,
                    'countryCode' => 'ZA',
                    'latitude' => fake()->latitude,
                    'longitude' => fake()->longitude,
                ],
            ];
        }

        $this->createTenantLocations($tenant, $locations);
        $this->createClasses($tenant, $headCoachTenantUser->user, $gymCoachTenantUser->user);
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
}

<?php

namespace Database\Seeders;

use App\Models\LocationCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LocationCategoriesSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        LocationCategory::insert([
            [
                'box_facility_category_id' => 1,
                'name' => 'CrossFit',
            ],
            [
                'box_facility_category_id' => 2,
                'name' => 'Functional Fitness',
            ],
            [
                'box_facility_category_id' => 3,
                'name' => 'Combat Sport',
            ],
            [
                'box_facility_category_id' => 4,
                'name' => 'Yoga/Pilates',
            ],
            [
                'box_facility_category_id' => 5,
                'name' => 'EMS',
            ],
            [
                'box_facility_category_id' => 6,
                'name' => 'Rebound/ Bounce',
            ],
            [
                'box_facility_category_id' => 7,
                'name' => 'Dance',
            ],
            [
                'box_facility_category_id' => 8,
                'name' => 'Other',
            ],
            [
                'box_facility_category_id' => 9,
                'name' => 'Gym',
            ],
            [
                'box_facility_category_id' => 10,
                'name' => 'Personal Training',
            ],
            [
                'box_facility_category_id' => 11,
                'name' => 'Outdoor Swimming and Hiking',
            ],
            [
                'box_facility_category_id' => 12,
                'name' => 'Virtual',
            ],
            [
                'box_facility_category_id' => 13,
                'name' => 'Sports Clubs',
            ],
            [
                'box_facility_category_id' => 14,
                'name' => 'Rehab',
            ],
        ]);
    }
}

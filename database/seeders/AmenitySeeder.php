<?php

namespace Database\Seeders;

use App\Models\Amenity;
use Illuminate\Database\Seeder;

class AmenitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $amenities = [
            [
                'name' => 'Lockers',
            ],
            [
                'name' => 'Showers',
            ],
            [
                'name' => 'Sauna',
            ],
            [
                'name' => 'Steam Room',
            ],
            [
                'name' => 'Ice Bath',
            ],
            [
                'name' => 'Swimming Pool',
            ],
            [
                'name' => 'Wi-Fi Access',
            ],
            [
                'name' => '24-Hour Access',
            ],
            [
                'name' => 'Parking',
            ],
            [
                'name' => 'Coffee Shop',
            ],
            [
                'name' => 'Wheelchair Accessible',
            ],
            [
                'name' => 'Childcare',
            ],
        ];

        foreach ($amenities as $amenity) {
            Amenity::create($amenity);
        }
    }
}

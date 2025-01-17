<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClassBookingStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('class_booking_status')->insert([
            [
                'class_booking_status_id' => 1,
                'class_booking_status_descr' => 'Booked',
            ],
            [
                'class_booking_status_id' => 2,
                'class_booking_status_descr' => 'Cancelled',
            ],
            [
                'class_booking_status_id' => 3,
                'class_booking_status_descr' => 'Cancelled after threshhold',
            ],
            [
                'class_booking_status_id' => 4,
                'class_booking_status_descr' => 'Cancelled by coach',
            ],
            [
                'class_booking_status_id' => 5,
                'class_booking_status_descr' => 'No show',
            ],
            [
                'class_booking_status_id' => 6,
                'class_booking_status_descr' => 'Checked in',
            ],
        ]);
    }
}

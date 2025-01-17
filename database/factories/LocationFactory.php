<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\PaymentGateway;
use App\Models\Timezone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_gateway_id' => PaymentGateway::query()->inRandomOrder()->first()->getKey(),
            'timezone_id' => Timezone::query()->inRandomOrder()->first()->getKey(),
            'category_id' => 1,
            'prefix' => $this->faker->randomLetter().$this->faker->randomLetter().$this->faker->randomLetter(),
            'name' => $name = $this->faker->company(),
            'business_name' => $name,
            'vat' => null,
            'invoiceinfo' => null,
            'logofile' => null,
            'dt_added' => $this->faker->dateTimeThisDecade(),
            'dt_modified' => $this->faker->dateTimeThisYear(),
            'is_active' => true,
            'can_debit' => true,
            'invoice_code_index' => 1,
            'vat_percent' => '15.00',
            'view_class_bookings' => 1,
            'max_bookings_per_athlete_per_day' => 1000,
            'show_invoice_totals' => 1,
            'public_token' => null,
            'lead_redirect_url' => null,
            'lead_request_demo_redirect_url' => null,
            'sign_up_redirect_url' => null,
            'class_schedule_number_of_days' => 105,
            'attendance_code' => null,
            'attendance_code_expires_on' => null,
            'display_booking_details' => 1,
            'description' => $this->faker->sentence,
            'phone_number' => $this->faker->phoneNumber,
            'image_one' => null,
            'image_two' => null,
            'image_three' => null,
            'image_four' => null,
            'visible_in_app_sessions' => 1,
        ];
    }
}

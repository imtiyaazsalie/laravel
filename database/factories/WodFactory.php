<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Wod>
 */
class WodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // $box = Tenant::inRandomOrder()->first();

        return [
            // 'box_id' => $box->getKey(),
            // 'programme_id' => $box->programmesActive()->first()->getKey(),
            'wod_name' => 'Monday 1 December 2014 - TAGG',
            'nickname' => null,
            'descr' => $this->faker->sentence(),
            'img' => null,
            'warmup' => null,
            'cooldown' => null,
            'wod_date' => now(),
            'dt_added' => now(),
            'coach_notes' => null,
            'member_notes' => null,
        ];
    }
}

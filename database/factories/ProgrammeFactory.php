<?php

namespace Database\Factories;

use App\Models\Programme;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Programme>
 */
class ProgrammeFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $box = Tenant::inRandomOrder()->first();

        return [
            'box_id' => $box->getKey(),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'is_active' => $this->faker->boolean(90),
        ];
    }
}

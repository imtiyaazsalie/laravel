<?php

namespace Database\Factories;

use App\Models\ExerciseCategory;
use App\Models\MeasurementUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Exercise>
 */
class ExerciseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exercise_category_id' => ExerciseCategory::inRandomOrder()->first(),
            'measuring_unit_id' => MeasurementUnit::inRandomOrder()->first(),
            'is_benchmark' => 1,
            'exercise_name' => $this->faker->words(3, true),
            'exercise_desc' => $this->faker->sentence(),
            'rx_male' => '100',
            'rx_female' => '80',
            'dt_added' => now()->toDateTimeString(),
            'dt_modified' => now()->toDateTimeString(),
            'is_active' => $this->faker->boolean(90),
            'is_pb' => $this->faker->boolean(90),
            'resource_url' => null,
        ];
    }
}

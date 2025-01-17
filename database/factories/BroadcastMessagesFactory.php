<?php

namespace Database\Factories;

use App\Models\BroadcastMessages;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BroadcastMessages>
 */
class BroadcastMessagesFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'message' => $this->faker->paragraphs(3, true),
            'is_active' => true,
            'dt_modified' => $this->faker->dateTimeThisYear->format('Y-m-d H:i:s'),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Enums\PackageType;
use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'package_name' => $this->faker->words(3, true),
            'package_limit' => $this->faker->numberBetween(10, 20),
            'package_descr' => $this->faker->sentence(),
            'package_price' => $this->faker->randomFloat(2, 50, 250),
            'is_active' => true,
            'is_displayed' => true,
            'package_topup_price' => $this->faker->boolean() ? $this->faker->randomFloat(2, 50, 250) : null,
            'is_display_on_buy_packages' => true,
            'topup_stock_item_id' => null,
            //'default_period_in_months' => 12,
            //'default_period_interval' => 'P12M',
            'priority' => null,
            'is_hidden' => false,
        ];
    }

    /**
     * Limited package.
     */
    public function limited(): PackageFactory|Factory
    {
        return $this->state(fn (array $attributes) => [
            'package_limit_type_id' => PackageType::LIMITED,
            'default_period_in_months' => 12,
            'default_period_interval' => 'P12M',
        ]);
    }

    /**
     * Weekly package.
     */
    public function weekly(): PackageFactory|Factory
    {
        return $this->state(fn (array $attributes) => [
            'package_limit_type_id' => PackageType::WEEKLY,
            'default_period_in_months' => null,
            'default_period_interval' => 'P3W',
        ]);
    }

    /**
     * Monthly package.
     */
    public function monthly(): PackageFactory|Factory
    {
        return $this->state(fn (array $attributes) => [
            'package_limit_type_id' => PackageType::MONTHLY,
            'default_period_in_months' => 3,
            'default_period_interval' => 'P3M',
        ]);
    }
}

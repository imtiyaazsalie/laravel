<?php

namespace Database\Factories;

use App\Models\CrmSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CrmSetting>
 */
class CrmSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sms_status' => 'enabled',
            'email_status' => 'enabled',
            'email_signature' => '<p>'.$this->faker->words(3, true)."</p>\n",
            'test_mode' => false,
            'test_email_address' => null,
            'test_mobile' => null,
            'sms_sent' => 0,
            'email_sent' => 0,
            'reply_to' => $this->faker->email(),
            'file_relative_path' => null,
            'file_absolute_path' => null,
            'file_mime' => null,
            'file_name' => null,
            'sender_name' => $this->faker->company(),
        ];
    }
}

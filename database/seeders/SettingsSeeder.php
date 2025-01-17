<?php

namespace Database\Seeders;

use App\Models\CrmSetting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CrmSetting::insert([
            'setting_id' => 1,
            'sms_status' => 'enabled',
            'email_status' => 'enabled',
            'email_signature' => "<p>Team Octiv</p>\n",
            'test_mode' => false,
            'sms_sent' => 0,
            'email_sent' => 0,
            'reply_to' => 'noreply@octivfitness.com',
            'sender_name' => 'Octiv Fitness',
        ]);
    }
}

<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserStatusSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('user_status')->insert([
            [
                'user_status_id' => UserStatus::PENDING,
                'user_status_desc' => UserStatus::PENDING->toString(),
            ],
            [
                'user_status_id' => UserStatus::ACTIVE,
                'user_status_desc' => UserStatus::ACTIVE->toString(),
            ],
            [
                'user_status_id' => UserStatus::SUSPENDED,
                'user_status_desc' => UserStatus::SUSPENDED->toString(),
            ],
            [
                'user_status_id' => UserStatus::DEACTIVATED,
                'user_status_desc' => UserStatus::DEACTIVATED->toString(),
            ],
            [
                'user_status_id' => UserStatus::READY_FOR_TRANSFER,
                'user_status_desc' => UserStatus::READY_FOR_TRANSFER->toString(),
            ],
            [
                'user_status_id' => UserStatus::ON_HOLD,
                'user_status_desc' => UserStatus::ON_HOLD->toString(),
            ],
        ]);
    }
}

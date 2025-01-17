<?php

namespace Database\Seeders;

use App\Enums\UserType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('user_types')->insert([
            [
                'user_type_id' => UserType::SUPER_ADMINISTRATOR,
                'user_type_desc' => UserType::SUPER_ADMINISTRATOR->toString(),
            ],
            [
                'user_type_id' => UserType::HEAD_COACH,
                'user_type_desc' => UserType::HEAD_COACH->toString(),
            ],
            [
                'user_type_id' => UserType::GYM_COACH,
                'user_type_desc' => UserType::GYM_COACH->toString(),
            ],
            [
                'user_type_id' => UserType::GYM_MEMBER,
                'user_type_desc' => UserType::GYM_MEMBER->toString(),
            ],
            [
                'user_type_id' => UserType::BOX_ADMIN,
                'user_type_desc' => UserType::BOX_ADMIN->toString(),
            ],
            [
                'user_type_id' => UserType::BOX_FACILITY_ADMIN,
                'user_type_desc' => UserType::BOX_FACILITY_ADMIN->toString(),
            ],
            [
                'user_type_id' => UserType::ADMIN,
                'user_type_desc' => UserType::ADMIN->toString(),
            ],
            [
                'user_type_id' => UserType::LOCATION_CHECK_IN,
                'user_type_desc' => UserType::LOCATION_CHECK_IN->toString(),
            ],
        ]);
    }
}

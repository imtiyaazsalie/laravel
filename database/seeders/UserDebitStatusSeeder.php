<?php

namespace Database\Seeders;

use App\Enums\UserDebitStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserDebitStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('user_debit_status')->insert([
            [
                'user_debit_status_id' => UserDebitStatus::CASH,
                'user_debit_status_descr' => UserDebitStatus::CASH->toString(),
                'is_active' => true,
            ],
            [
                'user_debit_status_id' => UserDebitStatus::DEBIT_ORDER,
                'user_debit_status_descr' => UserDebitStatus::DEBIT_ORDER->toString(),
                'is_active' => true,
            ],
            [
                'user_debit_status_id' => UserDebitStatus::DISCOVERY_VITALITY,
                'user_debit_status_descr' => UserDebitStatus::DISCOVERY_VITALITY->toString(),
                'is_active' => false,
            ],
            [
                'user_debit_status_id' => UserDebitStatus::NO_PAYMENT,
                'user_debit_status_descr' => UserDebitStatus::CASH->toString(),
                'is_active' => true,
            ],
            [
                'user_debit_status_id' => UserDebitStatus::UP_FRONT_PAYMENT,
                'user_debit_status_descr' => UserDebitStatus::UP_FRONT_PAYMENT->toString(),
                'is_active' => true,
            ],
            [
                'user_debit_status_id' => UserDebitStatus::ONLINE_PAYMENT,
                'user_debit_status_descr' => UserDebitStatus::ONLINE_PAYMENT->toString(),
                'is_active' => true,
            ],
        ]);
    }
}

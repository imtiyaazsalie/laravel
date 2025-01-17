<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccountTypeSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('account_types')->insert([
            [
                'account_type_id' => 1,
                'account_type_descr' => 'Cheque/Current',
                'import_code' => 'C',
                'export_code_namibia' => 1,
            ],
            [
                'account_type_id' => 2,
                'account_type_descr' => 'Savings',
                'import_code' => 'S',
                'export_code_namibia' => 2,
            ],
            [
                'account_type_id' => 3,
                'account_type_descr' => 'Transmission',
                'import_code' => 'T',
                'export_code_namibia' => 3,
            ],
            [
                'account_type_id' => 4,
                'account_type_descr' => 'Bond',
                'import_code' => 'B',
                'export_code_namibia' => 4,
            ],
            [
                'account_type_id' => 5,
                'account_type_descr' => 'Subscription',
                'import_code' => 'Su',
                'export_code_namibia' => 6,
            ],
        ]);
    }
}

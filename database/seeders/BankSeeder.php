<?php

namespace Database\Seeders;

use App\Models\Bank;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BankSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Bank::insert([
            [
                'bank_id' => 1,
                'country_id' => 1,
                'bank_name' => 'ABSA',
                'universal_code' => '632005',
                'import_code' => 'ABSA',
            ],
            [
                'bank_id' => 2,
                'country_id' => 1,
                'bank_name' => 'Bank of Athens',
                'universal_code' => '410506',
                'import_code' => 'BOA',
            ],
            [
                'bank_id' => 3,
                'country_id' => 1,
                'bank_name' => 'Bidvest',
                'universal_code' => '462005',
                'import_code' => 'BV',
            ],
            [
                'bank_id' => 4,
                'country_id' => 1,
                'bank_name' => 'Capitec',
                'universal_code' => '470010',
                'import_code' => 'C',
            ],
            [
                'bank_id' => 5,
                'country_id' => 1,
                'bank_name' => 'FNB',
                'universal_code' => '254005',
                'import_code' => 'FNB',
            ],
            [
                'bank_id' => 6,
                'country_id' => 1,
                'bank_name' => 'Investec Private Banking',
                'universal_code' => '580105',
                'import_code' => 'IPB',
            ],
            [
                'bank_id' => 7,
                'country_id' => 1,
                'bank_name' => 'Nedbank',
                'universal_code' => '198765',
                'import_code' => 'N',
            ],
            [
                'bank_id' => 8,
                'country_id' => 1,
                'bank_name' => 'SA Post Bank',
                'universal_code' => '460005',
                'import_code' => 'SAPB',
            ],
            [
                'bank_id' => 9,
                'country_id' => 1,
                'bank_name' => 'Standard Bank',
                'universal_code' => '051001',
                'import_code' => 'SB',
            ],
            [
                'bank_id' => 10,
                'country_id' => 1,
                'bank_name' => 'Mercantile Bank',
                'universal_code' => '450905',
                'import_code' => 'MB',
            ],
            [
                'bank_id' => 11,
                'country_id' => null,
                'bank_name' => 'Other',
                'universal_code' => null,
                'import_code' => null,
            ],
            [
                'bank_id' => 12,
                'country_id' => 1,
                'bank_name' => 'SASFIN',
                'universal_code' => '683000',
                'import_code' => 'SASFIN',
            ],
            [
                'bank_id' => 13,
                'country_id' => 2,
                'bank_name' => 'Bank Of Windhoek',
                'universal_code' => '483872',
                'import_code' => 'BOW',
            ],
            [
                'bank_id' => 14,
                'country_id' => 2,
                'bank_name' => 'FNB Namibia',
                'universal_code' => '282672',
                'import_code' => 'FNBN',
            ],
            [
                'bank_id' => 15,
                'country_id' => 2,
                'bank_name' => 'Standard Bank Namibia',
                'universal_code' => '087373',
                'import_code' => 'SBN',
            ],
            [
                'bank_id' => 16,
                'country_id' => 2,
                'bank_name' => 'Nedbank Namibia',
                'universal_code' => '461609',
                'import_code' => 'NN',
            ],
            [
                'bank_id' => 17,
                'country_id' => 2,
                'bank_name' => 'Trustco Bank Namibia',
                'universal_code' => null,
                'import_code' => 'TBN',
            ],
            [
                'bank_id' => 19,
                'country_id' => 2,
                'bank_name' => 'SME Bank Namibia Ltd',
                'universal_code' => null,
                'import_code' => 'SBN',
            ],
            [
                'bank_id' => 20,
                'country_id' => 2,
                'bank_name' => 'U Bank',
                'universal_code' => '431010',
                'import_code' => 'UB',
            ],
            [
                'bank_id' => 21,
                'country_id' => 1,
                'bank_name' => 'Tyme Bank',
                'universal_code' => '678910',
                'import_code' => 'TB',
            ],
            [
                'bank_id' => 22,
                'country_id' => 1,
                'bank_name' => 'Discovery Bank',
                'universal_code' => '679000',
                'import_code' => 'DB',
            ],
            [
                'bank_id' => 23,
                'country_id' => 1,
                'bank_name' => 'Finbond Mutual Bank',
                'universal_code' => '589000',
                'import_code' => 'FBMB',
            ],
            [
                'bank_id' => 24,
                'country_id' => 1,
                'bank_name' => 'Old Mutual Bank',
                'universal_code' => '462005',
                'import_code' => 'OMB',
            ],
            [
                'bank_id' => 25,
                'country_id' => 1,
                'bank_name' => 'Africa bank',
                'universal_code' => '430000',
                'import_code' => 'AFB',
            ],
            [
                'bank_id' => 26,
                'country_id' => 1,
                'bank_name' => 'Albaraka Bank ',
                'universal_code' => '800000',
                'import_code' => 'ALB',
            ],
            [
                'bank_id' => 27,
                'country_id' => 1,
                'bank_name' => 'Bank Zero',
                'universal_code' => '888000',
                'import_code' => 'BZ',
            ],
            [
                'bank_id' => 28,
                'country_id' => 1,
                'bank_name' => 'HBZ Bank Limited',
                'universal_code' => null,
                'import_code' => 'HBZ',
            ],
            [
                'bank_id' => 29,
                'country_id' => 1,
                'bank_name' => 'Access Bank',
                'universal_code' => null,
                'import_code' => 'AB',
            ],
        ]);
    }
}

<?php

namespace Database\Seeders;

use App\Enums\TagType;
use App\Models\Tag;
use Illuminate\Database\Seeder;

class PaymentTagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Tag::insert([
            [
                'name' => 'Three Peaks',
                'type' => TagType::PAYMENT,
            ],
            [
                'name' => 'Netcash',
                'type' => TagType::PAYMENT,
            ],
            [
                'name' => 'GoCardless',
                'type' => TagType::PAYMENT,
            ],
            [
                'name' => 'Stripe',
                'type' => TagType::PAYMENT,
            ],
            [
                'name' => 'Paystack',
                'type' => TagType::PAYMENT,
            ],
            [
                'name' => 'PayNow',
                'type' => TagType::PAYMENT,
            ],
            [
                'name' => 'SEPA',
                'type' => TagType::PAYMENT,
            ],
        ]);
    }
}

<?php

namespace Database\Seeders;

use App\Models\LeadMember;
use App\Models\MailerRecipient;
use App\Models\Region;
use App\Models\User;
use Illuminate\Database\Seeder;

class MigrateCrmMailersMorphs extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        MailerRecipient::whereIn('ref_entity_name', ['App\\Entity\User', 'Entity\\User', 'user'])
            ->update([
                'ref_entity_name' => (new User())->getTable(),
            ]);

        MailerRecipient::whereIn('ref_entity_name', ['App\\Entity\\Lead\\Member', 'Entity\\Lead\\Member', 'lead_member', 'lead-member'])
            ->update([
                'ref_entity_name' => (new LeadMember())->getTable(),
            ]);

        MailerRecipient::whereIn('ref_entity_name', ['Entity\\Region', 'region'])
            ->update([
                'ref_entity_name' => (new Region())->getTable(),
            ]);
    }
}

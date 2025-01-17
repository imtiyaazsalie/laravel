<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MigrateCrmMailerRecipients extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // can only be run once lead migration has run
        DB::select("UPDATE crm_recipients
            LEFT JOIN lead_members ON lead_members.member_id = crm_recipients.ref_entity_id
            SET
            crm_recipients.ref_entity_name = 'users',
            crm_recipients.ref_entity_id = lead_members.user_id
            WHERE
            crm_recipients. `type` = 'lead-member'"
        );
    }
}

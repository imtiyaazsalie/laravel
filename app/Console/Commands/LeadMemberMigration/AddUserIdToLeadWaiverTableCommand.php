<?php

namespace App\Console\Commands\LeadMemberMigration;

use App\Models\LeadMember;
use App\Models\LeadWaivers;
use Illuminate\Console\Command;

class AddUserIdToLeadWaiverTableCommand extends Command
{
    protected $signature = 'lead-member:add-user-id-to-lead-waiver-table';

    protected $description = 'Command description';

    public function handle(): void
    {
        $this->addUserIdToLeadWaiverTable();
    }

    private function addUserIdToLeadWaiverTable(): void
    {
        $leadUsers = LeadMember::query()
            ->join('lead_waivers', 'lead_waivers.waiver_id', '=', 'lead_members.waiver_id')
            ->whereNotNull('lead_members.user_id')
            ->select('lead_members.user_id', 'lead_members.waiver_id')
            ->get();

        $this->info('Add user_id to Lead Waiver table');
        $progress = $this->output->createProgressBar(count($leadUsers));
        $progress->start();

        foreach ($leadUsers as $leadUser) {
            $leadWaiver = LeadWaivers::query()->where('waiver_id', $leadUser->waiver_id)->first();
            $leadWaiver->update(['user_id' => $leadUser->user_id]);
            $progress->advance();
        }

        $progress->finish();
    }
}

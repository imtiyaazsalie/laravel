<?php

namespace App\Console\Commands\LeadMemberMigration;

use App\Models\LeadMember;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateAllEmailsToUserEmailCommand extends Command
{
    protected $signature = 'lead-member:update-all-emails-to-user-email';

    protected $description = 'Command description';

    public function handle(): void
    {
        $leadMembers = LeadMember::query()
            ->withoutGlobalScopes()
            ->select('users.email', 'lead_members.email_address', 'lead_members.member_id')
            ->join('users', 'users.user_id', '=', 'lead_members.user_id')
            ->whereNotNull('lead_members.user_id')
            ->where('lead_members.email_address', '!=', DB::raw('users.email'))
            ->get();

        $this->info('Updating lead members email address to user email address');
        $progress = $this->output->createProgressBar(count($leadMembers));
        $progress->start();

        foreach ($leadMembers as $leadMember) {
            $leadMember->update(['email_address' => $leadMember->getRawOriginal('email')]);
            $progress->advance();
        }
        $progress->finish();
    }
}

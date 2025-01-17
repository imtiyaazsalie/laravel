<?php

namespace App\Console\Commands\LeadMemberMigration;

use App\Models\DropInPackageLeadMember;
use App\Models\UserPackage;
use Illuminate\Console\Command;

class MigrateDropInLeadPackagesToUserToPackagesCommand extends Command
{
    protected $signature = 'lead-member:migrate-drop-in-lead-packages-to-user-to-packages';

    protected $description = 'Command description';

    public function handle(): void
    {
        $this->migrateDropInLeadPackagesToUserToPackages();
    }

    private function migrateDropInLeadPackagesToUserToPackages(): void
    {
        $dropInUsers = DropInPackageLeadMember::query()
            ->join('lead_members', 'drop_in_packages_to_lead_members.lead_member_id', '=', 'lead_members.member_id')
            ->join('packages', 'packages.drop_in_package_id', '=', 'drop_in_packages_to_lead_members.drop_in_package_id')
            ->join('drop_in_packages', 'drop_in_packages.id', '=', 'drop_in_packages_to_lead_members.drop_in_package_id')
            ->select(['lead_members.user_id', 'packages.package_id', 'drop_in_packages.created_at AS effective_date', 'sessions_remaining AS sessions_available'])
            ->get();

        $this->info('Migrate drop in lead packages table to user packages table');
        $progress = $this->output->createProgressBar(count($dropInUsers));
        $progress->start();

        foreach ($dropInUsers as $dropInUser) {
            UserPackage::query()->updateOrInsert([
                'user_id' => $dropInUser->user_id,
                'package_id' => $dropInUser->package_id,
                'effective_date' => $dropInUser->effective_date,
                'sessions_available' => $dropInUser->sessions_available,
                'deleted' => 0,
            ]);
            $progress->advance();
        }

        $progress->finish();
    }
}

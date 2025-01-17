<?php

namespace App\Console\Commands\LeadMemberMigration;

use App\Models\DropInPackageLeadMember;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrationUserToPackageIdToLeadInvoicesCommand extends Command
{
    protected $signature = 'lead-member:migration-user-to-package-id-to-lead-invoices';

    protected $description = 'Command description';

    public function handle(): void
    {
        $this->migrationUserToPackageIdToLeadInvoices();
    }

    private function migrationUserToPackageIdToLeadInvoices(): void
    {
        $userPackages = DropInPackageLeadMember::query()
            ->select([
                'drop_in_packages_to_lead_members.lead_member_id',
                'drop_in_packages_to_lead_members.drop_in_package_id',
                'packages.package_id',
                'lead_members.user_id',
                'packages.box_id',
                'box_facility.box_id',
                'finance_invoices.invoice_id',
                'finance_invoices.box_facility_id',
                DB::raw('(
                    SELECT
                        user_to_package_id
                    FROM
                        user_to_package
                    WHERE
                        user_id = lead_members.user_id
                        AND package_id = packages.package_id
                    ORDER BY
                        user_to_package_id DESC
                    LIMIT 1) as user_to_package_id_insert'
                ),
            ])
            ->join('lead_members', 'lead_members.member_id', '=', 'drop_in_packages_to_lead_members.lead_member_id')
            ->join('packages', 'packages.drop_in_package_id', '=', 'drop_in_packages_to_lead_members.drop_in_package_id')
            ->join('finance_invoices', 'finance_invoices.invoice_id', '=', 'drop_in_packages_to_lead_members.invoice_id')
            ->join('box_facility', 'box_facility.box_facility_id', '=', 'finance_invoices.box_facility_id')
            ->get();

        $this->info('Migrate user packages to lead invoices');
        $progress = $this->output->createProgressBar(count($userPackages));
        $progress->start();

        foreach ($userPackages as $userPackage) {
            if ($userPackage->userInvoice) {
                $userPackage->userInvoice->update([
                    'user_to_package_id' => $userPackage->user_to_package_id_insert,
                ]);
                $progress->advance();
            }
        }

        $progress->finish();
    }
}

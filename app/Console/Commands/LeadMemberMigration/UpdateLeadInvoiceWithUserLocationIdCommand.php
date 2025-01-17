<?php

namespace App\Console\Commands\LeadMemberMigration;

use App\Models\UserInvoice;
use Illuminate\Console\Command;

class UpdateLeadInvoiceWithUserLocationIdCommand extends Command
{
    protected $signature = 'lead-member:update-lead-invoice-with-user-location-id';

    protected $description = 'Command description';

    public function handle(): void
    {
        $this->updateLeadInvoiceWithUserLocationId();
    }

    private function updateLeadInvoiceWithUserLocationId(): void
    {
        $financeInvoices = UserInvoice::query()
            ->withoutGlobalScopes()
            ->whereNotNull('finance_invoices.lead_member_id')
            ->whereNull('finance_invoices.user_to_facility_id')
            ->leftJoin('box_facility', 'box_facility.box_facility_id', '=', 'finance_invoices.box_facility_id')
            ->leftJoin('lead_members', 'finance_invoices.lead_member_id', '=', 'lead_members.member_id')
            ->join('user_to_facility', function ($join) {
                $join->on('finance_invoices.box_facility_id', '=', 'user_to_facility.box_facility_id')
                    ->on('user_to_facility.lead_member_id', '=', 'lead_members.member_id');
            })
            ->select([
                'finance_invoices.invoice_id',
                'finance_invoices.box_facility_id',
                'box_facility.box_id',
                'lead_members.user_id',
                'user_to_facility.user_to_facility_id as utf',
            ])
            ->groupBy('finance_invoices.invoice_id')
            ->get();

        $this->info('Update lead invoice with utf_id');
        $progress = $this->output->createProgressBar(count($financeInvoices));
        $progress->start();

        foreach ($financeInvoices as $financeInvoice) {
            if ($financeInvoice->utf) {
                $financeInvoice->update([
                    'user_to_facility_id' => $financeInvoice->utf,
                ]);
                $progress->advance();
            }
        }
        $progress->finish();
    }
}

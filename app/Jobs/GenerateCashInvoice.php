<?php

namespace App\Jobs;

use App\Models\TenantUser;
use App\Services\InvoiceService;
use App\Services\TenantUserService;
use App\Services\UserPackageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class GenerateCashInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(private readonly TenantUser $tenantUser, private readonly Carbon $generationDate)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(TenantUserService $tenantUserService, InvoiceService $invoiceService): void
    {
        $locationUser = $tenantUserService->getLocationUserByTenant($this->tenantUser->user_id, $this->tenantUser->tenant_id);

        $dueDay = ($this->tenantUser->auto_invoicing_due_day !== null && $this->tenantUser->auto_invoicing_due_day !== '') ? $this->tenantUser->auto_invoicing_due_day : $this->tenantUser->tenant->cash_member_invoice_due_day;
        $dueOnDate = $this->getInvoiceDueDateForGenerationDate($this->generationDate, $dueDay);

        if (! $locationUser) {
            Log::warning("Location User does not exist / is out of date for: {$this->tenantUser->user_id}.{$this->tenantUser->tenant_id}");

            return;
        }

        $hasExistingMembershipInvoiceForDate = $locationUser->invoices()
            ->where('description', 'LIKE', 'Membership invoice%')
            ->whereDate('due_on', '=', $dueOnDate)
            ->where('deleted', '=', 0)
            ->count() > 0;

        // Check if invoice has already been generated.
        if ($hasExistingMembershipInvoiceForDate) {
            Log::warning("Invoice already generated for date {$dueOnDate->toDateString()} and user to facility ID: {$locationUser->getKey()}");

            return;
        }

        // Check if this user has non-limited packages
        if ((new UserPackageService())->getActiveUserPackagesExcludingLimitedPackagesForTenant($this->tenantUser->user, $this->tenantUser->tenant)->count() === 0) {
            Log::warning('User does not have active packages: UserId - '.$this->tenantUser->user_id.' TenantId - '.$this->tenantUser->tenant_id);

            return;
        }

        // Generate the invoice
        $invoice = $invoiceService->generateCashInvoiceForUser($locationUser, $dueOnDate, $this->generationDate);

        if ($locationUser->location->tenant->cash_member_invoice_strategy === 'generate_and_send' && $invoice) {

            (new InvoiceService())->sendInvoice($invoice);

            Log::info("Invoice ID {$invoice->getKey()} created and sent to {$locationUser->user->email}");

        } elseif ($invoice) {

            Log::info("Invoice ID {$invoice->getKey()} created but not sent.");
        }

        Log::info("Created invoice({$invoice->getKey()}) for tenant user id: ".$this->tenantUser->getKey().' - '.$this->tenantUser->user->fullname);
    }

    private function getInvoiceDueDateForGenerationDate(Carbon $generationDate, $invoiceDueDay): Carbon
    {
        $boxDueDayNumeric = $this->getNumericDayForStrategyAndDate($invoiceDueDay, $generationDate);
        $boxDueDate = Carbon::parse($generationDate->format('Y-m-').$boxDueDayNumeric);

        if ($boxDueDate < $generationDate) {
            $boxDueDate->addMonth();
        }

        return $boxDueDate;
    }

    private function getNumericDayForStrategyAndDate(string $strategy, Carbon $date): string
    {
        if (is_numeric($strategy)) {
            return $strategy;
        } elseif ($strategy === 'last_day_of_month') {
            $relativeDate = Carbon::parse(date('Y-m-d', strtotime('last day of this month', $date->getTimestamp())));

            return $relativeDate->format('d');
        } elseif ($strategy === 'second_last_day_of_month') {
            $relativeDate = Carbon::parse(date('Y-m-d', strtotime('last day of this month', $date->getTimestamp())));
            $relativeDate->subDay();

            return $relativeDate->format('d');
        }

        throw new \Exception("Invoice due date strategy unknown: $strategy");
    }
}

<?php

namespace App\Console\Commands;

use App\Enums\TenantStatus;
use App\Enums\UserDebitStatus;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Jobs\GenerateCashInvoice as GenerateCashInvoiceJob;
use App\Models\TenantUser;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateCashInvoice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-cash-invoices {--date=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate cash invoices.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $generationDate = $this->option('date') ? Carbon::parse($this->option('date'))->endOfDay() : today()->endOfDay();
        $dueDate = $this->getInvoiceDueDateParameterForDate($generationDate);

        TenantUser::query()
            ->distinct()
            ->select('user_to_box.*')
            ->addSelect('boxes.cash_member_invoice_generation_day')
            ->join('boxes', function ($join) {
                $join->on('user_to_box.box_id', '=', 'boxes.box_id')
                    ->where('boxes.box_status_id', '=', TenantStatus::ACTIVE->value);
            })
            ->whereIn('user_to_box.user_status_id', [UserStatus::ACTIVE->value, UserStatus::SUSPENDED->value])
            ->where('user_to_box.user_type_id', UserType::GYM_MEMBER->value)
            ->where('user_to_box.user_debit_status_id', UserDebitStatus::CASH->value)
            ->whereDate('user_to_box.effective_date', '<=', $generationDate)
            ->whereDate('user_to_box.end_date', '>', $generationDate)
            ->whereNotNull('boxes.cash_member_invoice_generation_day')
            ->when((! $generationDate->isLeapYear() && $generationDate->format('j M') !== '27 Feb' && $generationDate->format('j M') !== '28 Feb') || ($generationDate->isLeapYear() && $generationDate->format('j M') !== '28 Feb' && $generationDate->format('j M') !== '29 Feb'), function ($query) use ($dueDate) {
                $query->where(function ($query) use ($dueDate) {
                    $query->where('user_to_box.auto_invoicing_day', $dueDate)
                        ->orWhere(function ($query) use ($dueDate) {
                            $query->where('boxes.cash_member_invoice_generation_day', $dueDate)
                                ->where(function ($query) {
                                    $query->whereNull('user_to_box.auto_invoicing_day')
                                        ->orWhere('user_to_box.auto_invoicing_day', '');
                                });
                        });
                });
            })
            ->when((! $generationDate->isLeapYear() && $generationDate->format('j M') === '27 Feb') || ($generationDate->isLeapYear() && $generationDate->format('j M') === '28 Feb'), function ($query) use ($generationDate) {
                $secondLastDayOfMonthNumber = $generationDate->isLeapYear() ? '28' : '27';

                $query->where(function ($query) use ($secondLastDayOfMonthNumber) {
                    $query->where('user_to_box.auto_invoicing_day', $secondLastDayOfMonthNumber)
                        ->orWhere('user_to_box.auto_invoicing_day', 'second_last_day_of_month')
                        ->orWhere(function ($query) use ($secondLastDayOfMonthNumber) {
                            $query->where(function ($query) {
                                $query->whereNull('user_to_box.auto_invoicing_day')
                                    ->orWhere('user_to_box.auto_invoicing_day', '');
                            })->where(function ($query) use ($secondLastDayOfMonthNumber) {
                                $query->where('boxes.cash_member_invoice_generation_day', $secondLastDayOfMonthNumber)
                                    ->orWhere('boxes.cash_member_invoice_generation_day', 'second_last_day_of_month');
                            });
                        });
                });
            })
            ->when((! $generationDate->isLeapYear() && $generationDate->format('j M') === '28 Feb') || ($generationDate->isLeapYear() && $generationDate->format('j M') === '29 Feb'), function ($query) use ($generationDate) {
                $lastDayOfMonthNumber = $generationDate->isLeapYear() ? '29' : '28';

                $query->where(function ($query) use ($lastDayOfMonthNumber) {
                    $query->where('user_to_box.auto_invoicing_day', $lastDayOfMonthNumber)
                        ->orWhere('user_to_box.auto_invoicing_day', 'last_day_of_month')
                        ->orWhere(function ($query) use ($lastDayOfMonthNumber) {
                            $query->where(function ($query) {
                                $query->whereNull('user_to_box.auto_invoicing_day')
                                    ->orWhere('user_to_box.auto_invoicing_day', '');
                            })->where(function ($query) use ($lastDayOfMonthNumber) {
                                $query->where('boxes.cash_member_invoice_generation_day', $lastDayOfMonthNumber)
                                    ->orWhere('boxes.cash_member_invoice_generation_day', 'last_day_of_month');
                            });
                        });
                });
            })->chunk(500, function ($tenantUsers) use ($generationDate) {
                $tenantUsers->each(function ($tenantUser) use ($generationDate) {
                    GenerateCashInvoiceJob::dispatch(tenantUser: $tenantUser, generationDate: $generationDate)->onQueue('finance');
                });
            });

        return Command::SUCCESS;
    }

    private function getInvoiceDueDateParameterForDate(Carbon $date): string
    {
        if ($date->equalTo(today()->lastOfMonth()->endOfDay())) {
            return 'last_day_of_month';
        }

        if ($date->equalTo(today()->lastOfMonth()->subDay()->endOfDay())) {
            return 'second_last_day_of_month';
        }

        return $date->format('j');
    }
}

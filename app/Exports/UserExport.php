<?php

namespace App\Exports;

use App\Enums\UserStatus;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Services\FinanceService;
use App\Services\UserService;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UserExport implements FromCollection, WithHeadings, WithMapping
{
    private Tenant $tenant;

    public function __construct()
    {
        $this->tenant = Tenant::find(request()->input('filter.tenant_id'));
    }

    public function headings(): array
    {
        $headings = [
            'Name',
            'Surname',
            'Date of Birth',
            'Email',
            'Mobile',
            'Package(s)',
            'Package(s) start and end dates',
            'Contract start and end dates',
            'Member rate',
        ];

        if ((int) request()->input('filter.user_tenant_status_id') === UserStatus::DEACTIVATED->value) {
            $headings = array_merge($headings, [
                'Deactivated on',
                'Last modified on',
            ]);
        }

        if (in_array($this->tenant->region->getKey(), [1, 2])) {
            $headings = array_merge($headings, [
                'Bank',
                'Account Type',
                'Account number',
                'Account Holder Name',
                'Debit Day',
            ]);
        }

        return $headings;
    }

    public function map($user): array
    {
        $contractDates = 'n/a';

        /** @var TenantUser $userTenant */
        $userTenant = $user->userTenant;
        $userContract = $userTenant->userContract;

        if ($userContract) {
            $contractStartDate = $userContract->starting_on ? $userContract->starting_on->toDateString() : '-';
            $contractEndDate = $userContract->ending_on ? $userContract->ending_on->toDateString() : 'open';
            $contractDates = $contractStartDate.' / '.$contractEndDate;
        }

        $map = [
            $user->name,
            $user->surname,
            $user->dob?->toDateString(),
            $user->email,
            $user->mobile,
            $user->getActivePackagesStringForTenant($this->tenant),
            $user->getActivePackagesDateStringForTenant($this->tenant),
            $contractDates,
            (new FinanceService())->calculateMemberFee($user, $this->tenant),
        ];

        if ((int) request()->input('filter.user_tenant_status_id') === UserStatus::DEACTIVATED->value) {
            $map = array_merge($map, [
                $userTenant->deactivated_on,
                $userTenant->updated_on,
            ]);
        }

        if (in_array($this->tenant->region->getKey(), [1, 2])) {
            $bankingDetails = $userTenant->bankAccount;

            if ($bankingDetails) {
                $map = array_merge($map, [
                    $bankingDetails->bank?->name,
                    $bankingDetails->account_type?->value,
                    $bankingDetails->account_number,
                    trim($bankingDetails->account_name),
                    $bankingDetails->debitDay?->name,
                ]);
            }
        }

        return $map;
    }

    public function collection(): Collection
    {
        return (new UserService())->getUsersQueryBuilder()->get();
    }
}

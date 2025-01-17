<?php

namespace App\Exports;

use App\Models\UserInvoice;
use App\Services\TenantUserService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TenantUserExport implements FromQuery, ShouldQueue, WithHeadings, WithMapping
{
    use Exportable;

    public function __construct(
        protected array $params,
    ) {
        $this->params['include'] = str(Arr::get($this->params, 'include'))->contains('bankAccount')
            ? 'bankAccount,packages'
            : 'packages';
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

        if (str(Arr::get($this->params, 'include'))->contains('bankAccount')) {
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

    /**
     * @var UserInvoice
     */
    public function map($user): array
    {

        $contract = $user->contracts->first();

        $data = [
            $user->name,
            $user->surname,
            $user->dob?->toDateString(),
            $user->email,
            $user->mobile,
            $user->packages->pluck('package_name')->join(PHP_EOL),
            $user->packages->map(fn ($p) => $p->userPackage->effective_date.' / '.$p->userPackage->end_date)->join(PHP_EOL),
            $contract?->starting_on?->toDateString().' / '.$contract?->ending_on?->toDateString(),
            0.00,
            // TODO: (finance) $this->financeService->calculateMemberFee($tenantUser, $tenantUser->currentLocation),
        ];

        if (str(Arr::get($this->params, 'include'))->contains('bankAccount')) {
            $data = array_merge($data, [
                $user->bankAccount->bank->name,
                $user->bankAccount->accountType->toString(),
                $user->bankAccount->account_no,
                $user->bankAccount->account_name,
                $user->bankAccount->debitDay->name,
            ]);
        }

        return $data;
    }

    public function query()
    {
        $request = Request::create('temporary/?'.urldecode(Arr::query($this->params)), 'GET');

        /** @var TenantUserService */
        $service = resolve(TenantUserService::class);

        $service->queryByRequest($request)->getEloquentBuilder();

    }
}

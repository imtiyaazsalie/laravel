<?php

namespace App\Imports;

use App\Enums\AccountType;
use App\Enums\MandateStatus;
use App\Enums\MandateType;
use App\Enums\PaymentGateway;
use App\Enums\UserDebitStatus;
use App\Enums\UserStatus;
use App\Models\Bank;
use App\Models\DebitDay;
use App\Models\LocationUser;
use App\Models\Mandate;
use App\Models\SpecialRate;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\UserBankingDetail;
use App\Services\DebitBatchService;
use App\Services\FinanceService;
use App\Services\MandateService;
use App\Services\PaymentGateways\NetcashService;
use App\Services\UtilityService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class UsersBankingDetailsImport implements ToCollection, WithHeadingRow, WithValidation
{
    use Importable;

    public Collection $users;

    public Collection $locationUsers;

    public Collection $debitDays;

    public Collection $data;

    public Collection $errors;

    public function __construct(private string|int $tenantId, public bool $validationOnly)
    {
        $this->errors = collect();
        $this->data = collect();
    }

    /**
     * Get the upload results with errors.
     */
    public function getResults(): Collection
    {
        if ($this->errors->isNotEmpty()) {
            return $this->errors;
        }

        return $this->data->map(function ($row) {
            $user = $this->users->where('email', $row[0])->first();
            $userLocation = $this->locationUsers->where('user_id', $user->user_id)->first();
            $isSepa = $userLocation->location->payment_gateway_id === PaymentGateway::SEPA->value;

            $baseData = [
                'user' => $user->name.' ('.$user->email.')',
                'account_name' => Arr::get($row, '4'),
                'debit_day' => $this->debitDays->where('import_code', Arr::get($row, 5))->first()?->name,
                'special_rate' => Arr::has($row, '6') ? floatval(Arr::get($row, '6')) : null,
            ];

            if ($isSepa) {
                $extraData = [
                    'iban' => Arr::get($row, 1),
                    'bic' => Arr::get($row, 2),
                    'address' => Arr::get($row, 3),
                    'is_create_sepa_mandate' => strtolower(Arr::get($row, 7, 'no')) === 'yes',
                ];
            } else {
                $extraData = [
                    'bank' => Arr::get($row, 1),
                    'account_type' => Arr::get($row, 2),
                    'account_number' => Arr::get($row, 3),
                ];
            }

            return array_merge($baseData, $extraData);
        });
    }

    public function rules(): array
    {
        return [
            '0' => 'email', // email
            '1' => 'nullable', // account_name / iban
            '2' => 'nullable', // account_number / bic
            '3' => 'nullable', // account_type / address
            '4' => 'required', // debit_day
            '5' => 'nullable', // bank
            '6' => 'nullable', // specialRate
            '7' => 'nullable', // isCreateSepaMandate
        ];
    }

    /**
     * @return array
     */
    public function customValidationAttributes()
    {
        return [
            '0' => 'email',
            '1' => 'account name/iban',
            '2' => 'account number/bic',
            '3' => 'account type/address',
            '4' => 'debit day',
            '5' => 'bank import code',
            '6' => 'special rate',
            '7' => 'create sepa mandate',
        ];
    }

    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {

            $data = collect($validator->getData());

            /**
             * Validate debit days
             */
            $debitDays = $data->pluck(5)->unique();

            $this->debitDays = DebitDay::query()
                ->where('is_active', true)
                ->get();

            foreach ($debitDays as $debitDay) {

                if (! in_array($debitDay, $this->debitDays->pluck('import_code')->toArray())) {
                    $rows = $data->where(5, $debitDay);
                    foreach ($rows as $index => $row) {
                        $this->errors->add([
                            'user' => Arr::get($row, '0'),
                            'account_name' => Arr::get($row, '4'),
                            'debit_day' => Arr::get($row, '5'),
                            'special_rate' => Arr::get($row, '6'),
                            'invalid_field' => 'debitDay',
                            'invalid_message' => 'Debit day invalid: '.$row[5].'. Valid values are '.implode(', ', $this->debitDays->pluck('import_code')->toArray()),
                        ]);
                    }
                }
            }

            /**
             * Validate special rates
             */
            $specialRate = $data->pluck(6)->filter()->unique();
            foreach ($specialRate as $rate) {
                if (! is_float($rate)) {
                    $rows = $data->where(6, $rate);
                    foreach ($rows as $row) {
                        $this->errors->add([
                            'user' => Arr::get($row, '0'),
                            'account_name' => Arr::get($row, '4'),
                            'debit_day' => Arr::get($row, '5'),
                            'special_rate' => Arr::get($row, '6'),
                            'invalid_field' => 'specialRate',
                            'invalid_message' => 'Special rate is invalid.',
                        ]);
                    }
                }
            }

            /**
             * Validate duplicate emails
             */
            $emails = $data->pluck(0);
            if ($emails->duplicates()->count()) {
                $rows = $data->whereIn(0, $emails->duplicates());
                foreach ($rows as $index => $row) {
                    $this->errors->add([
                        'user' => Arr::get($row, '0'),
                        'account_name' => Arr::get($row, '4'),
                        'debit_day' => Arr::get($row, '5'),
                        'special_rate' => Arr::get($row, '6'),
                        'invalid_field' => 'user',
                        'invalid_message' => 'Duplicate email address in upload.',
                    ]);
                }
            }

            /**
             * Validate users exist
             */
            $this->users = TenantUser::query()
                ->select('user_to_box.*')
                ->addSelect('users.email')
                ->addSelect('users.name')
                ->joinRelationship('user')
                ->whereBoxId($this->tenantId)
                ->whereIn('users.email', $emails)
                ->get();

            foreach ($emails as $email) {
                if (! $this->users->where('email', $email)->first()) {
                    $rows = $data->where(0, $email);
                    foreach ($rows as $index => $row) {
                        $this->errors->add([
                            'user' => Arr::get($row, '0'),
                            'account_name' => Arr::get($row, '4'),
                            'debit_day' => Arr::get($row, '5'),
                            'special_rate' => Arr::get($row, '6'),
                            'invalid_field' => 'user',
                            'invalid_message' => 'User could not be found.',
                        ]);
                    }
                }
            }

            /**
             * Validate users have locations
             */
            $this->locationUsers = LocationUser::query()
                ->whereRelation('location', 'box_id', '=', $this->tenantId)
                ->with('location')
                ->active()
                ->whereIn('user_id', $this->users->pluck('user_id'))
                ->get();

            foreach ($emails as $email) {
                $user = $this->users->where('email', $email)->first();

                if ($user && ! $this->locationUsers->where('user_id', $user->user_id)->first()) {
                    $rows = $data->where(0, $user->email);
                    foreach ($rows as $row) {
                        $this->errors->add([
                            'user' => Arr::get($row, '0'),
                            'account_name' => Arr::get($row, '4'),
                            'debit_day' => Arr::get($row, '5'),
                            'special_rate' => Arr::get($row, '6'),
                            'invalid_field' => 'user',
                            'invalid_message' => 'User has no location.',
                        ]);
                    }
                }
            }

            /**
             * Validate banking details
             */
            foreach ($data as $row) {
                if ($user = $this->users->where('email', $row[0])->first()) {
                    $locationUser = $this->locationUsers->where('user_id', $user->user_id)->first();

                    if (! $locationUser) {
                        continue;
                    }

                    $isCreateSepaMandate = strtolower(Arr::get($row, 7, 'no')) === 'yes';

                    $data = [
                        'user' => $user->name.' ('.$user->email.')',
                        'account_name' => Arr::get($row, '4'),
                        'debit_day' => $this->debitDays->where('import_code', Arr::get($row, 5))->first()?->name,
                        'special_rate' => Arr::has($row, '6') ? floatval(Arr::get($row, '6')) : null,
                    ];

                    if ($locationUser->location->payment_gateway_id === PaymentGateway::SEPA->value) {
                        $extraData = [
                            'iban' => Arr::get($row, 1),
                            'bic' => Arr::get($row, 2),
                            'address' => Arr::get($row, 3),
                            'is_create_sepa_mandate' => $isCreateSepaMandate,
                        ];
                    } else {
                        $extraData = [
                            'bank' => Arr::get($row, 1),
                            'account_type' => Arr::get($row, 2),
                            'account_number' => Arr::get($row, 3),
                        ];
                    }

                    $data = array_merge($data, $extraData);

                    if ($locationUser->location->payment_gateway_id === PaymentGateway::SEPA->value && $isCreateSepaMandate) {
                        /**
                         * Validate SEPA details
                         */
                        $iban = $row[1];
                        $bic = $row[2];

                        if (empty($iban)) {
                            $this->errors->add([
                                ...$data,
                                'invalid_field' => 'iban',
                                'invalid_message' => 'Iban is required in order to create a mandate.',
                            ]);
                        }

                        if (empty($bic)) {
                            $this->errors->add([
                                ...$data,
                                'invalid_field' => 'bic',
                                'invalid_message' => 'Bic is required in order to create a mandate.',
                            ]);
                        }

                        if ($validationResult = (new FinanceService)->validateIbanAndBic($iban, $bic)) {
                            $this->errors->add([
                                ...$data,
                                'invalid_field' => 'iban',
                                'invalid_message' => $validationResult,
                            ]);

                            $this->errors->add([
                                ...$data,
                                'invalid_field' => 'bic',
                                'invalid_message' => $validationResult,
                            ]);
                        }

                    } elseif ($locationUser->location?->payment_gateway_id !== PaymentGateway::SEPA->value) {

                        /**
                         * Validate non-SEPA info
                         */
                        $bank = $row[1];
                        $accountType = $row[2];
                        $accountNumber = $row[3];
                        $accountName = $row[4];

                        if (empty($bank)) {
                            $this->errors->add([
                                ...$data,
                                'invalid_field' => 'bank',
                                'invalid_message' => 'Bank is required.',
                            ]);
                        }

                        if (! $bank = Bank::query()->where('import_code', $bank)->first()) {
                            $this->errors->add([
                                ...$data,
                                'invalid_field' => 'bank',
                                'invalid_message' => 'Bank must be valid.',
                            ]);
                        }

                        if (empty($accountType)) {
                            $this->errors->add([
                                ...$data,
                                'invalid_field' => 'accountType',
                                'invalid_message' => 'Account type is required.',
                            ]);
                        }

                        if (! $accountType = AccountType::tryFromImportCode($accountType)) {
                            $this->errors->add([
                                ...$data,
                                'invalid_field' => 'accountType',
                                'invalid_message' => 'Account type must be valid.',
                            ]);
                        }

                        if (empty($accountName)) {
                            $this->errors->add([
                                ...$data,
                                'invalid_field' => 'accountName',
                                'invalid_message' => 'Account name is required.',
                            ]);
                        }

                        if (empty($accountNumber)) {
                            $this->errors->add([
                                ...$data,
                                'invalid_field' => 'accountNumber',
                                'invalid_message' => 'Account number is required.',
                            ]);
                        }

                        /**
                         * Validate Sage banking details
                         */
                        if ($locationUser->location?->payment_gateway_id === PaymentGateway::SAGE_PAY_V3->value && app()->environment('production')) {
                            /** @var NetcashService $sage */
                            $sage = resolve(NetcashService::class);

                            $validationResult = $sage->validateBankingDetails(
                                $locationUser->location,
                                $accountNumber,
                                $bank->universal_code,
                                $accountType,
                            );

                            if (is_string($validationResult)) {
                                $this->errors->add([
                                    ...$data,
                                    'invalid_field' => 'Sage error',
                                    'invalid_message' => 'Sage error: Banking details invalid. Validation result: '.$validationResult,
                                ]);
                            }
                        }
                    }
                }
            }
        });
    }

    public function collection(Collection $rows): void
    {
        /**
         * Load the data
         */
        $this->data = $rows;

        if ($this->validationOnly || $this->errors->isNotEmpty()) {
            return;
        }

        $tenantUsers = TenantUser::query()
            ->memberships()
            ->with('bankAccount')
            ->withinActivePeriod()
            ->whereIn('user_to_box.user_status_id', [UserStatus::ACTIVE->value, UserStatus::PENDING->value])
            ->select('user_to_box.*')
            ->addSelect('users.email')
            ->joinRelationship('user')
            ->where('box_id', $this->tenantId)
            ->whereIn('users.email', $rows->pluck(0))
            ->get();

        $locationUsers = LocationUser::with('location')
            ->whereRelation('location', 'box_id', '=', $this->tenantId)
            ->whereIn('user_id', $tenantUsers->pluck('user_id'))
            ->get();

        foreach ($rows as $row) {
            $email = $row[0];
            $accountName = $row[4];
            $specialRate = Arr::get($row, 6);

            $debitDay = DebitDay::query()
                ->where('is_active', true)
                ->where('import_code', Arr::get($row, 5))->first();

            $tenantUser = $tenantUsers->where('email', $email)->first();

            if (! isset($tenantUser)) {
                continue;
            }

            $location = $locationUsers->where('user_id', $tenantUser->user_id)->first()->location;

            $isSepa = $location->payment_gateway_id === PaymentGateway::SEPA->value;

            $bankingDetail = new UserBankingDetail([
                'box_id' => $this->tenantId,
                'user_id' => $tenantUser->user_id,
                'account_name' => $accountName,
                'debit_day_id' => $debitDay->getKey(),
                'is_active' => true,
            ]);

            if ($isSepa) {
                $iban = Arr::get($row, 1);
                $bic = Arr::get($row, 2);
                $address = Arr::get($row, 3);

                $bankingDetail->fill([
                    'iban' => preg_replace('/\s+/', '', $iban),
                    'bic' => preg_replace('/\s+/', '', $bic),
                    'address' => $address,
                ]);

                if (! ($mandate = (new MandateService())->getLatestMandate($tenantUser->user_id, $this->tenantId, MandateType::SEPA)) || $mandate->status !== MandateStatus::ACTIVE) {
                    if ($mandate) {
                        $mandate->update([
                            'cancelled_at' => now(),
                        ]);
                    }
                    Mandate::create([
                        'user_id' => $tenantUser->user_id,
                        'box_id' => $this->tenantId,
                        'type' => MandateType::SEPA,
                        'reference' => (new UtilityService())->generateNanoId(30),
                        'status' => MandateStatus::ACTIVE,
                        'is_created_by_upload' => true,
                        'signed_at' => now(),
                        'cancelled_at' => null,
                        'ip_address' => request()->ip(),
                    ]);
                }

            } else {
                $bank = Bank::query()->where('import_code', Arr::get($row, 1))->first();
                $accountType = AccountType::fromImportCode(Arr::get($row, 2));
                $accountNumber = Arr::get($row, 3);

                $bankingDetail->fill([
                    'account_no' => preg_replace('/\s+/', '', $accountNumber),
                    'account_type_id' => $accountType,
                    'bank_id' => $bank->getKey(),
                ]);
            }

            //deactivate existing banking details
            UserBankingDetail::query()
                ->where('box_id', $this->tenantId)
                ->where('user_id', $tenantUser->user_id)
                ->update([
                    'is_active' => false,
                ]);

            //create new banking details
            $bankingDetail->save();

            // Set the user's payment type to Debit order if they are not
            if ($tenantUser->user_debit_status_id !== UserDebitStatus::DEBIT_ORDER) {
                $tenantUser->fill([
                    'user_debit_status_id' => UserDebitStatus::DEBIT_ORDER,
                ])->save();
            }

            // create or update special rate
            if (! empty($specialRate) && is_numeric($specialRate)) {

                $existingRate = SpecialRate::query()
                    ->where('user_id', $tenantUser->user_id)
                    ->where('box_id', $this->tenantId)
                    ->where('is_active', true)
                    ->first();

                //disable existing if amount not equal
                if ($existingRate && $existingRate->amount !== $specialRate) {
                    $existingRate->fill([
                        'is_active' => false,
                    ])->save();
                }

                //no active rate exists, create it
                if (! $existingRate?->is_active) {
                    SpecialRate::create([
                        'user_id' => $tenantUser->user_id,
                        'box_id' => $this->tenantId,
                        'amount' => $specialRate,
                        'is_active' => true,
                    ]);
                }
            }

            // Deactivate all future debit batches if there were existing ones for this user.
            (new DebitBatchService)->deactivateFutureUserBatchesForUserBoxMembership($tenantUser);

            // Generate new user batches for all future debit debit batches
            (new DebitBatchService)->generateFutureDebitBatchesForUser($location, $debitDay, $tenantUser->user);
        }
    }
}

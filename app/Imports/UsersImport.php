<?php

namespace App\Imports;

use App\Enums\PackageType;
use App\Enums\TenantStatus;
use App\Enums\UserDebitStatus;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\ClassRecurringBooking;
use App\Models\DebitDay;
use App\Models\Location;
use App\Models\LocationUser;
use App\Models\Package;
use App\Models\Programme;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\UserBankingDetail;
use App\Models\UserContract;
use App\Models\UserPackage;
use App\Services\CrmService;
use App\Services\DebitBatchService;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class UsersImport implements ToCollection, WithHeadingRow, WithValidation
{
    use Importable;

    public Collection $data;

    public Collection $errors;

    public function __construct(private string|int $tenantId)
    {
        $this->errors = collect();
        $this->data = collect();
    }

    /**
     * Get the upload results with errors.
     */
    public function getResults()
    {
        return $this->data->transform(function ($row, $index) {

            $rowNumber = $index + 2;

            $errors = Arr::get($this->errors->where('row', $rowNumber)->first(), 'errors');

            $notes = (string) Arr::get($row, 15) ?: null;
            $contractStartDate = Arr::get($row, 12);
            $contractEndDate = Arr::get($row, 13);
            $packageSessions = Arr::get($row, 14);
            $paymentGateway = Arr::get($row, 16);
            $debitDayOfTheMonth = Arr::get($row, 17);

            // This results of the upload
            $result = [
                'location' => $row[0],
                'package' => $row[1],
                'package_start_date' => $row[2],
                'package_end_date' => $row[3],
                'programme' => $row[4],
                'member_id' => $row[5],
                'first_name' => $row[6],
                'surname' => $row[7],
                'gender' => $row[8],
                'email' => $row[9],
                'mobile' => $row[10],
                'date_of_birth' => $row[11],
                'contract_start_date' => $contractStartDate,
                'contract_end_date' => $contractEndDate,
                'notes' => $notes,
            ];

            if ($paymentGateway && $paymentGateway == 'gocardless') {
                $result = array_merge($result, [
                    'payment_gateway' => $paymentGateway,
                    'debit_day' => $debitDayOfTheMonth,
                ]);
            }

            if ($packageSessions) {
                $result = array_merge($result, [
                    'sessions' => $row[14],
                ]);
            }

            $result['status'] = $errors ? $errors : 'OK';

            return $result;
        });
    }

    public function rules(): array
    {
        return [
            '0' => 'required|string',
            '1' => 'required|string',
            '2' => 'nullable|date_format:Y-m-d',
            '3' => 'nullable|date_format:Y-m-d',
            '4' => 'required|string',
            '5' => 'nullable',
            '6' => 'required|string',
            '7' => 'required|string',
            '8' => 'nullable|string|in:m,f,M,F',
            '9' => 'required|email:rfc,dns',
            '10' => ['sometimes'],
            '11' => 'nullable|date_format:Y-m-d|before_or_equal:today',
            '12' => 'nullable|date_format:Y-m-d',
            '13' => 'nullable|date_format:Y-m-d',
            '14' => 'nullable|integer',
            '15' => 'nullable|string',
            '16' => 'nullable',
            '17' => 'nullable',
            '18' => 'nullable|date_format:Y-m-d',
            '19' => ['nullable', 'alpha_num'],
            '20' => 'nullable|string',
        ];
    }

    /**
     * @return array
     */
    public function customValidationAttributes()
    {
        return [
            '0' => 'location name',
            '1' => 'package name',
            '2' => 'package start date',
            '3' => 'package end date',
            '4' => 'programme name ',
            '5' => 'member_id',
            '6' => 'first_name',
            '7' => 'last_name',
            '8' => 'gender',
            '9' => 'email',
            '10' => 'mobile',
            '11' => 'date of birth',
            '12' => 'contract start date',
            '13' => 'contract end date',
            '14' => 'package sessions',
            '15' => 'notes',
            '16' => 'payment gateway',
            '17' => 'debit day',
            '18' => 'date joined',
            '19' => 'member social security number',
            '20' => 'member address',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {

            $data = collect($validator->getData());

            /**
             * Validate payment gateways
             */
            $paymentGateways = $data->pluck(16)->filter()->unique();

            $gateways = ['gocardless'];

            foreach ($paymentGateways as $paymentGateway) {
                if (! in_array($paymentGateway, $gateways)) {
                    $rows = $data->where(16, $paymentGateway);
                    foreach ($rows as $index => $row) {
                        $validator->errors()->add($index, 'Payment gateway invalid: '.$row[16].'. Valid values are '.implode(', ', $gateways));
                    }
                }
            }

            /**
             * Validate debit days
             */
            $debitDays = $data->pluck(17)->filter()->unique();

            $validDebitDays = DebitDay::query()
                ->where('is_active', true)
                ->get()
                ->pluck('import_code')
                ->toArray();

            foreach ($debitDays as $debitDay) {
                if (! in_array($debitDay, $validDebitDays)) {
                    $rows = $data->where(17, $debitDay);
                    foreach ($rows as $index => $row) {
                        $validator->errors()->add($index, 'Debit day invalid: '.$row[17].'. Valid values are '.implode(', ', $validDebitDays));
                    }
                }
            }

            /**
             * Validate location names
             */
            $locationNames = $data->pluck(0)->unique();

            $locations = Location::query()
                ->where('box_id', $this->tenantId)
                ->where('is_active', true)
                ->whereIn('box_facility_name', $locationNames)
                ->get();

            if ($locationNames->count() !== $locations->count()) {
                $rows = $data->whereIn(0, $locationNames->diff($locations->pluck('box_facility_name')));
                foreach ($rows as $index => $row) {
                    $validator->errors()->add($index, 'Location names invalid: '.$row[0]);
                }
            }

            /**
             * Validate programme names
             */
            $programmeNames = $data->pluck(4)->unique();

            $programmes = Programme::query()
                ->where('box_id', $this->tenantId)
                ->where('is_active', true)
                ->whereIn('name', $programmeNames)
                ->get();

            if ($programmeNames->count() !== $programmes->count()) {
                $rows = $data->whereIn(4, $programmeNames->diff($programmes->pluck('name')));
                foreach ($rows as $index => $row) {
                    $validator->errors()->add($index, 'Programme name invalid: '.$row[4]);
                }
            }

            /**
             * Validate package names
             */
            $packageNames = $data->pluck(1)->unique();

            $packages = Package::query()
                ->where('box_id', $this->tenantId)
                ->where('is_active', true)
                ->whereIn('package_name', $packageNames)
                ->get();

            if ($packageNames->count() !== $packages->count()) {
                $rows = $data->whereIn(1, $packageNames->diff($packages->pluck('package_name')));
                foreach ($rows as $index => $row) {
                    $validator->errors()->add($index, 'Package name invalid: '.$row[1]);
                }
            }

            /**
             * Validate package dates.
             */
            foreach ($data as $index => $row) {

                if (! $package = $packages->where('package_name', Arr::get($row, 1))->first()) {
                    continue;
                }

                $start = Arr::get($row, 2) ?? today();
                $end = Arr::get($row, 3) ?? $package->getDefaultPeriodInterval();

                $start = Carbon::parse($start);
                $end = Carbon::parse($end);

                if ($start->gte($end)) {
                    $validator->errors()->add(
                        $index,
                        "Package start date ({$start->toDateString()}) cannot be greater than the end date ({$end->toDateString()}) in row {$index}"
                    );
                }
            }

            /**
             * Validate unique users.
             */
            $emails = $data->pluck(9);

            if ($emails->count() !== $emails->unique()) {

                $rows = $data->whereIn(9, $emails->duplicates());

                foreach ($rows as $index => $row) {
                    $validator->errors()->add($index, 'Duplicate email address');
                }
            }

            $existingUsers = TenantUser::query()
                ->select('user_to_box.*')
                ->addSelect('users.email')
                ->joinRelationship('user')
                ->whereBoxId($this->tenantId)
                ->whereIn('users.email', $emails)
                ->get();

            foreach ($existingUsers as $tenantUser) {

                if (! $tenantUser->isMember()) {
                    $rows = $data->where(9, $tenantUser->email);
                    foreach ($rows as $index => $row) {
                        $validator->errors()->add($index, 'Email address '.$tenantUser->email.' already registered as staff.');
                    }
                } else {
                    //check if onboarded to location
                    $rows = $data->where(9, $tenantUser->email);

                    foreach ($rows as $index => $row) {
                        if ($location = $locations->where('box_facility_name', $row[0])->first()) {
                            if ($locationUser = LocationUser::query()
                                ->where('box_facility_id', $location->getKey())
                                ->where('user_id', $tenantUser->user_id)
                                ->first()
                            ) {
                                if ($location->is_active) {
                                    $validator->errors()->add(
                                        $index,
                                        'Email address '.$tenantUser->email.' already registered athlete at '.$location->name.' with user status: '.$tenantUser->status->toString()
                                    );
                                } else {
                                    // deactivate it because location has been deactivated
                                    $locationUser->update([
                                        'end_date' => now()->subDay(),
                                    ]);
                                }
                            }
                        }
                    }
                }
            }

            /**
             * Load and empty the validator errors for custom behavior.
             */
            foreach ($validator->errors()->keys() as $key) {

                $baseKey = str($key)->before('.')->toString();

                if ($this->errors->has($baseKey)) {
                    $this->errors->merge($baseKey, $this->errors->get($key));
                } else {
                    $this->errors->add([
                        'row' => $baseKey,
                        'errors' => $validator->errors()->get($key),
                    ]);
                }

                $validator->errors()->forget($key);
            }
        });
    }

    public function collection(Collection $rows): void
    {
        /**
         * Load the data
         */
        $this->data = $rows;
        $crmService = resolve(CrmService::class);

        $tenant = Tenant::findOrFail($this->tenantId);

        foreach ($rows as $index => $row) {

            /**
             * Skip rows with errors
             */
            $rowNumber = $index + 2;

            if ($this->errors->where('row', $rowNumber)->count()) {
                continue;
            }

            /**
             * Set data
             */
            $locationName = Arr::get($row, 0);
            $packageName = Arr::get($row, 1);
            $packageStartDate = Arr::get($row, 2);
            $packageEndDate = Arr::get($row, 3);
            $programmeName = Arr::get($row, 4);
            $memberId = Arr::get($row, 5);
            $firstName = Arr::get($row, 6);
            $lastName = Arr::get($row, 7);
            $gender = Arr::get($row, 9);
            $email = Arr::get($row, 9);
            $mobile = Arr::get($row, 10);
            $dateOfBirth = Arr::get($row, 11);
            $contractStart = Arr::get($row, 12);
            $contractEnd = Arr::get($row, 13);
            $packageSesssions = Arr::get($row, 14);
            $notes = Arr::get($row, 15);
            $paymentGateway = Arr::get($row, 16);
            $debitDay = Arr::get($row, 17);
            $dateJoined = Arr::get($row, 18);
            $memberIdNumber = Arr::get($row, 19);
            $memberAddress = Arr::get($row, 20);

            $location = Location::query()
                ->where('box_id', '=', $this->tenantId)
                ->where('box_facility_name', '=', $locationName)
                ->where('is_active', '=', true)
                ->firstOrFail();

            $package = Package::query()
                ->where('box_id', '=', $this->tenantId)
                ->where('packages.package_name', '=', $packageName)
                ->where('is_active', true)
                ->firstOrFail();

            $programme = Programme::query()
                ->where('box_id', '=', $this->tenantId)
                ->where('is_active', true)
                ->where('programmes.name', '=', $programmeName)
                ->firstOrFail();

            $userDebitStatus = $paymentGateway === 'gocardless' ? UserDebitStatus::DEBIT_ORDER : UserDebitStatus::CASH;

            if (strtolower($gender) === 'm' || strtolower($gender) === 'f') {
                $gender = strtolower($gender) === 'm' ? 1 : 2;
            } else {
                $gender = null;
            }

            $tenantUser = TenantUser::query()
                ->joinRelationship('user')
                ->with('user')
                ->where('box_id', '=', $this->tenantId)
                ->where('users.email', '=', $email)
                ->first();

            if ($tenantUser) {

                $user = $tenantUser->user;

                /**
                 * Create or update location user
                 */
                $locationUser = LocationUser::query()
                    ->where('user_id', $user->getAuthIdentifier())
                    ->where('box_facility_id', $location->getKey())
                    ->where('end_date', '>', today())
                    ->first();

                if ($locationUser && $locationUser->end_date->isPast()) {
                    $locationUser->update([
                        'end_date' => today()->addYear(),
                    ]);
                } else {
                    $locationUser = LocationUser::create([
                        'location_id' => $location->getKey(),
                        'user_id' => $user->getAuthIdentifier(),
                        'effective_date' => today(),
                        'end_date' => today()->addYear(),
                    ]);
                }

                /**
                 * Update existing tenant user and user objects
                 */
                $tenantUser->update([
                    'end_date' => $tenantUser->end_date->isPast() ? now()->addYear() : $tenantUser->end_date,
                    'user_type_id' => UserType::GYM_MEMBER,
                    'user_status_id' => UserStatus::ACTIVE,
                    'user_debit_status_id' => $userDebitStatus,
                    'programme_id' => $programme->getKey(),
                    'member_id' => $memberId,
                    'notes' => $notes,
                ]);

                /**
                 * Update user
                 */
                $user->update([
                    'name' => $firstName,
                    'surname' => $lastName,
                    'gender_id' => $gender,
                    'email' => $email,
                    'mobile' => $mobile,
                    'dob' => $dateOfBirth,
                    'id_number' => $memberIdNumber ?: $user->id_number,
                    'address' => $memberAddress ?: $user->address,
                ]);

                /**
                 * Deactivate current packages
                 */
                $user->userPackages(fn ($q) => $q->active())->update([
                    'end_date' => now()->subDay(),
                    'deleted' => true,
                ]);

                /**
                 * Deactivate recurring bookings.
                 */
                ClassRecurringBooking::query()
                    ->leftJoin('classes', 'classes.class_id', '=', 'class_recurring_bookings.class_id')
                    ->leftJoin('box_facility', 'box_facility.box_facility_id', '=', 'classes.box_facility_id')
                    ->leftJoin('boxes', 'boxes.box_id', '=', 'box_facility.box_id')
                    ->where('class_recurring_bookings.user_id', '=', $user->getAuthIdentifier())
                    ->where('classes.box_id', '=', $this->tenantId)
                    ->where('classes.is_active', '=', true)
                    ->where('box_facility.is_active', '=', true)
                    ->where('class_recurring_bookings.active', '=', true)
                    ->where('boxes.box_status_id', '=', TenantStatus::ACTIVE)
                    ->update([
                        'active' => false,
                    ]);
            } else {
                /**
                 * Create new user.
                 */
                $user = User::create([
                    'name' => $firstName,
                    'surname' => $lastName,
                    'gender_id' => $gender,
                    'email' => $email,
                    'mobile' => $mobile,
                    'dob' => $dateOfBirth,
                    'created_on' => $dateJoined ?? now(),
                    'id_number' => $memberIdNumber ?: null,
                    'address' => $memberAddress ?: null,
                    'user_status_id' => UserStatus::ACTIVE->value,
                    'user_type_id' => UserType::USER->value,
                ]);

                $tenantUser = TenantUser::create([
                    'tenant_id' => $this->tenantId,
                    'user_id' => $user->getAuthIdentifier(),
                    'effective_date' => now(),
                    'end_date' => now()->addYear(),
                    'user_type_id' => UserType::GYM_MEMBER,
                    'user_status_id' => UserStatus::ACTIVE,
                    'user_debit_status_id' => $userDebitStatus,
                    'programme_id' => $programme->getKey(),
                    'member_id' => $memberId,
                    'notes' => $notes,
                    'activated_on' => now(),
                ]);

                $locationUser = LocationUser::create([
                    'location_id' => $location->getKey(),
                    'user_id' => $user->getAuthIdentifier(),
                    'effective_date' => now(),
                    'end_date' => now()->addYear(),
                ]);
            }

            /**
             * Create user package
             */
            $sessionsAvailable = $packageSesssions;

            if ($package->type === PackageType::LIMITED) {
                $sessionsAvailable = $packageSesssions ?? $package->limit;
            }

            $packageStartDate = $packageStartDate ?? today()->toDateString();

            UserPackage::create([
                'user_id' => $user->getAuthIdentifier(),
                'package_id' => $package->getKey(),
                'sessions_available' => $sessionsAvailable,
                'effective_date' => $packageStartDate,
                'end_date' => $packageEndDate ?? $package->getDefaultPeriodInterval(Carbon::parse($packageStartDate)),
            ]);

            /**
             * Create banking details for GoCardless
             */
            if ($paymentGateway === 'gocardless' && $location->paymentGateway->name === 'GoCardless') {
                $debitDay = DebitDay::query()
                    ->where('import_code', '=', $debitDay)
                    ->first();

                UserBankingDetail::create([
                    'user_id' => $user->getAuthIdentifier(),
                    'box_id' => $tenant->getKey(),
                    'debit_day_id' => $debitDay->getKey(),
                ]);

                (new DebitBatchService())->generateFutureDebitBatchesForUser(
                    $location,
                    $debitDay,
                    $user
                );
            }

            /**
             * Create user contract
             */
            if (strlen($contractStart) >= 8 && strlen($contractEnd) >= 8) {

                $startDate = Carbon::parse($contractStart);
                $endDate = Carbon::parse($contractEnd);

                UserContract::create([
                    'user_id' => $user->getKey(),
                    'box_id' => $this->tenantId,
                    'box_facility_id' => $location->getKey(),
                    'starting_on' => $startDate,
                    'ending_on' => $endDate,
                    'contract_terms_and_conditions' => $tenant->contract_terms_and_conditions,
                ]);
            }

            /**
             * Send welcome mailer
             */
            $crmService->scheduleWelcomeMessageMailer(
                user: $user,
                tenant: $tenant,
                isSendImmediately: false,
                isSignUp: false,
            );
        }
    }
}

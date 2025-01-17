<?php

namespace App\Services;

use App\Enums\DebitOrderSetting as EnumsDebitOrderSetting;
use App\Enums\InvoiceItemDiscriminator;
use App\Enums\InvoicePaymentType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentGateway;
use App\Models\Bank;
use App\Models\DebitBatch;
use App\Models\DebitOrderSetting;
use App\Models\FinanceDiscount;
use App\Models\Location;
use App\Models\LocationUser;
use App\Models\LocationUserDiscount;
use App\Models\SpecialRate;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\UserInvoice;
use App\Models\UserInvoiceItem;
use App\Models\UserInvoicePayment;
use App\Models\UserPackage;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FinanceService
{
    public function isUserOnSpecialRateOrDiscount(User $user, string|int $tenantId): bool
    {
        if (SpecialRate::for($user->getAuthIdentifier(), $tenantId)) {
            return true;
        }

        $locationUser = LocationUser::query()
            ->whereRelation('location', 'box_id', '=', $tenantId)
            ->where('user_id', $user->getAuthIdentifier())
            ->active()
            ->first();

        if ($locationUser?->activeDiscounts()->first()) {
            return true;
        }

        return false;
    }

    public function getPackageTotal(string|int $tenantId, string|int $userId, ?Collection $userPackages = null): float
    {
        if (! $userPackages) {
            $userPackages = UserPackage::query()
                ->with('package')
                ->where('user_id', $userId)
                ->whereRelation('package', 'box_id', '=', $tenantId)
                ->active()
                ->get();
        } else {
            $userPackages->loadMissing('package');
        }

        return $userPackages->map(function ($userPackage) {
            return $userPackage->package->getPrice();
        })->sum();
    }

    public function getDiscountArray(LocationUser $locationUser, float $packagePrice): ?array
    {
        $userDiscount = $locationUser->activeDiscounts()->latest('facility_membership_discount_id')->first();
        $specialRate = SpecialRate::for($locationUser->user_id, $locationUser->location->tenant_id);
        $discountAmount = $this->calculateDiscountAmount($packagePrice, $specialRate, $userDiscount);

        if ($specialRate) {
            return [
                'description' => 'Special rate discount',
                'discriminator' => InvoiceItemDiscriminator::SPECIAL_DISCOUNT,
                'quantity' => 1,
                'unitPrice' => -$discountAmount,
                'amount' => -$discountAmount,
            ];
        }

        if ($userDiscount?->discount->type === 'fixed' || $userDiscount?->discount->type === 'percentage') {
            return [
                'description' => 'Discount: '.$userDiscount->discount->name,
                'discriminator' => InvoiceItemDiscriminator::DISCOUNT,
                'quantity' => 1,
                'unitPrice' => -$discountAmount,
                'amount' => -$discountAmount,
            ];
        }

        return null;
    }

    public function calculateDiscountAmount(float $packagePrice, ?SpecialRate $specialRate, ?LocationUserDiscount $userDiscount): ?float
    {
        if ($specialRate) {
            return $packagePrice - $specialRate->amount;
        }

        if ($userDiscount) {
            $userDiscount->loadMissing('discount');

            if ($userDiscount->discount?->type === 'fixed') {
                return $userDiscount->discount->amount;
            }

            if ($userDiscount->discount?->type === 'percentage') {
                return round($packagePrice * ($userDiscount->discount->amount / 100), 2);
            }
        }

        return null;
    }

    public function getPaymentsForInvoiceByType($invoice, $type): Collection|array
    {
        return UserInvoicePayment::query()
            ->where('invoice_id', '=', $invoice->getKey())
            ->where('deleted', '=', false)
            ->where('type', '=', $type)
            ->orderBy('date_time', 'ASC')
            ->distinct()
            ->get();
    }

    public function getPaymentsForInvoiceByOtherThanDebitOrderType($invoice): Collection|array
    {
        return UserInvoicePayment::query()
            ->where('invoice_id', '=', $invoice->getKey())
            ->where('deleted', '=', false)
            ->where('type', '!=', InvoicePaymentType::DEBIT_ORDER->value)
            ->orderBy('date_time', 'ASC')
            ->distinct()
            ->get();
    }

    public function generateInvoiceForUser(User $user, DebitBatch $debitBatch, $proRate = null): ?UserInvoice
    {
        $tenant = $debitBatch->location->tenant;
        $locationUser = (new TenantUserService())->getLocationUserByTenant($user->getKey(), $tenant->getKey(), $debitBatch->location_id);

        if (! $locationUser instanceof LocationUser) {
            return null;
        }

        if (! $proRate) {
            $userPackages = (new UserPackageService())->getActiveUserPackagesExcludingLimitedPackagesForTenant($user, $tenant, $debitBatch->debitDayDate->debit_day_date);

            if ($userPackages->isEmpty()) {
                return null;
            }
        }

        $userDiscount = LocationUserDiscount::query()
            ->where('user_to_facility_id', '=', $locationUser->getKey())
            ->where('status', '=', 'active')
            ->orderBy('facility_membership_discount_id', 'desc')
            ->first();

        $specialRate = SpecialRate::for($user, $tenant);

        $debitOrderSetting = DebitOrderSetting::query()
            ->where('box_id', '=', $debitBatch->location->tenant->getKey())
            ->where('debit_day_id', '=', $debitBatch->debitDayDate->debitDay->getKey())
            ->first();

        $invoice = UserInvoice::create([
            'code' => $this->generateInvoiceNumberForFacility($locationUser->location),
            'user_to_facility_id' => $locationUser->getKey(),
            'type' => InvoiceType::INVOICE->value,
            'description' => str('Membership invoice')->when($proRate, fn ($str) => $str->append(' (Prorate)'))->toString(),
            'status' => InvoiceStatus::PENDING->value,
            'currency' => $tenant->memberCurrency->code,
            'user_to_package_id' => (new UserPackageService())->getActiveUserPackageForTenant($user, $tenant, $debitBatch->debitDayDate->debit_day_date)?->getKey(),
            'discriminator' => InvoiceType::INVOICE,
        ]);

        // If $proRate was sent through then create special line item
        if ($proRate) {
            // Create membership invoice-item
            $invoice->invoiceItems()->create([
                'discriminator' => 'membership',
                'description' => 'Pro-rate',
                'unitPrice' => $proRate,
                'amount' => $proRate,
            ]);
        } else {
            /** @var UserPackage $userPackage */
            foreach ($userPackages as $userPackage) {
                $invoice->invoiceItems()->create([
                    'discriminator' => 'membership',
                    'description' => $userPackage->package->package_name,
                    'unitPrice' => $userPackage->package->package_price,
                    'amount' => $userPackage->package->package_price,
                    'user_to_package_id' => $userPackage->getKey(),
                ]);
            }

            // Special rate is first priority
            if ($specialRate instanceof SpecialRate && $specialRate->amount > 0) {
                $specialRateDiscount = (new UserPackageService())->getActiveUserPackagesTotalForTenant($user, $tenant, $debitBatch->debitDayDate->debit_day_date) - $specialRate->amount;

                $invoice->invoiceItems()->create([
                    'discriminator' => 'special_discount',
                    'description' => 'Special rate discount ('.$tenant->memberCurrency->code.' '.$specialRate->amount.')',
                    'unitPrice' => -$specialRateDiscount,
                    'amount' => -$specialRateDiscount,
                ]);
            } // Then discount amount
            elseif ($userDiscount instanceof LocationUserDiscount) {
                $discountAmount = 0;
                $discount = $userDiscount->discount;

                if ($discount->type == 'fixed') {
                    $discountAmount = $discount->amount;
                } elseif ($discount->type == 'percentage') {
                    $discountAmount = (new UserPackageService())->getActiveUserPackagesTotalForTenant($user, $tenant, $debitBatch->debitDayDate->debit_day_date) * ($discount->amount / 100);
                }

                $invoice->invoiceItems()->create([
                    'discriminator' => 'discount',
                    'description' => 'Discount : '.$userDiscount->discount->name,
                    'unitPrice' => -$discountAmount,
                    'amount' => -$discountAmount,
                ]);
            }
        }

        // Use $proRate if one has been passed
        $rate = $proRate ?: $this->calculateMemberFee($user, $tenant, $debitBatch->debitDayDate->debit_day_date, true);

        if ($rate === 0) {
            return null;
        }

        $invoice->setAttribute('amount', $rate);

        // Set the invoice dates
        if ($debitOrderSetting instanceof DebitOrderSetting && $debitOrderSetting->debit_order_invoice_date === EnumsDebitOrderSetting::FIRST_OF_NEXT_MONTH) {
            $date = $debitBatch->debitDayDate->debit_day_date->addMonth()->firstOfMonth();
            $endDate = clone $date;

            $invoice->update([
                'due_on' => $date,
                'period_start' => $date,
                'period_end' => $endDate->lastOfMonth(),
            ]);
        } else {
            $invoice->update([
                'due_on' => $debitBatch->debitDayDate->debit_day_date,
                'period_start' => $debitBatch->debitDayDate->debit_day_date,
                'period_end' => $debitBatch->debitDayDate->debit_day_date,
            ]);
        }

        $invoice->save();

        return $invoice->refresh();
    }

    public function generateInvoiceNumberForFacility(Location $location): string
    {
        $invoiceIndex = Cache::lock('invoice_code_'.$location->getKey(), 2)->block(3, function () use ($location) {
            // This is to make sure that we also have a fresh reference of the entity for the invoice code
            $location->refresh();

            $invoiceIndex = $location->invoice_code_index ?? 1;
            $nextAvailableIndex = $invoiceIndex + 1;

            $location->update([
                'invoice_code_index' => $nextAvailableIndex,
            ]);

            return $invoiceIndex;
        });

        $prefix = $location->box_facility_prefix;

        return $prefix.str_pad($invoiceIndex, 7, '0', STR_PAD_LEFT);
    }

    public function calculateMemberFee(User $user, Tenant $tenant, $date = null, ?bool $excludeLimitedPackages = false): string
    {
        $userSpecialRate = SpecialRate::for($user, $tenant);

        if ($userSpecialRate instanceof SpecialRate) {
            return $userSpecialRate->amount;
        }

        $packagePrice = 0;

        $packages = $excludeLimitedPackages ? (new UserPackageService())->getActiveUserPackagesExcludingLimitedPackagesForTenant($user, $tenant, $date) : (new UserPackageService())->getActiveUserPackagesForTenant($user, $tenant, $date);

        /** @var UserPackage $package */
        foreach ($packages as $package) {
            $packagePrice += $package->package->price;
        }

        $location = (new TenantUserService())->getLocationUserByTenant($user, $tenant)?->location;

        if ($location) {
            /** @var LocationUserDiscount $userDiscount */
            $userDiscount = (new LocationDiscountService())->getFacilityMembershipDiscountForUser($user, $location);

            if ($userDiscount && $userDiscount->discount->type === 'fixed') {
                $packagePrice = $packagePrice - $userDiscount->discount->amount;
            } elseif ($userDiscount && $userDiscount->discount->type === 'percentage') {
                $packagePrice = $packagePrice - ((float) $userDiscount->discount->amount / 100 * $packagePrice);
            }
        }

        return number_format($packagePrice, 2, '.', '');
    }

    public function generateInvoiceForNewUserPackage(User $member, Location $boxFacility, UserPackage $userPackage, $rate, $debitBatchId = null): UserInvoice
    {
        $invoice = new UserInvoice();

        $tenant = $boxFacility->tenant;

        $userFacilityMembership = (new TenantUserService())->getLocationUserByTenant($member, $tenant);

        if ($boxFacility->box_facility_prefix) {
            $invoice->setAttribute('code', $boxFacility->box_facility_prefix.uniqid('', false));
        } else {
            $invoice->setAttribute('code', 'IN'.uniqid('', false));
        }
        // Create invoice

        // For debit order invoices only
        if ($debitBatchId) {
            $invoice->setAttribute('status', InvoiceStatus::PENDING->value);

            // Get debit batch entity
            $debitBatch = DebitBatch::query()->find($debitBatchId);

            // Get Debit debit date
            $debitDayDate = $debitBatch->debitDayDate;

            // Get debit-order settings for debit day and box
            $debitOrderSetting = DebitOrderSetting::query()
                ->where('box_id', '=', $tenant->getKey())
                ->where('debit_day_id', '=', $debitDayDate->debit_day_id)
                ->first();

            // Get debitDay settings for this box
            if ($debitOrderSetting && $debitOrderSetting->debit_order_invoice_date == 'first day of the next month') {
                $date = new DateTime($debitDayDate->debit_day_date->format('Y-m-d'));

                $date->modify('+1 month');
                $date->modify('first day of this month');

                $invoice->setAttribute('due_on', $date);
                $invoice->setAttribute('period_start', $date);
                $invoice->setAttribute('period_end', $date);
            } else {
                $invoice->setAttribute('due_on', $debitDayDate->debit_day_date);
                $invoice->setAttribute('period_start', $debitDayDate->debit_day_date);
                $invoice->setAttribute('period_end', $debitDayDate->debit_day_date);
            }
        } else {
            $date = new DateTime();

            $invoice->setAttribute('status', InvoiceStatus::UNPAID->value);
            $invoice->setAttribute('due_on', $date);
            $invoice->setAttribute('period_start', $date);
            $invoice->setAttribute('period_end', $date);
        }

        $isProRate = $rate != $userPackage->package->package_price;

        $invoice->setAttribute('user_to_facility_id', $userFacilityMembership->getKey());
        $invoice->setAttribute('type', InvoiceType::INVOICE);
        $invoice->setAttribute('description', $isProRate ? 'Membership invoice (Prorate)' : 'Membership invoice');
        $invoice->setAttribute('amount', $rate);
        $invoice->setAttribute('currency', $tenant->memberCurrency->code);
        $invoice->setAttribute('created_by_id', $member->getKey());
        $invoice->setAttribute('user_to_package_id', $userPackage->getKey());

        $invoice->save();

        // Create membership invoice-item
        $newInvoiceItem = new UserInvoiceItem();
        $description = $userPackage->package->package_name;

        if ($isProRate) {
            $description .= ' (Prorate)';
        }

        $newInvoiceItem->setAttribute('invoice_id', $invoice->getKey());
        $newInvoiceItem->setAttribute('discriminator', $isProRate ? 'prorate' : 'membership');
        $newInvoiceItem->setAttribute('description', $description);
        $newInvoiceItem->setAttribute('unitPrice', $rate);
        $newInvoiceItem->setAttribute('quantity', 1);
        $newInvoiceItem->setAttribute('amount', $rate);
        $newInvoiceItem->setAttribute('created_by_id', $member->getKey());

        $newInvoiceItem->save();

        return $invoice;
    }

    public function generateProRateInvoiceForUser(UserPackage $userPackage, DebitBatch $debitBatch): ?UserInvoice
    {
        $userFacilityMembership = (new TenantUserService())->getLocationUserByTenant($userPackage->user, $debitBatch->location->tenant);

        if (! $userFacilityMembership instanceof LocationUser) {
            return null;
        }

        $invoice = UserInvoice::create([
            'code' => $this->generateInvoiceNumberForFacility($debitBatch->location),
            'user_to_facility_id' => $userFacilityMembership->getKey(),
            'type' => InvoiceType::INVOICE,
            'description' => 'Membership invoice',
            'currency' => $debitBatch->location->tenant->memberCurrency->code,
            'user_to_package_id' => $userPackage->getKey(),
            'amount' => $userPackage->package->getExclusiveProrateAmount() + $userPackage->package->package_price,
            'status' => InvoiceStatus::PENDING,
        ]);

        // Pro-rate line item
        UserInvoiceItem::create([
            'invoice_id' => $invoice->getKey(),
            'discriminator' => 'prorate',
            'description' => $userPackage->package->name.' (Prorate)',
            'quantity' => 1,
            'unitPrice' => $userPackage->package->getExclusiveProrateAmount(),
            'amount' => $userPackage->package->getExclusiveProrateAmount(),
        ]);

        // Membership line item
        UserInvoiceItem::create([
            'invoice_id' => $invoice->getKey(),
            'discriminator' => 'membership',
            'description' => $userPackage->package->name,
            'quantity' => 1,
            'unitPrice' => $userPackage->package->package_price,
            'amount' => $userPackage->package->package_price,
        ]);

        $tenant = $debitBatch->location->tenant;

        $debitOrderSetting = DebitOrderSetting::query()
            ->where('box_id', '=', $tenant->getKey())
            ->where('debit_day_id', '=', $debitBatch->debitDayDate->debitDay->getKey())
            ->first();

        // set the invoice dates
        if ($debitOrderSetting instanceof DebitOrderSetting && $debitOrderSetting->debit_order_invoice_date == 'first day of the next month') {
            $date = $debitBatch->debitDayDate->date;
            $date->modify('first day of next month');

            $invoice->update([
                'due_on' => $date->toDateString(),
                'period_start' => $date->toDateString(),
                'period_end' => $date->toDateString(),
            ]);
        } else {
            $invoice->update([
                'due_on' => $debitBatch->debitDayDate->date,
                'period_start' => $debitBatch->debitDayDate->date,
                'period_end' => $debitBatch->debitDayDate->date,
            ]);
        }

        return $invoice;
    }

    public function generateInvoiceForUserPackage(User $user, UserPackage $userPackage, Location $location, string $discriminator): ?UserInvoice
    {
        $locationUser = (new TenantUserService())->getLocationUserByTenant($user, $location->tenant);

        if (! $locationUser instanceof LocationUser) {
            return null;
        }

        $rate = 0;
        $date = now();
        $tenant = $location->tenant;

        // Create invoice
        $invoice = UserInvoice::create([
            'code' => (new FinanceService())->generateInvoiceNumberForFacility($location),
            'user_to_facility_id' => $locationUser->getKey(),
            'type' => InvoiceType::INVOICE,
            'description' => 'Membership invoice',
            'status' => InvoiceStatus::UNPAID,
            'currency' => $tenant->memberCurrency->code,
            'user_to_package_id' => $userPackage->getKey(),
            'due_on' => $date,
            'period_start' => $date,
            'period_end' => $date,
            'discriminator' => $discriminator,
        ]);

        // Membership line item
        UserInvoiceItem::create([
            'invoice_id' => $invoice->getKey(),
            'discriminator' => 'membership',
            'description' => $userPackage->package->name,
            'quantity' => 1,
            'unitPrice' => $userPackage->package->package_price,
            'amount' => $userPackage->package->package_price,
        ]);

        $rate += $userPackage->package->package_price;

        if ($tenant->pro_rate_strategy === 'automatic') {
            // Pro-rate line item
            UserInvoiceItem::create([
                'invoice_id' => $invoice->getKey(),
                'discriminator' => 'prorate',
                'description' => $userPackage->package->name.' (Prorate)',
                'quantity' => 1,
                'unitPrice' => $userPackage->package->getExclusiveProrateAmount(),
                'amount' => $userPackage->package->getExclusiveProrateAmount(),
            ]);

            $rate += $userPackage->package->getExclusiveProrateAmount();
        }

        $invoice->update([
            'amount' => $rate,
        ]);

        return $invoice;
    }

    public function validateDebitOrderPaymentDetails(array $paymentDetails, Location $location): array
    {
        $errors = [];

        if (! isset($paymentDetails['debit_day_id']) || $paymentDetails['debit_day_id'] == '') {
            $errors['debit_day_id'] = 'Debit day is required';
        }

        if (in_array($location->paymentGateway->getKey(), [PaymentGateway::GO_CARDLESS->value, PaymentGateway::STRIPE_CONNECT->value])) {
            return $errors;
        }

        if ($location->paymentGateway->getKey() === PaymentGateway::SEPA->value) {
            if (! isset($paymentDetails['account_holder_name']) || $paymentDetails['account_holder_name'] == '') {
                $errors['account_holder_name'] = 'Account holder\'s name is a required field';
            }

            if (! isset($paymentDetails['iban']) || $paymentDetails['iban'] == '') {
                $errors['iban'] = 'Iban is a required field';
            }

            if (! isset($paymentDetails['bic']) || $paymentDetails['bic'] == '') {
                $errors['bic'] = 'Bic is a required field';
            }

            return $errors;
        }

        $bankId = Arr::get($paymentDetails, 'bank_id');

        $bank = $bankId ? Bank::query()->find($bankId) : null;

        if (! isset($paymentDetails['bank_id']) || $paymentDetails['bank_id'] == '') {
            $errors['bank_id'] = 'Bank is a required field';
        }

        if (is_null($bank)) {
            $errors['bank_id'] = 'Bank ID must be valid.';
        }

        if (! isset($paymentDetails['account_type_id']) || $paymentDetails['account_type_id'] == '') {
            $errors['account_type_id'] = 'Account type is a required field';
        }

        if (! isset($paymentDetails['account_number']) || $paymentDetails['account_number'] == '') {
            $errors['account_number'] = 'Account number is a required field';
        }

        if (! isset($paymentDetails['account_holder_name']) || $paymentDetails['account_holder_name'] == '') {
            $errors['account_holder_name'] = 'Account holder\'s name is a required field';
        }

        if ($bank && (! $bank->universal_code) && (! isset($paymentDetails['branch_code']) || $paymentDetails['branch_code'] == '')) {
            $errors['branch_code'] = 'Branch code is required because the bank does not have a default branch code';
        }

        return $errors;
    }

    public function createOrUpdateUserSpecialRate(TenantUser $tenantUser, $specialRateAmount): void
    {
        // Check for existing special rate
        $specialRate = SpecialRate::for($tenantUser->user_id, $tenantUser->tenant_id);

        if ($specialRate instanceof SpecialRate && $specialRate->amount != $specialRateAmount) {
            // Disable existing rate
            $specialRate->update(['is_active' => false]);

            // Set to null to create new one if this was and update
            $specialRate = null;
        }

        if (! $specialRate instanceof SpecialRate) {
            SpecialRate::create([
                'user_id' => $tenantUser->user_id,
                'box_id' => $tenantUser->tenant_id,
                'amount' => (float) $specialRateAmount,
            ]);
        }
    }

    public function createFacilityMembershipDiscount(LocationUser $userFacilityMembership, FinanceDiscount $discount, ?DateTime $startDate = null): LocationUserDiscount
    {
        if (! $startDate) {
            $startDate = new DateTime();
        }

        $facilityMembershipDiscount = new LocationUserDiscount();
        $facilityMembershipDiscount->setAttribute('discount_id', $discount->getKey());
        $facilityMembershipDiscount->setAttribute('user_to_facility_id', $userFacilityMembership->getKey());
        $facilityMembershipDiscount->setAttribute('starting_on', $startDate);
        $facilityMembershipDiscount->setAttribute('status', 'active');
        $facilityMembershipDiscount->save();

        return $facilityMembershipDiscount;
    }

    public function generateUpfrontInvoiceAndPayment(TenantUser $userBoxMembership, $amount, $periodStartDate, $periodEndDate, ?InvoicePaymentType $paymentType): ?UserInvoice
    {
        $invoice = new UserInvoice();

        $box = $userBoxMembership->tenant;
        $user = $userBoxMembership->user;
        $facility = (new TenantUserService())->getLocationUserByTenant($user, $box)?->location;
        $userPackage = (new UserPackageService())->getActiveUserPackagesForTenant($user, $box)->first();
        $amount = preg_replace('/[^0-9.]/', '', $amount);

        if ($facility && $userPackage) {
            $userFacilityMembership = (new TenantUserService())->getLocationUserByTenant($user, $box);

            if ($facility->prefix) {
                $invoice->setAttribute('code', $facility->prefix.uniqid('', false));
            } else {
                $invoice->setAttribute('code', 'IN'.uniqid('', false));
            }

            // Create invoice
            $invoice->setAttribute('user_to_facility_id', $userFacilityMembership->getKey());
            $invoice->setAttribute('type', InvoiceType::INVOICE->value);
            $invoice->setAttribute('description', 'Membership invoice - Up-front payment');
            $invoice->setAttribute('amount', (float) $amount);
            $invoice->setAttribute('status', InvoiceStatus::PAID->value);
            $invoice->setAttribute('currency', $facility->tenant->memberCurrency->code);
            $invoice->setAttribute('created_by_id', auth()->user()->getAuthIdentifier());
            $invoice->setAttribute('user_to_package_id', $userPackage->getKey());
            $invoice->setAttribute('due_on', now());
            $invoice->setAttribute('period_start', $periodStartDate);
            $invoice->setAttribute('period_end', $periodEndDate);
            $invoice->setAttribute('upfront_user_id', $user->getKey());
            $invoice->save();

            // Create membership invoice-item
            $newInvoiceItem = new UserInvoiceItem();
            $newInvoiceItem->setAttribute('invoice_id', $invoice->getKey());
            $newInvoiceItem->setAttribute('discriminator', 'membership');
            $newInvoiceItem->setAttribute('description', 'Up-front payment : '.$userPackage->package->package_name);
            $newInvoiceItem->setAttribute('unitPrice', $amount);
            $newInvoiceItem->setAttribute('quantity', 1);
            $newInvoiceItem->setAttribute('amount', $amount);
            $newInvoiceItem->save();

            // Create payment
            $newPayment = new UserInvoicePayment();

            if (! $paymentType) {
                $paymentType = InvoicePaymentType::CASH;
            }

            $newPayment->setAttribute('invoice_id', $invoice->getKey());
            $newPayment->setAttribute('date_time', now());
            $newPayment->setAttribute('amount', $amount);
            $newPayment->setAttribute('type', $paymentType->value);
            $newPayment->setAttribute('currency', $box->memberCurrency->code);
            $newPayment->setAttribute('user_to_facility_id', $invoice->user_to_facility_id);
            $newPayment->setAttribute('reference', 'Up-front payment for '.$user->full_name);
            $newPayment->save();

            // Return the invoice
            return $invoice;
        }

        return null;
    }

    public function getDiscountForUserTenant(TenantUser $userBoxMembership): ?LocationUserDiscount
    {
        $box = $userBoxMembership->tenant;
        $user = $userBoxMembership->user;
        $location = (new TenantUserService())->getLocationUserByTenant($user, $box)?->location;

        $userDiscount = (new LocationDiscountService())->getFacilityMembershipDiscountForUser($user, $location);

        if ($userDiscount instanceof LocationUserDiscount) {
            return $userDiscount;
        }

        return null;
    }

    public function generateOnHoldUserProRateInvoice(User $member, Tenant $tenant, $rate, Carbon $debitDayDate): ?UserInvoice
    {
        $userLocation = (new TenantUserService())->getLocationUserByTenant($member->getKey(), $tenant->getKey());

        $location = $userLocation->location;

        $userPackage = (new UserPackageService)->getActiveUserPackageForTenant($member, $tenant, $debitDayDate);

        if ($location && $userPackage) {
            $invoice = UserInvoice::create([
                'user_to_facility_id' => $userLocation->getKey(),
                'type' => 'invoice',
                'description' => 'Membership invoice',
                'amount' => $rate,
                'status' => InvoiceStatus::PENDING,
                'due_on' => $debitDayDate->toDateString(),
                'period_start' => $debitDayDate->toDateString(),
                'period_end' => $debitDayDate->toDateString(),
                'currency' => $tenant->memberCurrency->code,
                'user_to_package_id' => $userPackage->getKey(),
                'code' => $this->generateInvoiceNumberForFacility($userLocation->location),
            ]);

            // Create membership invoice-item
            UserInvoiceItem::create([
                'invoice_id' => $invoice->getKey(),
                'discriminator' => 'membership',
                'description' => $userPackage->package->name,
                'unitPrice' => $rate,
                'quantity' => 1,
                'amount' => $rate,
            ]);

            return $invoice;
        }

        return null;
    }

    public function getAmountOutstanding(TenantUser $tenantUser, $endDate = null): float
    {
        if (! $endDate) {
            $endDate = now()->toDateTimeString();
        }

        // Get all user invoices
        $invoices = (new InvoiceService())->getInvoicesForUser($tenantUser->user, $tenantUser->tenant, null, null, $endDate);

        $totalPayments = 0;
        $totalOwing = 0;
        $totalCredit = 0;

        /** @var UserInvoice $invoice */
        foreach ($invoices as $invoice) {
            if ($invoice->type == InvoiceType::INVOICE) {
                $totalOwing = (float) bcadd($totalOwing, $invoice->amount, 2);

                if ($invoice->status != InvoiceStatus::CREDITED) {
                    /** @var UserInvoicePayment $payment */
                    foreach ($invoice->payments as $payment) {
                        if (! $payment->trashed()) {
                            $totalPayments = (float) bcadd($totalPayments, $payment->amount, 2);
                        }
                    }
                }
            } else {
                if ($invoice->type === InvoiceType::CREDIT_NOTE) {
                    $totalCredit = (float) bcadd($totalCredit, $invoice->amount, 2);
                }
            }
        }

        return (float) bcsub(bcsub($totalOwing, $totalCredit, 2), $totalPayments, 2);
    }

    public function validateIbanAndBic(string $iban, string $bic): ?string
    {
        $ibanApiKey = config('iban.api_key');

        if (is_null($ibanApiKey)) {
            return null;
        }

        try {
            $response = Http::asForm()->post('https://api.iban.com/clients/api/v4/iban/', [
                'format' => 'json',
                'api_key' => $ibanApiKey,
                'iban' => $iban,
            ]);

            $response->throw();

            $responseData = $response->json();

            if (isset($responseData['errors']) && is_array($responseData['errors']) && count($responseData['errors']) > 0) {
                $errorsString = '';

                foreach ($responseData['errors'] as $error) {
                    $errorsString .= "{$error['message']}. "."Error code: {$error['code']}".PHP_EOL;
                }

                return $errorsString;
            }

            if (isset($responseData['validations']) && is_array($responseData['validations'])) {
                $isInvalid = false;
                $validationsString = '';

                foreach ($responseData['validations'] as $validation) {
                    /*
                     * 201	Validation Failed	Account Number check digit not correct
                     * 202	Validation Failed	IBAN Check digit not correct
                     * 203	Validation Failed	IBAN Length is not correct
                     * 205	Validation Failed	IBAN structure is not correct
                     * 206	Validation Failed	IBAN contains illegal characters
                     * 207	Validation Failed	Country does not support IBAN standard
                    */

                    if (in_array($validation['code'], [201, 202, 203, 205, 206, 207])) {
                        $isInvalid = true;
                        $validationsString .= "Message: {$validation['message']}, Code: {$validation['code']}\n";
                    }
                }

                if ($isInvalid) {
                    return $validationsString;
                }
            }

            if (isset($responseData['bank_data']) && $responseData['bank_data']['bic']) {
                if (strtoupper($bic) !== $responseData['bank_data']['bic']) {
                    return 'The BIC you have supplied does not correspond with the Iban that you have supplied.';
                }
            }
        } catch (Exception|RequestException $e) {
            Log::error($e->getMessage());
        }

        return null;
    }
}

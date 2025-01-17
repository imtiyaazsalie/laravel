<?php

namespace App\Models;

use App\Enums\InvoiceDiscriminator;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Traits\BelongsToTenant;
use App\Traits\MutatesBoxFacilityId;
use App\Traits\MutatesUserToFacilityId;
use App\Traits\Paginatable;
use App\Traits\RecordUserOnCreateAndUpdate;
use Awobaz\Compoships\Database\Eloquent\Relations\HasOne;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Kirschbaum\PowerJoins\PowerJoins;

class UserInvoice extends Model
{
    use BelongsToTenant, HasFactory, MutatesBoxFacilityId, MutatesUserToFacilityId, Paginatable, PowerJoins, RecordUserOnCreateAndUpdate;

    public const CREATED_AT = 'created_on';

    public const UPDATED_AT = 'updated_on';

    public $timestamps = true;

    protected $table = 'finance_invoices';

    protected $primaryKey = 'invoice_id';

    protected $guarded = [];

    protected $casts = [
        'status' => InvoiceStatus::class,
        'discriminator' => InvoiceDiscriminator::class,
        'type' => InvoiceType::class,
        'due_on' => 'datetime',
        'sent_on' => 'datetime',
        'created_at' => 'datetime',
        'period_start' => 'date',
        'period_end' => 'date',
        'deleted' => 'integer',
    ];

    protected $attributes = [
        'discriminator' => InvoiceDiscriminator::INVOICE,
        'deleted' => false,
    ];

    protected $appends = [
        'balance_of_invoice',
        'outstanding_amount',
        'company_membership',
        'amount_in_cents',
    ];

    /**
     * Mutate facility_to_payment_gateway_id to location_payment_gateway_id
     */
    protected function locationPaymentGatewayId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->facility_to_payment_gateway_id,
            set: fn (mixed $value) => ['facility_to_payment_gateway_id' => $value]
        );
    }

    protected function tenantId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->location ? $this->location->tenant_id : $this->userLocation?->location->tenant_id,
        );
    }

    protected function userId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->userLocation?->user_id,
        );
    }

    public function userLocation(): BelongsTo
    {
        return $this->belongsTo(LocationUser::class, 'user_to_facility_id', 'user_to_facility_id');
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(UserInvoiceItem::class, 'invoice_id');
    }

    public function parentInvoice(): BelongsTo
    {
        return $this->belongsTo(UserInvoice::class, 'parent_invoice_id');
    }

    public function getOutstandingAmountAttribute(): string
    {
        return number_format($this->amount - ($this->payments()->where('deleted', false)->sum('amount') ?? 0), 2, '.', '');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(UserInvoicePayment::class, 'invoice_id');
    }

    public function recalculateTotal(): self
    {
        $this->amount = number_format($this->invoiceItems()->sum('amount'), 2, '.', '');

        $this->save();

        return $this;
    }

    public function location(): HasOne
    {
        return $this->hasOne(Location::class, 'box_facility_id', 'box_facility_id');
    }

    public function balanceOfInvoice(): Attribute
    {
        if ($this->locationUser()->doesntExist()) {
            return Attribute::make(get: fn () => 0);
        }

        $beforeDate = Carbon::parse($this->due_on)->subDay()->toDateString();

        $invoices = UserInvoice::with('payments')
            ->leftJoin('user_to_facility', 'user_to_facility.user_to_facility_id', '=', 'finance_invoices.user_to_facility_id')
            ->leftJoin('box_facility', 'box_facility.box_facility_id', '=', 'user_to_facility.box_facility_id')
            ->where('user_to_facility.user_id', '=', $this->locationUser->user_id)
            ->where('box_facility.is_active', '=', true)
            ->where('finance_invoices.type', '=', 'invoice')
            ->where('finance_invoices.due_on', '<', $beforeDate)
            ->where('finance_invoices.deleted', '=', false)
            ->get();

        $creditNotes = UserInvoice::leftJoin('user_to_facility', 'user_to_facility.user_to_facility_id', '=', 'finance_invoices.user_to_facility_id')
            ->leftJoin('box_facility', 'box_facility.box_facility_id', '=', 'user_to_facility.box_facility_id')
            ->where('user_to_facility.user_id', '=', $this->locationUser->user_id)
            ->where('box_facility.is_active', '=', true)
            ->where('finance_invoices.type', '=', 'creditNote')
            ->where('finance_invoices.due_on', '<', $beforeDate)
            ->where('finance_invoices.deleted', '=', false)
            ->get();

        $totalPayments = floatval(0);
        $totalOwing = floatval(0);
        $totalCredit = floatval(0);

        foreach ($invoices as $invoice) {
            if ($invoice->userBatch()->exists() && ! $invoice->userBatch->is_active) {
                continue;
            }

            $totalOwing = (float) bcadd($totalOwing, $invoice->amount, 2);

            foreach ($invoice->payments as $payment) {
                if (! $payment->deleted) {
                    $totalPayments = (float) bcadd($totalPayments, $payment->amount, 2);
                }
            }
        }

        foreach ($creditNotes as $creditNote) {
            $totalCredit = (float) bcadd($totalCredit, $creditNote->amount, 2);
        }

        return Attribute::make(
            get: fn () => (float) bcsub(bcsub($totalOwing, $totalCredit), $totalPayments),
        );
    }

    public function locationUser(): BelongsTo
    {
        return $this->belongsTo(LocationUser::class, 'user_to_facility_id', 'user_to_facility_id');
    }

    public function userBatch(): BelongsTo
    {
        return $this->belongsTo(UserBatch::class, 'invoice_id', 'invoice_id');
    }

    public function tenantUser(): ?TenantUser
    {
        return TenantUser::where('user_id', '=', $this->locationUser?->user_id)
            ->where('box_id', '=', $this->locationUser?->tenant?->box_id)->first();
    }

    public function scopeDueOnBetween(Builder $query, $from, $to): Builder
    {
        return $query->whereBetween('due_on', [Carbon::parse($from), Carbon::parse($to)]);
    }

    public function scopeSentStatus(Builder $query, $status): Builder
    {
        return match ($status) {
            'sent' => $query->whereNotNull('sent_on'),
            'unsent' => $query->whereNull('sent_on'),
            default => $query->where('sent_on', '=', null)
                ->orWhereDate('sent_on', '=', 'sent_on'),
        };
    }

    public function scopeSearch(Builder $query, $search): Builder
    {
        return $query->where('code', 'LIKE', "%$search%")
            ->orWhere('invoice_id', 'LIKE', "%$search%")
            ->orWhere('description', 'LIKE', "%$search%");
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->where('status', '!=', 'paid');
    }

    public function getAmountInCentsAttribute(): int
    {
        return (int) bcmul((string) $this->amount, '100');
    }

    public function getExVatTotalAttribute()
    {
        return number_format($this->invoiceItems->sum('ex_vat_amount'), 2, '.', '');
    }

    public function getVatTotalAttribute()
    {
        return number_format($this->invoiceItems->sum('vat_amount'), 2, '.', '');
    }

    public function getInvoiceEmailAttribute()
    {
        $this->loadMissing('locationUser');

        if ($this->locationUser) {
            return $this->locationUser->user_id ? $this->locationUser->user->email : $this->locationUser->leadMember->user->email;
        }

        return $this->non_member_email;
    }

    public function leadMember(): BelongsTo
    {
        return $this->belongsTo(LeadMember::class, 'lead_member_id');
    }

    public function userPackage(): BelongsTo
    {
        return $this->belongsTo(UserPackage::class, 'user_to_package_id');
    }

    public function locationPaymentGateway(): BelongsTo
    {
        return $this->belongsTo(LocationPaymentGateway::class, 'facility_to_payment_gateway_id');
    }

    public function isSent(): bool
    {
        return ! is_null($this->sent_on);
    }

    public function isPending(): bool
    {
        return $this->status === InvoiceStatus::PENDING;
    }

    public function isPaid(): bool
    {
        return $this->status === InvoiceStatus::PAID;
    }

    public function isUnpaid(): bool
    {
        return $this->status === InvoiceStatus::UNPAID;
    }

    public function getActivePayments()
    {
        return $this->payments->where('is_deleted', false);
    }

    public function getExistingPaymentsBy(float $amount, string $reference, string $type)
    {
        return $this->getActivePayments()->filter(function (UserInvoicePayment $payment) use ($amount, $reference, $type) {
            return $payment->amount === $amount && $payment->reference === $reference && $payment->type->value === $type;
        });
    }

    public function invoiceMemberName(): Attribute
    {
        if ($this->locationUser) {
            $user = $this->locationUser->leadMember ? $this->locationUser->leadMember->user() : $this->locationUser()->withoutGlobalScopes()->first()->user();
            $memberName = $user->withTrashed()->first()->full_name ?? null;
        } elseif ($this->leadMember) {
            $memberName = $this->leadMember->name;
        } else {
            $memberName = $this->non_member_name ?? $this->non_member_email;
        }

        return Attribute::make(
            get: fn () => $memberName,
        );
    }

    public function invoiceMemberAddress(): Attribute
    {
        $memberAddress = null;

        if ($this->locationUser) {
            $user = $this->locationUser->leadMember ? $this->locationUser->leadMember->user() : $this->locationUser()->withoutGlobalScopes()->first()->user();
            $memberAddress = $user->withTrashed()->first()->address ?? null;
        }

        return Attribute::make(
            get: fn () => $memberAddress,
        );
    }

    public function invoiceMemberSocialSecurityNumber(): Attribute
    {
        $memberSocialSecurityNumber = null;

        if ($this->locationUser) {
            $user = $this->locationUser->leadMember ? $this->locationUser->leadMember->user() : $this->locationUser()->withoutGlobalScopes()->first()->user();
            $memberSocialSecurityNumber = $user->withTrashed()->first()->id_number ?? null;
        }

        return Attribute::make(
            get: fn () => $memberSocialSecurityNumber,
        );
    }

    public function invoiceLocation(): Attribute
    {
        if ($this->userLocation) {
            $location = $this->userLocation->location;
        } elseif ($this->leadMember) {
            $location = $this->leadMember->location;
        } else {
            $location = $this->location;
        }

        return Attribute::make(
            get: fn () => $location,
        );
    }
}

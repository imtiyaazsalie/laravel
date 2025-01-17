<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Traits\IsOwnedByTenant;
use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UserBankingDetail extends Model
{
    use Compoships, HasFactory, IsOwnedByTenant;

    public const CREATED_AT = 'dt_added';

    public const UPDATED_AT = 'dt_modified';

    public $timestamps = true;

    protected $table = 'user_banking_details';

    protected $primaryKey = 'user_banking_details_id';

    protected $guarded = [];

    protected $casts = [
        'account_type_id' => AccountType::class,
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Mutate bank_other to other
     */
    protected function other(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->bank_other,
            set: fn (mixed $value) => ['bank_other' => $value]
        );
    }

    /**
     * Mutate account_type_id to account_type
     */
    protected function accountType(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->account_type_id,
            set: fn (mixed $value) => ['account_type_id' => $value]
        );
    }

    protected function accountNumber(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->account_no,
            set: fn (mixed $value) => ['account_no' => $value]
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }

    public function debitDay(): BelongsTo
    {
        return $this->belongsTo(DebitDay::class, 'debit_day_id');
    }

    /**
     * Formats a branch code for Netcash batch submission.
     */
    protected function formatBranchCodeForNetcashBatch(): Attribute
    {
        return Attribute::make(
            get: fn () => Str::padLeft(trim($this->branch_code), 6, 0),
        );
    }
}

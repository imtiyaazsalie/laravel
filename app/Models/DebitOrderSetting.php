<?php

namespace App\Models;

use App\Enums\DebitOrderSetting as EnumsDebitOrderSetting;
use App\Traits\IsOwnedByTenant;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DebitOrderSetting extends Model
{
    use HasFactory, IsOwnedByTenant;

    protected $table = 'debit_order_settings';

    protected $primaryKey = 'debit_order_settings_id';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'debit_order_invoice_date' => EnumsDebitOrderSetting::class,
    ];

    /**
     * Mutate debit_order_invoice_date to date
     */
    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->debit_order_invoice_date,
            set: fn (mixed $value) => ['debit_order_invoice_date' => $value]
        );
    }

    public function debitDay(): BelongsTo
    {
        return $this->belongsTo(DebitDay::class, 'debit_day_id');
    }

    public function isOnTheDate()
    {
        return in_array($this->date->value, [
            'date of debit-order',
            'on the date of debit-order',
        ]);
    }

    public function isFirstOfNextMonth()
    {
        return in_array($this->date->value, [
            'first day of next month',
        ]);
    }
}

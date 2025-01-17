<?php

namespace App\Models;

use App\Enums\PaymentGateway;
use App\Enums\TenantStatus;
use App\Enums\ThreePeaksApiProcessingStatus;
use App\Traits\IsOwnedByLocation;
use App\Traits\Paginatable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Kirschbaum\PowerJoins\PowerJoins;

class DebitBatch extends Model
{
    use HasFactory, IsOwnedByLocation, Paginatable, PowerJoins;

    protected $table = 'debit_batches';

    protected $primaryKey = 'debit_batch_id';

    protected $guarded = [];

    public $timestamps = true;

    public const CREATED_AT = 'dt_added';

    public const UPDATED_AT = 'dt_modified';

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_processed' => 'integer',
        'dt_processed' => 'datetime',
        'validation_status' => ThreePeaksApiProcessingStatus::class,
    ];

    protected $attributes = [
        'validation_status' => 0,
    ];

    /**
     * Mutate debit_batch_filename to file
     */
    protected function file(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->debit_batch_filename,
            set: fn (mixed $value) => ['debit_batch_filename' => $value]
        );
    }

    /**
     * Mutate debit_batch_total to total
     */
    protected function total(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->debit_batch_total,
            set: fn (mixed $value) => ['debit_batch_total' => $value]
        );
    }

    /**
     * Mutate debit_batch_num_users to users_count
     */
    protected function usersCount(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->debit_batch_num_users,
            set: fn (mixed $value) => ['debit_batch_num_users' => $value]
        );
    }

    public function debitDayDate(): BelongsTo
    {
        return $this->belongsTo(DebitDayDate::class, 'debit_day_date_id');
    }

    public function userBatch(): HasMany
    {
        return $this->hasMany(UserBatch::class, 'debit_batch_id');
    }

    public function statements(): HasMany
    {
        return $this->hasMany(DebitBatchStatement::class, 'debit_batch_id');
    }

    public function scopeProcessed(Builder $query): Builder
    {
        return $query->where('is_processed', true);
    }

    public function scopeUnprocessed(Builder $query): Builder
    {
        return $query->where('is_processed', false);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->joinRelationship('debitDayDate')
            ->where('debit_day_dates.is_active', true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query
            ->joinRelationship('debitDayDate')
            ->where('debit_day_dates.is_active', true);
    }

    public function scopeInFuture(Builder $query): Builder
    {
        return $query
            ->joinRelationship('debitDayDate')
            ->where('debit_day_dates.debit_day_date', '>', today()->toDateString());
    }

    public function scopeLocation(Builder $query, string|int|Model $location): Builder
    {
        if ($location instanceof Model) {
            $location = $location->getKey();
        }

        return $query->where('debit_batches.box_facility_id', $location);
    }

    public function scopeDebitDay(Builder $query, DebitDay $debitDay): Builder
    {
        return $query->joinRelationship('debitDayDate')
            ->when(
                ($debitDay->getKey() === 7),
                function ($query) {
                    $query->whereDate('debit_day_dates.debit_day_date', '>', today())
                        ->orWhereDate('debit_day_dates.debit_day_date', '>', 'DATE_ADD(CURDATE(), INTERVAL 5 DAY)');
                },
                function ($query) use ($debitDay) {
                    $query->where('debit_day_dates.debit_day_id', '=', $debitDay->getKey());
                }
            );
    }

    public function scopeHasMembersToDebit(Builder $query): Builder
    {
        return $query->where('debit_batches.debit_batch_num_users', '>', 0);
    }

    public function scopeUnprocessedBatches(Builder $query, Carbon $date, PaymentGateway $paymentGateway, bool $isSameDay = false)
    {
        return $query->select('debit_batches.*')
            ->joinRelationship('debitDayDate.debitDay')
            ->joinRelationship('location.tenant')
            ->where('debit_batches.is_processed', '=', false)
            ->where('debit_day_dates.is_active', '=', true)
            ->where('debit_days.is_active', '=', true)
            ->where('debit_day_dates.debit_day_date', $date->toDateString())
            ->where('box_facility.payment_gateway_id', '=', $paymentGateway->value)
            ->where('box_facility.is_active', '=', true)
            ->where('box_facility.can_debit', '=', true)
            ->where('boxes.box_status_id', '=', TenantStatus::ACTIVE)
            ->when($paymentGateway === PaymentGateway::SAGE_PAY_V3,
                function (Builder $query) use ($isSameDay) {
                    $query->where('debit_days.interval', $isSameDay === true ? 'P0D' : 'P5D');
                }
            );
    }

    public function updateBatchTotal(): void
    {
        $total = UserBatch::query()
            ->whereIsActive(true)
            ->whereDebitBatchId($this->getKey())
            ->get();

        $this->debit_batch_total = $total->sum('amount_editable');
        $this->debit_batch_num_users = $total->count();
        $this->save();
    }

    public function isProcessed(): bool
    {
        return (bool) $this->is_processed;
    }

    public function isNotProcessed(): bool
    {
        return ! $this->isProcessed();
    }

    public function startLogEntry(): void
    {
        $timestamp = now()->toDateTimeString();

        $this->log .= PHP_EOL.PHP_EOL."====================== START {$this->location->name} [$timestamp] =======================".PHP_EOL;
    }

    public function endLogEntry(): void
    {
        $timestamp = now()->toDateTimeString();

        $this->log .= PHP_EOL."====================== END {$this->location->name} [$timestamp] =======================".PHP_EOL;
    }

    public function log($text): static
    {
        $this->log .= PHP_EOL.$text.PHP_EOL;

        return $this;
    }
}

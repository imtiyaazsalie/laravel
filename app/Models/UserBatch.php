<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kirschbaum\PowerJoins\PowerJoins;

class UserBatch extends Model
{
    use BelongsToTenant, HasFactory, PowerJoins;

    public const CREATED_AT = 'dt_added';

    public const UPDATED_AT = 'dt_modified';

    public $timestamps = true;

    protected $table = 'user_to_batch';

    protected $primaryKey = 'user_to_batch_id';

    protected $casts = [
        'is_active' => 'integer',
        'is_manually_added' => 'integer',
    ];

    protected $guarded = [];

    public function tenantId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->debitBatch()->exists() ? $this->debitBatch->location->box_id : null,
        );
    }

    /**
     * Mutate user_note to note
     */
    protected function note(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->user_note,
            set: fn (mixed $value) => ['user_note' => $value]
        );
    }

    /**
     * Mutate amount_editable to amount
     */
    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->amount_editable,
            set: fn (mixed $value) => ['amount_editable' => $value]
        );
    }

    protected function amountInCents(): Attribute
    {
        return Attribute::make(
            get: fn () => (int) bcmul($this->amount_editable, 100),
        );
    }

    public function debitBatch(): BelongsTo
    {
        return $this->belongsTo(DebitBatch::class, 'debit_batch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeSearch(Builder $query, $search): Builder
    {
        return $query->where('user.name', 'LIKE', "%$search%")
            ->orWhere('user.surname', 'LIKE', "%$search%");
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(UserInvoice::class, 'invoice_id', 'invoice_id');
    }
}

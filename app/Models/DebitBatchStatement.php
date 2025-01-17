<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DebitBatchStatement extends Model
{
    use HasFactory;

    protected $table = 'debit_batch_statements';

    protected $primaryKey = 'debit_batch_statement_download_request_id';

    protected $guarded = [];

    public $timestamps = false;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'dt_requested' => 'datetime',
        'dt_downloaded' => 'datetime',
        'dt_reconciled' => 'datetime',
        'dt' => 'date',
    ];

    /**
     * Mutate dt to date
     */
    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->dt,
            set: fn (mixed $value) => ['dt' => $value]
        );
    }

    public function debitBatch(): BelongsTo
    {
        return $this->belongsTo(DebitBatch::class, 'debit_batch_id');
    }
}

<?php

namespace App\Models;

use App\Enums\DiscoveryVitalityBatchType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiscoveryVitalityBatch extends Model
{
    use HasFactory;

    protected $table = 'discovery_vitality_batches';

    protected $guarded = [];

    public $timestamps = true;

    public const CREATED_AT = 'created_on';

    public const UPDATED_AT = 'updated_on';

    public $casts = [
        'batch_type' => DiscoveryVitalityBatchType::class,
    ];

    /**
     * Mutate file_path to file
     */
    protected function file(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->file_path,
            set: fn (mixed $value) => ['file_path' => $value]
        );
    }

    /**
     * Mutate batch_type to type
     */
    protected function type(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->batch_type,
            set: fn (mixed $value) => ['batch_type' => $value]
        );
    }
}

<?php

namespace App\Models;

use App\Enums\NotificationStatus;
use App\Traits\IsOwnedByTenant;
use App\Traits\Paginatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Notifications extends Model
{
    use IsOwnedByTenant, Paginatable;

    protected $table = 'crm_notifications';

    protected $primaryKey = 'notification_id';

    protected $guarded = [];

    public $timestamps = false;

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'unsubscribable' => 'integer',
        'status' => NotificationStatus::class,
    ];

    /**
     * Mutate system_type to type
     */
    protected function type(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->system_type,
            set: fn (mixed $value) => ['system_type' => $value]
        );
    }

    /**
     * Mutate system_context to context
     */
    protected function context(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->system_context,
            set: fn (mixed $value) => ['system_context' => $value]
        );
    }

    /**
     * Mutate emailSubject to subject
     */
    protected function subject(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->emailSubject,
            set: fn (mixed $value) => ['emailSubject' => $value]
        );
    }

    /**
     * Mutate emailContent to content
     */
    protected function content(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->emailContent,
            set: fn (mixed $value) => ['emailContent' => $value]
        );
    }

    /**
     * Mutate smsContent to sms
     */
    protected function sms(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->smsContent,
            set: fn (mixed $value) => ['smsContent' => $value]
        );
    }

    /**
     * Parent relation.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id', 'notification_id');
    }

    public static function for(string|int $boxId, array $contexts): Collection
    {
        return self::query()
            ->byTenant($boxId)
            ->contexts($contexts)
            ->get();
    }

    public static function firstFor(string|int $boxId, string $contexts): Notifications
    {
        return self::query()
            ->byTenant($boxId)
            ->contexts($contexts)
            ->first();
    }

    public function scopeByTenant(Builder $query, $boxId): Builder
    {
        return $query->withoutGlobalScopes()
            ->where(function ($query) use ($boxId) {
                $query
                    ->whereNull('box_id')
                    ->orWhere('box_id', $boxId);
            })->orderBy('box_id', 'desc');
    }

    public function scopeContexts(Builder $query, array|string $contexts): Builder
    {
        if (is_string($contexts)) {
            return $query->where('system_context', $contexts);
        }

        return $query->whereIn('system_context', $contexts);
    }

    public function isEnabled(): bool
    {
        return $this->status === NotificationStatus::ENABLED;
    }

    public function unsubscription(): HasMany
    {
        return $this->hasMany(NotificationUnsubscriptions::class, 'notification_id');
    }

    public function generateHtml(?array $data = null): string
    {
        $content = $this->emailContent;

        if (! empty($data)) {
            foreach ($data as $key => $value) {
                // Replace null content with empty string
                if (is_null($value)) {
                    $content = str_replace('['.$key.']', '', $content);

                    continue;
                }

                $content = str_replace('['.$key.']', $value, $content);
            }
        }

        return $content;
    }

    public function getSubject(?array $data = null): string
    {
        $subject = $this->subject;

        if (! empty($data)) {
            foreach ($data as $key => $value) {
                // Replace null content with empty string
                if (is_null($value)) {
                    $subject = str_replace('['.$key.']', '', $subject);

                    continue;
                }

                $subject = str_replace('['.$key.']', $value, $subject);
            }
        }

        return $subject;
    }
}

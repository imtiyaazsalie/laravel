<?php

namespace App\Models;

use App\Traits\MutatesUserToFacilityId;
use App\Traits\Paginatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\JoinClause;

class PushNotification extends Model
{
    use HasFactory, MutatesUserToFacilityId, Paginatable;

    public $timestamps = false;

    protected $table = 'saved_push_notifications';

    protected $primaryKey = 'push_notification_id';

    protected $guarded = [];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_read' => 'integer',
        'received_on' => 'datetime',
    ];

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->join('user_to_facility', function (JoinClause $join) use ($userId) {
            $join->on('user_to_facility.user_to_facility_id', '=', 'saved_push_notifications.user_to_facility_id')
                ->where('user_to_facility.user_id', $userId)
                ->whereDate('user_to_facility.end_date', '>', now());
        });
    }

    public function toggleReadState(): void
    {
        $this->update(['is_read' => ! $this->is_read]);
    }

    public function userLocation(): BelongsTo
    {
        return $this->belongsTo(LocationUser::class, 'user_to_facility_id');
    }

    public static function nextBadgeCount($userLocationId): int
    {
        return self::query()
            ->where('user_to_facility_id', $userLocationId)
            ->where('is_read', 0)
            ->count() + 1;
    }
}

<?php

namespace App\Models;

use App\Enums\PackageType;
use App\Traits\BelongsToTenant;
use App\Traits\Paginatable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Kirschbaum\PowerJoins\PowerJoins;
use RuntimeException;

class UserPackage extends Model
{
    use BelongsToTenant, HasFactory, Paginatable, PowerJoins;

    public $timestamps = false;

    protected $table = 'user_to_package';

    protected $primaryKey = 'user_to_package_id';

    protected $guarded = [];

    protected $casts = [
        'effective_date' => 'date',
        'end_date' => 'date',
        'sessions_available' => 'integer',
        'sessions_expire' => 'date',
    ];

    public function tenantId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->package?->box_id,
        );
    }

    /**
     * Mutate effective_date to start_date
     */
    protected function startDate(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->effective_date,
            set: fn (mixed $value) => ['effective_date' => $value]
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function invoices()
    {
        return $this->hasMany(UserInvoice::class, 'user_to_package_id')->where('deleted', '=', false);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_id');
    }

    public function locations()
    {
        return $this->hasMany(LocationPackage::class, 'package_id', 'package_id', 'user_to_package_id', 'package_id', 'package_id');
    }

    public function programmeVisibilities(): HasMany
    {
        return $this->hasMany(ProgrammePackageVisibility::class, 'package_id', 'package_id');
    }

    public function scopeCalculatedSessionsAvailable(Builder $query, ?Carbon $date = null): Builder
    {
        $date = $date ?: now();

        $subQuery = <<<'HEREA'
            (
                SELECT
                    count(class_bookings.class_booking_id) AS 'class_bookings_count'
                FROM
                    class_bookings
                LEFT JOIN class_to_dates ON class_bookings.class_to_date_id = class_to_dates.class_to_date_id
                LEFT JOIN classes ON class_bookings.class_id = classes.class_id
             WHERE
                class_bookings.class_booking_status_id NOT IN(2, 4)
                AND class_to_dates.is_active = 1
                AND classes.is_free = 0
                AND user_id = user_to_package.user_id
                AND class_to_dates.class_date BETWEEN '%s' AND '%s'
            )
            HEREA;

        $select = <<<'HEREA'
            CASE WHEN packages.package_limit = 0 THEN
                'unlimited'
             WHEN package_limit_type_id = 3 THEN
                user_to_package.sessions_available
             WHEN package_limit_type_id = 2 THEN
                packages.package_limit - %s
             WHEN package_limit_type_id = 1 THEN
                packages.package_limit - %s
             ELSE
                0
             END AS calculated_sessions_available
            HEREA;

        $select = sprintf(
            $select,
            sprintf($subQuery, $date->startOfWeek()->toDateString(), $date->endOfWeek()->toDateString()),
            sprintf($subQuery, $date->startOfMonth()->toDateString(), $date->endOfMonth()->toDateString())
        );

        return $query->joinRelationship('package')->addSelect(
            DB::raw(str_replace(PHP_EOL, '', $select))
        );
    }

    public function scopeActive(Builder $query, ?Carbon $date = null): Builder
    {
        return $query
            ->joinRelationship('package')
            ->where('user_to_package.deleted', '=', false)
            ->where('packages.is_active', true)
            ->where('user_to_package.effective_date', '<=', $date?->toDateString() ?: today()->toDateString())
            ->where(function ($query) use ($date) {
                $query->where('user_to_package.end_date', '>', $date?->toDateString() ?: today()->toDateString())
                    ->orWhereNull('user_to_package.end_date');
            });
    }

    public function scopeIsPackageActive(Builder $query, bool $isActive): Builder
    {
        return $query->where('packages.is_active', $isActive);
    }

    public function scopeIsSessionsAvailable(Builder $query, bool $isAvailable): Builder
    {
        return $query
            ->when(
                $isAvailable,
                function ($query) {
                    $query->where('user_to_package.sessions_available', '>', 0)
                        ->orWhere(function ($query) {
                            //unlimited package
                            $query->where('packages.package_limit', 0)
                                ->whereIn('packages.package_limit_type_id', [
                                    PackageType::WEEKLY->value,
                                    PackageType::MONTHLY->value,
                                ]);
                        });
                },
                function ($query) {
                    $query->where('user_to_package.sessions_available', '<=', 0)
                        ->where(function ($query) {
                            //limited package
                            $query->where('packages.package_limit', '>', 0);
                        });
                }
            );
    }

    public function scopeIsActive(Builder $query, bool $isActive): Builder
    {
        return $query
            ->when(
                $isActive,
                function ($query) {
                    $query->where('user_to_package.effective_date', '<=', today()->toDateString())
                        ->where(function ($query) {
                            $query->where('user_to_package.end_date', '>', today()->toDateString())->orWhereNull('user_to_package.end_date');
                        });
                },
                function ($query) {
                    $query->where('user_to_package.effective_date', '>', today()->toDateString())
                        ->orWhere(function ($query) {
                            $query->where('user_to_package.end_date', '<', today()->toDateString());
                        });
                },
            );
    }

    public function scopeStartsBetween(Builder $query, $start, $end): Builder
    {
        $start = Carbon::parse($start)->toDateString();
        $end = Carbon::parse($end)->toDateString();

        return $query->whereBetween('user_to_package.effective_date', [$start, $end ?? $start]);
    }

    public function scopeEndsBetween(Builder $query, $start, $end): Builder
    {
        $start = Carbon::parse($start)->toDateString();
        $end = Carbon::parse($end)->toDateString();

        return $query->whereBetween('user_to_package.end_date', [$start, $end ?? $start]);
    }

    public function isActive(): bool
    {
        if (! $this->end_date) {
            return $this->effective_date->lte(Carbon::today());
        }

        return $this->effective_date->lte(Carbon::today()) && $this->end_date->gte(Carbon::today());
    }

    public function getSessionsAvailable(?string $date = null)
    {
        switch ($this->package->type) {
            case PackageType::LIMITED:
            case PackageType::DROP_IN:
                return $this->sessions_available;
            case PackageType::MONTHLY:
            case PackageType::WEEKLY:

                $timezone = $this->package->tenant->timezone->zone;

                $date = $date
                    ? Carbon::parse($date)->timezone($timezone)
                    : now($timezone);

                if ($this->package->isWeekly()) {
                    $start = $date->copy()->startOfWeek();
                    $end = $date->copy()->endOfWeek();
                }

                if ($this->package->isMonthly()) {
                    $start = $date->copy()->startOfMonth();
                    $end = $date->copy()->endOfMonth();
                }

                $sessionsUsedInPeriod = ClassBooking::query()
                    ->whereUserId($this->user_id)
                    ->between($start, $end)
                    ->count();

                return $this->package->package_limit - $sessionsUsedInPeriod;

            default:
                throw new RuntimeException('Package type unknown.');
        }
    }

    public function sessionsAvailableAsText(?string $date = null): string
    {
        if ($this->package->isLimited() || $this->package->type == PackageType::DROP_IN) {
            return (string) $this->getSessionsAvailable($date);
        }

        $number = $this->package->limit === 0 ? '∞' : $this->getSessionsAvailable($date);

        return str($number)->append($this->sessionsAvailableText())->toString();
    }

    public function sessionsAvailableText(): string
    {
        if ($this->package->isLimited()) {
            return '';
        }

        return $this->package->isMonthly() ? ' this month' : ' this week';
    }
}

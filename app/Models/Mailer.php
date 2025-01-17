<?php

namespace App\Models;

use App\Casts\MailerSchedule as CastsMailerSchedule;
use App\Enums\MailerSchedule;
use App\Enums\MailerType;
use App\Traits\IsOwnedByTenant;
use App\Traits\MutatesBoxFacilityId;
use App\Traits\RecordUserOnCreateAndUpdate;
use App\Traits\SoftDeletesBoolean;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class Mailer extends Model
{
    use HasFactory, IsOwnedByTenant, MutatesBoxFacilityId, RecordUserOnCreateAndUpdate, SoftDeletesBoolean;

    protected $table = 'crm_mailers';

    protected $primaryKey = 'mailer_id';

    protected $guarded = [];

    public $timestamps = true;

    public const CREATED_AT = 'created_on';

    public const UPDATED_AT = 'updated_on';

    public const UPDATED_BY_ID = null;

    protected $casts = [
        'schedule' => CastsMailerSchedule::class,
        'frequency' => MailerSchedule::class,
        'type' => MailerType::class,
        'sent_on' => 'datetime',
        'repeat_on' => 'array',
        'next_scheduled_for' => 'datetime',
    ];

    protected $attributes = [
        'deleted' => 0,
    ];

    /**
     * Mutate discr to description
     */
    protected function description(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->discr,
            set: fn (mixed $value) => ['discr' => $value]
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
     * Mutate image_header to image_header_url
     */
    protected function imageHeaderUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->image_header ? Storage::disk('public')->url($this->image_header) : null,
        );
    }

    /**
     * Mutate image_footer to image_footer_url
     */
    protected function imageFooterUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->image_footer ? Storage::disk('public')->url($this->image_footer) : null,
        );
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'box_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'box_facility_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(MailerRecipient::class, 'mailer_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MailerAttachment::class, 'mailer_id');
    }

    public function setPlaceholders(array $data = []): string
    {
        $content = $this->content;

        foreach ($data as $key => $value) {
            $content = str_replace('['.$key.']', $value, $content);
        }

        return $content;
    }

    public function getTimeFromSchedule(): string
    {
        return Carbon::today()->setTimeFromTimeString(
            $this->schedule->getTime().$this->schedule->getAmPm()
        )->format('H:i:s');
    }

    public function getNextScheduledForDate(?Carbon $from = null): ?Carbon
    {
        $from = $from ?: now();

        if (in_array($this->status, ['draft', 'complete'])) {
            return null;
        }

        if ($this->frequency === MailerSchedule::NOW) {
            return now()->ceilMinute();
        }

        if (! in_array($this->frequency, [MailerSchedule::WEEKLY, MailerSchedule::MONTHLY])) {
            return null;
        }

        if ($this->frequency === MailerSchedule::WEEKLY) {
            return $this->getNextScheduledDateForWeekly($from);
        }

        return $this->getNextScheduledDateForMonthly($from);
    }

    public function getNextScheduledDateForWeekly(Carbon $from): Carbon
    {
        $nextDay = $this->getNextDay($this->repeat_on, $from->dayOfWeekIso);

        //today
        if (now()->dayOfWeekIso === $nextDay) {
            return now()
                ->setTimeFromTimeString($this->time);
        }

        //later this week
        if (now()->dayOfWeekIso < $nextDay) {
            return now()
                ->addDays($nextDay - now()->dayOfWeekIso)
                ->setTimeFromTimeString($this->time);
        }

        //next week
        return now()
            ->endOfWeek()
            ->addDays($nextDay)
            ->setTimeFromTimeString($this->time);
    }

    public function getNextScheduledDateForMonthly(Carbon $from): Carbon
    {
        $nextDay = $this->getNextDay($this->repeat_on, $from->day);

        //today
        if (now()->day === $nextDay) {
            return now()
                ->setTimeFromTimeString($this->time);
        }

        //later this month
        if (now()->day < $nextDay) {
            return now()
                ->addDays($nextDay - now()->day)
                ->setTimeFromTimeString($this->time);
        }

        //next month
        return now()
            ->endOfMonth()
            ->addDays($nextDay)
            ->setTimeFromTimeString($this->time);
    }

    public function getNextDay(array $days, int $startAt): ?int
    {
        $this->validateDaysArray($days);

        $restartAt = match ($this->frequency) {
            MailerSchedule::WEEKLY => 8,
            MailerSchedule::MONTHLY => now()->daysInMonth + 1,
            default => throw new RuntimeException("Can't get next day for non-recurring mailer."),
        };

        if ($startAt < 1) {
            throw new RuntimeException('Tried to get next scheduled day without valid start at day.');
        }

        if ($startAt >= $restartAt) {
            $startAt = 1;
        }

        sort($days);

        foreach ($days as $day) {
            if ($day === 'last') {
                $day = today()->daysInMonth;
            }

            $day = (int) $day;

            if ($day === $startAt) {
                return $day;
            } else {
                return $this->getNextDay($days, ++$startAt);
            }
        }

        return null;
    }

    public function validateDaysArray(array $days): bool
    {
        $limit = $this->frequency === MailerSchedule::WEEKLY ? 7 : 31;

        foreach ($days as $day) {
            if ($day === 'last') {
                $day = today()->daysInMonth;
            }

            if ($day >= 1 && $day <= $limit) {
                return true;
            }
        }

        throw new RuntimeException("Invalid days array in mailer ID: {$this->getKey()}");
    }

    public function getTimezone(): Timezone
    {
        if ($this->tenant_id) {
            return Tenant::findOrFail($this->tenant_id)->timezone;
        }

        $location = Location::findOrFail($this->location_id);

        return $location->timezone ?? $location->tenant->timezone;
    }
}

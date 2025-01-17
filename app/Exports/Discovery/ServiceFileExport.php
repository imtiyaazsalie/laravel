<?php

namespace App\Exports\Discovery;

use App\Enums\HealthCareProvider;
use App\Models\AttendanceRecord;
use App\Services\AttendanceRecordService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ServiceFileExport implements FromQuery, ShouldQueue, WithHeadings, WithMapping
{
    use Exportable;

    private int $qualifiesThreshold = 30;

    private Carbon $date;

    private AttendanceRecordService $attendanceRecordService;

    public function __construct()
    {
        $this->attendanceRecordService = new AttendanceRecordService();
    }

    public function forDate(Carbon $date): ServiceFileExport
    {
        $this->date = $date;

        return $this;
    }

    public function headings(): array
    {
        return [
            'Octiv member number',
            'RSA ID/Passport number',
            'Date of birth',
            'Event date',
            'Event type',
            'Member name',
            'Member surname',
            'Facility',
            'Class',
            'Check in time',
            'Check out time',
        ];
    }

    public function map($record): array
    {
        // Only applies to non-virtual classes so that it lines up with the class time
        if (! $record->classDate?->class->isVirtual()) {
            $record->checked_in_at->setTimezone('Africa/Johannesburg');

            if ($record->checked_out_at) {
                $record->checked_out_at->setTimezone('Africa/Johannesburg');
            }
        }

        return [
            $record->user ? $record->user->getKey() : '',
            $record->user ? $record->user->id_number : $record->id_number,
            $record->user ? $record->user->dob?->toDateTimeString() : $record->date_of_birth?->toDateTimeString(),
            $record->created_on->setTimezone('Africa/Johannesburg')->toDateTimeString(),
            $this->attendanceRecordService->getDiscoveryVitalityMnemonic($record)->value,
            $record->user ? $record->user->name : $record->name,
            $record->user ? $record->user->surname : $record->surname,
            $record->location->tenant->name.' - '.$record->location->name,
            $record->location->category->name,
            $record->checked_in_at->format('H:i:s'),
            $record->checked_out_at?->format('H:i:s'),
        ];
    }

    public function query(): Builder
    {
        return AttendanceRecord::query()
            ->with(['classDate.class', 'user', 'location'])
            ->whereDate('created_on', $this->date)
            ->whereNotNull('checked_in_at')
            ->where('health_provider_id', HealthCareProvider::DISCOVERY_VITALITY_ID);
    }
}

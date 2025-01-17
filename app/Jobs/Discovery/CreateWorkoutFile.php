<?php

namespace App\Jobs\Discovery;

use App\Enums\DiscoveryVitalityBatchType;
use App\Enums\HealthCareProvider;
use App\Models\AttendanceRecord;
use App\Models\DiscoveryVitalityBatch;
use App\Services\AttendanceRecordService;
use App\Services\CrmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Markdown;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CreateWorkoutFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $qualifiesThreshold = 30;

    private int $idNumbersCount = 0;

    private int $idNumberSumTotal = 0;

    private string $filePath;

    private array $userWithInvalidData = [];

    private AttendanceRecordService $attendanceRecordService;

    private Carbon $firstDate;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $filename, public Carbon $date)
    {
        $this->filePath = config('discovery.directories.internal.workout').$filename;
        $this->attendanceRecordService = new AttendanceRecordService();
        $this->firstDate = Carbon::parse('1900-01-01');
    }

    public function map(AttendanceRecord $record): string
    {
        $idNumber = str($record->user ? $record->user->id_number : $record->id_number)->replace(' ', '')->toString();

        if ($record->user) {
            if (! $record->user->dob || $record->user->dob < $this->firstDate || $record->user->dob > today()) {
                $this->userWithInvalidData[] = $record->user;
            }

            // Id number validation
            if (strlen($idNumber) > 13 || strlen($idNumber) < 6) {
                $this->userWithInvalidData[] = $record->user;
            }

            // If an id number contains any letters
            if (strlen($idNumber) === 13 && preg_match('/[a-z]/i', $idNumber)) {
                $this->userWithInvalidData[] = $record->user;
            }
        }

        if ($idNumber) {
            $this->idNumbersCount++;
            if (! preg_match('/[a-z]/i', $idNumber)) {
                $this->idNumberSumTotal = $this->idNumberSumTotal + (int) $idNumber;
            }
        }

        return str('}}')
            ->append($record->user_id ?? '')
            ->append('}')
            ->append($idNumber)
            ->append('}')
            ->append($record->user ? $record->user->dob?->format('Y-m-d') : $record->date_of_birth?->format('Y-m-d'))
            ->append('}')
            ->append($record->created_on->setTimezone('Africa/Johannesburg')->format('Y-m-d'))
            ->append('}')
            ->append($this->attendanceRecordService->getDiscoveryVitalityMnemonic($record)->value)
            ->append('}')
            ->append($record->user ? $record->user->full_name : $record->full_name)
            ->append(PHP_EOL)
            ->toString();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Get the latest batch number and increase it by one
        $latestBatch = DiscoveryVitalityBatch::query()->whereBatchType(DiscoveryVitalityBatchType::WORKOUT)->latest()->first();

        // Determine the batch number
        $batchNumber = $latestBatch ? (int) str_pad(trim($latestBatch->batch_number), 6, '0', STR_PAD_LEFT) + 1 : 000001;

        $data = config('discovery.partner_entity_number').'}'.$this->date->format('Y-m-d h:i:s').'}0004}001}'.$batchNumber.'}'.PHP_EOL.PHP_EOL;

        AttendanceRecord::query()
            ->with(['classDate.class', 'user'])
            ->whereDate('created_on', $this->date)
            ->whereNotNull('checked_in_at')
            ->where('health_provider_id', HealthCareProvider::DISCOVERY_VITALITY_ID)
            ->chunk(1000, function ($records) use (&$data) {
                foreach ($records as $record) {
                    $data .= $this->map($record);
                }
            });

        // Footer
        $data .= PHP_EOL.PHP_EOL;
        $data .= $this->idNumbersCount.'}'.$this->idNumberSumTotal.'}';

        DB::transaction(function () use ($batchNumber, $data) {
            DiscoveryVitalityBatch::create([
                'batch_number' => $batchNumber,
                'file_path' => $this->filePath,
                'batch_type' => 'workout',
                'no_of_records' => $this->idNumbersCount,
                'check_total' => $this->idNumberSumTotal,
            ]);

            Storage::disk('private')->put($this->filePath, $data, ['visibility' => 'private']);
        }, 3);

        // Send email for faulty idNumber users
        $crmService = new CrmService();

        collect($this->userWithInvalidData)->unique()->each(function ($user) use ($crmService) {

            $content = Markdown::parse(
                view('emails.incorrect-vitality-information', ['user' => $user])->render()
            )->__toString();

            $crmService->createScheduledEmail(
                content: $content,
                subject: 'Incorrect Discovery Vitality Information on Octiv',
                to: $user->email,
                replyTo: 'noreply@octivfitness.com'
            );
        });
    }
}

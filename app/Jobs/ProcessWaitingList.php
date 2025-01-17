<?php

namespace App\Jobs;

use App\Exceptions\Class\AttendanceLimitReachedException;
use App\Models\ClassDate;
use App\Services\ClassBookingsWaitingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessWaitingList implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        public string|int $classDateId,
        public ?int $processLimit = null
    ) {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(ClassBookingsWaitingService $waitingList)
    {
        $waitingList->process(ClassDate::findOrFail($this->classDateId), $this->processLimit);
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        if ($exception instanceof AttendanceLimitReachedException) {
            Log::warning("Attendance limit reached for class date ID {$this->classDateId}. We should not be trying to process the waiting list when there are no spots available.");
        }
    }
}

<?php

namespace App\Jobs;

use App\Models\Programme;
use App\Services\ProgrammeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class CopyGlobalWods implements ShouldQueue, ShouldQueueAfterCommit
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private Programme $programme,
        private ?Programme $only = null
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(ProgrammeService $programmeService): void
    {
        Programme::query()
            ->active()
            ->where('parent_id', $this->programme->getKey())
            ->when($this->only, fn ($q) => $q->whereKey($this->only->getKey()))
            ->get()
            ->each(function (Programme $programme) use ($programmeService) {
                DB::transaction(
                    attempts: 2,
                    callback: fn () => $programmeService->copyWods($this->programme, $programme)
                );
            });
    }
}

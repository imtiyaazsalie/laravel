<?php

namespace App\Jobs\Discovery;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class UploadWorkoutFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $filename)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Storage::disk('discovery')->put(
            config('discovery.directories.external.workout').$this->filename,
            Storage::disk('private')->get(config('discovery.directories.internal.workout').$this->filename),
            ['visibility' => 'private']
        );
    }
}

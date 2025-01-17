<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class RemoveMailAttachments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private array $attachments
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (is_array($this->attachments)) {
            if (! Arr::get($this->attachments, 'disk')) {
                foreach ($this->attachments as $attachment) {
                    if (Arr::get($attachment, 'delete_after_send') === true) {
                        Storage::disk(Arr::get($attachment, 'disk'))->delete(Arr::get($attachment, 'path'));
                    }
                }
            } else {
                if (Arr::get($this->attachments, 'delete_after_send') === true) {
                    Storage::disk(Arr::get($this->attachments, 'disk'))->delete(Arr::get($this->attachments, 'path'));
                }
            }
        }
    }
}

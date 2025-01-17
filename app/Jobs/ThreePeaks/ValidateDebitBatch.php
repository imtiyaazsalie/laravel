<?php

namespace App\Jobs\ThreePeaks;

use App\Enums\ThreePeaksApiProcessingStatus;
use App\Exceptions\ThreePeaksException;
use App\Models\DebitBatch;
use App\Services\CrmService;
use App\Services\PaymentGateways\ThreePeaksService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Markdown;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ValidateDebitBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $releaseTimeInSeconds = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(private readonly DebitBatch $debitBatch)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(ThreePeaksService $threePeaksService): void
    {
        $crm = resolve(CrmService::class);

        if ($this->debitBatch->isNotProcessed()) {
            throw new RuntimeException("Trying to validate debit batch ID: {$this->debitBatch->getKey()} but is has not yet been processed.");
        }

        try {
            $submissionInfo = $threePeaksService->getSubmissionInformation($this->debitBatch->location_id, $this->debitBatch->subid);
        } catch (ThreePeaksException $e) {
            Log::critical($e->getMessage());

            return;
        }

        $validationStatus = ThreePeaksApiProcessingStatus::from(Arr::get($submissionInfo, 'validationStatusId', 0));

        /**
         * If the file is not ready release the job to try again.
         */
        if (in_array($validationStatus, [ThreePeaksApiProcessingStatus::RECEIVED, ThreePeaksApiProcessingStatus::VALIDATION_IN_PROGRESS])) {
            $this->release($this->releaseTimeInSeconds);
        }

        if ($validationStatus == ThreePeaksApiProcessingStatus::VALIDATION_PASSED) {

            $content = Markdown::parse(
                view('emails.three-peaks.debit-batch-validation-passed', [
                    'locationName' => $this->debitBatch->location->name,
                    'debitDayDate' => $this->debitBatch->debitDayDate->date->toDateString(),
                ])
            )->__toString();

            foreach ($this->debitBatch->location->tenant->headCoaches()->get() as $coach) {
                $crm->createScheduledEmail(
                    content: $content,
                    subject: "Batch validation passed: {$this->debitBatch->location->name}",
                    to: $coach->email,
                );
            }

            $this->debitBatch->validation_status = ThreePeaksApiProcessingStatus::VALIDATION_PASSED->value;
            $this->debitBatch->save();
        }

        if ($validationStatus == ThreePeaksApiProcessingStatus::VALIDATION_FAILED) {
            $crm->createScheduledEmail(
                content: Markdown::parse(
                    view('emails.three-peaks.debit-batch-validation-failed', [
                        'debitBatchId' => $this->debitBatch->getKey(),
                        'locationName' => $this->debitBatch->location->name,
                        'debitDayDate' => $this->debitBatch->debitDayDate->date->toDateString(),
                        'subid' => $this->debitBatch->subid,

                    ])
                )->__toString(),
                subject: "Batch validation failed: {$this->debitBatch->location->name}",
                to: config('octiv.emails.tech'),
            );

            $this->debitBatch->validation_status = ThreePeaksApiProcessingStatus::VALIDATION_FAILED->value;
            $this->debitBatch->save();

            Log::emergency('ThreePeaks batch validation failed.', [
                'debit_batch_id' => $this->debitBatch->getKey(),
                'submission_id' => $this->debitBatch->subid,
            ]);
        }
    }
}

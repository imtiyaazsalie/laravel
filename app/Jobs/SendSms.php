<?php

namespace App\Jobs;

use App\Enums\NotificationLogStatus;
use App\Enums\NotificationLogType;
use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendSms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $content,
        public string $to,
        public string|int|null $tenantId = null,
        public string|int|null $locationId = null,
        public string|int|null $mailerId = null,
        public string|int|null $userId = null,

    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $data = [
            'messages' => [
                [
                    'content' => $this->content,
                    'destination' => $this->to,
                ],
            ],
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic '.base64_encode(config('smsportal.client_id').':'.config('smsportal.client_secret')),
        ])->timeout(5)->post(config('smsportal.base_url').'bulkmessages', $data);

        try {
            $response->throw();
        } catch (RequestException $ex) {
            $response = $ex->response;

            Log::error('SMS failed to send.', $response->json());

            $errors = Arr::get($response->json(), 'errors', []);

            $firstError = Arr::get($errors, 0, []);

            $errorMessage = Arr::get($firstError, 'errorMessage', 'SMS API error.');

            NotificationLog::create([
                'type' => NotificationLogType::SMS,
                'status' => NotificationLogStatus::FAILED,
                'recipient' => $this->to ?? 'null',

                'message' => $errorMessage,

                'tenant_id' => $this->tenantId,
                'location_id' => $this->locationId,
                'user_id' => $this->userId,
                'mailer_id' => $this->mailerId,

                'content' => $this->content,
            ]);
        }
    }
}

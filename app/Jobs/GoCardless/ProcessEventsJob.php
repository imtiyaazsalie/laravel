<?php

namespace App\Jobs\GoCardless;

use App\Enums\GoCardlessWebhookStatus;
use App\Exceptions\GoCardless\EventActionNotAccountedFor;
use App\Models\GoCardlessWebhookEvent;
use App\Models\LocationPaymentGatewaySettings;
use App\Services\PaymentGateways\GoCardlessService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessEventsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public $events)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $goCardlessService = new GoCardlessService();

        foreach ($this->events as $event) {
            $eventId = $event->id;
            $organisationId = $event->links->organisation;

            $existingWebhookEvent = GoCardlessWebhookEvent::where('id', $eventId)->where('organisation_id', $organisationId)->first();

            if ($existingWebhookEvent) {
                continue;
            }

            if ($event->resource_type === 'organisations') {
                $action = $event->action;

                match ($action) {
                    'disconnected' => LocationPaymentGatewaySettings::query()
                        ->where('organisation_id', $event->links->organisation)
                        ->where('disconnected_from_event', false)
                        ->update([
                            'token' => null,
                            'disconnected_from_event' => 1,
                        ]),
                    default => throw new EventActionNotAccountedFor("Action not accounted for: $action"),
                };
            } else {
                $goCardlessWebhookEvent = GoCardlessWebhookEvent::create([
                    'id' => $eventId,
                    'organisation_id' => $organisationId,
                    'payload' => $event,
                    'status' => GoCardlessWebhookStatus::PENDING,
                    'published_at' => now(),
                ]);

                $goCardlessService->processEvent($goCardlessWebhookEvent);
            }
        }
    }
}

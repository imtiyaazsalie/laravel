<?php

namespace App\Console\Commands\CRM;

use App\Enums\MailerSchedule;
use App\Enums\MailerType;
use App\Models\Mailer;
use App\Services\CrmService;
use Illuminate\Console\Command;

class DispatchMailers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:dispatch-crm-mailers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch CRM mailers.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        /** @var CrmService $crm */
        $crm = resolve(CrmService::class);

        Mailer::query()
            ->with(['recipients'])
            ->where('status', 'upcoming')
            ->whereIn('frequency', [
                MailerSchedule::NOW->value,
                MailerSchedule::ONCEOFF->value,
            ])
            ->where('next_scheduled_for', '=', now()->floorMinute())
            ->chunk(100, function ($mailers) use ($crm) {
                foreach ($mailers as $mailer) {
                    if ($mailer->recipients->isEmpty()) {
                        $mailer->update([
                            'status' => 'complete',
                            'sent_on' => now(),
                        ]);

                        continue;
                    }

                    if ($mailer->type === MailerType::EMAIL) {
                        $crm->createScheduledEmailForMailer($mailer, $mailer->location ?: $mailer->tenant);

                        $mailer->update([
                            'status' => 'complete',
                            'sent_on' => now(),
                        ]);

                        continue;
                    }

                    if ($mailer->type === MailerType::SMS) {
                        $crm->createScheduledSMSForMailer($mailer, $mailer->location ?: $mailer->tenant);

                        $mailer->update([
                            'status' => 'complete',
                            'sent_on' => now(),
                        ]);

                        continue;
                    }

                    if ($mailer->type === MailerType::PUSH) {
                        $crm->createScheduledPushForMailer($mailer, $mailer->location ?: $mailer->tenant);

                        $mailer->update([
                            'status' => 'complete',
                            'sent_on' => now(),
                        ]);
                    }
                }
            });

        return Command::SUCCESS;
    }
}

<?php

namespace Database\Seeders;

use App\Models\Mailer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class MigrateCrmMailersSchedule extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Mailer::query()
            ->where('status', '!=', 'draft')
            ->chunk(1000, function ($mailers) {
                $mailers->each(function ($mailer) {
                    if (! $mailer->schedule) {
                        Log::error("Mailer ID {$mailer->getKey()} has no schedule.");

                        return;
                    }

                    if ($mailer->schedule->getType() === 'event') {
                        return;
                    }

                    switch ($mailer->schedule->getType()) {
                        case 'now':
                            $mailer->fill([
                                'repeat_on' => null,
                                'frequency' => 'onceoff',
                                'time' => null,
                            ]);
                            break;

                        case 'once_off':
                            $mailer->fill([
                                'repeat_on' => null,
                                'frequency' => 'onceoff',
                                'time' => $mailer->getTimeFromSchedule(),
                            ]);
                            break;

                        case 'weekly':
                            $mailer->fill([
                                'repeat_on' => $mailer->schedule->getDays(),
                                'frequency' => 'weekly',
                                'time' => $mailer->getTimeFromSchedule(),
                            ]);
                            break;

                        case 'monthly':
                            $mailer->fill([
                                'repeat_on' => $mailer->schedule->getDays(),
                                'frequency' => 'monthly',
                                'time' => $mailer->getTimeFromSchedule(),
                            ]);
                            break;

                        default:
                            // code...
                            break;
                    }

                    $mailer->fill([
                        'next_scheduled_for' => $mailer->getNextScheduledForDate(from: now()),
                    ])->save();
                });
            });
    }
}

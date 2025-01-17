<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        /**
         * Laravel internals
         */
        $schedule->command('auth:clear-resets')
            ->everyFifteenMinutes()
            ->onOneServer();

        $schedule->command('passport:purge')
            ->hourly()
            ->onOneServer();

        $schedule->command('cache:prune-stale-tags')
            ->hourly()
            ->onOneServer();

        $schedule->command('telescope:prune --hours=72')
            ->daily()
            ->onOneServer();

        $schedule->command('horizon:snapshot')
            ->everyFiveMinutes()
            ->onOneServer();

        /**
         * Users
         */
        $schedule->command('app:extend-memberships')
            ->dailyAt('00:00')
            ->onOneServer()
            ->runInBackground()
            ->evenInMaintenanceMode();

        $schedule->command('app:run-scheduled-actions')
            ->dailyAt('04:00')
            ->onOneServer()
            ->evenInMaintenanceMode();

        $schedule->command('app:run-scheduled-actions', ['--last-debit-date'])
            ->dailyAt('04:15')
            ->onOneServer()
            ->evenInMaintenanceMode();

        $schedule->command('app:dispatch-expiring-contract-notifications')
            ->dailyAt('05:15')
            ->onOneServer()
            ->evenInMaintenanceMode();

        $schedule->command('app:release-on-hold-users')
            ->dailyAt('20:00')
            ->onOneServer()
            ->evenInMaintenanceMode();

        $schedule->command('app:deactivate-users-with-expired-contracts')
            ->dailyAt('21:30')
            ->onOneServer()
            ->evenInMaintenanceMode();

        /**
         * CRM
         */
        $schedule->command('app:send-birthday-notifications')
            ->dailyAt('04:45')
            ->onOneServer()
            ->evenInMaintenanceMode();

        $schedule->command('app:dispatch-crm-mailers')
            ->everyMinute()
            ->onOneServer()
            ->evenInMaintenanceMode();

        $schedule->command('app:send-sign-up-notifications')
            ->dailyAt('05:00')
            ->onOneServer()
            ->evenInMaintenanceMode();

        /**
         * Classes
         */
        $schedule->command('app:auto-cancel-classes-and-bookings')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->onOneServer()
            ->evenInMaintenanceMode();

        $schedule->command('app:extend-recurring-classes')
            ->weeklyOn(7, '06:00')
            ->onOneServer()
            ->evenInMaintenanceMode();

        $schedule->command('app:extend-recurring-bookings')
            ->weeklyOn(7, '06:30')
            ->onOneServer()
            ->evenInMaintenanceMode();

        /**
         * Finance
         */
        $schedule->command('app:generate-cash-invoices')
            ->dailyAt('03:30')
            ->onOneServer()
            ->evenInMaintenanceMode();

        $schedule->command('app:generate-debit-batches')
            ->monthlyOn(1, '01:00')
            ->onOneServer()
            ->evenInMaintenanceMode();

        /**
         * Discovery
         */
        $schedule->command('app:discovery-generate-workout-file')
            ->dailyAt('22:15')
            ->onOneServer()
            ->evenInMaintenanceMode();

        $schedule->command('app:discovery-generate-servicing-file')
            ->dailyAt('22:30')
            ->onOneServer()
            ->evenInMaintenanceMode();

        $schedule->command('app:discovery-generate-monthly-recon-file')
            ->monthlyOn(1, '22:45')
            ->onOneServer()
            ->evenInMaintenanceMode();

        /**
         * Netcash
         */
        $schedule->command('app:netcash:submit-debit-batch')
            ->dailyAt('00:15')
            ->onOneServer()
            ->evenInMaintenanceMode();

        $schedule->command('app:netcash:submit-debit-batch', ['--same-day'])
            ->dailyAt('00:30')
            ->onOneServer()
            ->evenInMaintenanceMode();

        $schedule->command('app:netcash:request-batch-statements')
            ->dailyAt('06:45')
            ->onOneServer()
            ->evenInMaintenanceMode();

        $schedule->command('app:netcash:download-batch-statements')
            ->dailyAt('07:00')
            ->onOneServer()
            ->evenInMaintenanceMode();

        $schedule->command('app:netcash:reconcile-batch-statements')
            ->dailyAt('07:15')
            ->onOneServer()
            ->evenInMaintenanceMode();

        /**
         * Three Peaks
         */
        $schedule->command('app:three-peaks:submit-debit-batch')
            ->dailyAt('01:00')
            ->onOneServer()
            ->evenInMaintenanceMode();

        $schedule->command('app:three-peaks:validate-debit-batch')
            ->dailyAt('03:00')
            ->onOneServer()
            ->evenInMaintenanceMode();

        $schedule->command('app:three-peaks:reconcile-debit-batch')
            ->dailyAt('03:15')
            ->onOneServer()
            ->evenInMaintenanceMode();

        /**
         * Stripe
         */
        $schedule->command('app:stripe:submit-debit-batches')
            ->dailyAt('02:00')
            ->onOneServer()
            ->evenInMaintenanceMode();

        /**
         * GoCardless
         */
        $schedule->command('app:go-cardless:submit-debit-batch')
            ->dailyAt('01:30')
            ->onOneServer()
            ->evenInMaintenanceMode();

        $schedule->command('app:configure-s3')
            ->monthly()
            ->onOneServer()
            ->evenInMaintenanceMode();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}

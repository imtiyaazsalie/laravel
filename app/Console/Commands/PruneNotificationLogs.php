<?php

namespace App\Console\Commands;

use App\Models\NotificationLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneNotificationLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:prune-notification-logs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        NotificationLog::query()
            ->select('id')
            ->where('created_at', '<', DB::raw('DATE_SUB(NOW(), INTERVAL 1 MONTH)'))
            ->lazyById(100000, 'id')
            ->each->delete();

    }
}

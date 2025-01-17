<?php

namespace App\Console\Commands;

use App\Services\EnumService;
use Illuminate\Console\Command;

class RefreshEnumCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:refresh-enum-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh the ENUM cache.';

    /**
     * Execute the console command.
     */
    public function handle(EnumService $enums)
    {
        $enums->refresh();
    }
}

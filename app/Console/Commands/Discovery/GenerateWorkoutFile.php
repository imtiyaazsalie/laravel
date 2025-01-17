<?php

namespace App\Console\Commands\Discovery;

use App\Jobs\Discovery\CreateWorkoutFile;
use App\Jobs\Discovery\UploadWorkoutFile;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;

class GenerateWorkoutFile extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:discovery-generate-workout-file {--date=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate and upload discover workout file.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : today();

        $filename = 'OCTIVworkouts_'.$date->format('YmdHis').'.txt';

        Bus::chain([
            new CreateWorkoutFile($filename, $date),
            new UploadWorkoutFile($filename),
        ])->onQueue('exports')
            ->dispatch();

        return Command::SUCCESS;
    }
}

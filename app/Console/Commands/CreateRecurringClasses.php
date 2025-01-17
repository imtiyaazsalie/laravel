<?php

namespace App\Console\Commands;

use App\Enums\ClassType;
use App\Enums\TenantStatus;
use App\Jobs\CreateRecurringClasses as JobsCreateRecurringClasses;
use App\Models\Classes;
use Illuminate\Console\Command;

class CreateRecurringClasses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:extend-recurring-classes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create recurring classes.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Fetching active recurring classes...');

        Classes::query()
            ->where('class_type_id', ClassType::RECURRING)
            ->where('is_active', true)
            ->whereRelation('tenant', 'box_status_id', '=', TenantStatus::ACTIVE)
            ->chunk(500, function ($classes) {
                $this->info('Processing new chunk...');

                foreach ($classes as $class) {
                    $this->info("Processing class ID: {$class->getKey()}");

                    JobsCreateRecurringClasses::dispatch(class: $class);
                }
            });

        return Command::SUCCESS;
    }
}

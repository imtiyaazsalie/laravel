<?php

namespace Database\Seeders;

use App\Models\TenantUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;

class RemoveDuplicateUserTenants extends Seeder
{
    public function __construct()
    {

    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $output = new ConsoleOutput();

        // creates a new progress bar (50 units)

        $duplicationTenants = TenantUser::query()->withoutGlobalScopes()
            ->select('user_to_box_id', 'user_id', 'box_id')
            ->groupBy('user_id', 'box_id')
            ->having(DB::raw('count(*)'), '>', 1)
            ->get();

        $progressBar = new ProgressBar($output, $duplicationTenants->count());
        $progressBar->setFormat('very_verbose');
        $progressBar->start();

        foreach ($duplicationTenants as $duplicationTenant) {
            $duplicationTenantsUsers = TenantUser::query()->withoutGlobalScopes()
                ->select('user_to_box_id', 'user_id', 'box_id', 'updated_on')
                ->where('user_id', $duplicationTenant->user_id)
                ->where('box_id', $duplicationTenant->box_id)
                ->orderByDesc('updated_on')
                ->get();

            $deleted = $duplicationTenantsUsers->nth(1, 1);

            if ($deleted->isNotEmpty()) {
                TenantUser::query()
                    ->withoutGlobalScopes()
                    ->whereIn('user_to_box_id', $deleted->pluck('user_to_box_id'))
                    ->delete();
            }
            $progressBar->advance();

        }

        $progressBar->finish();

    }
}

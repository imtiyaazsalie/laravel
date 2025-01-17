<?php

namespace App\Console\Commands\LeadMemberMigration;

use App\Enums\PackageType;
use App\Models\DropInPackage;
use App\Models\Package;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateDropInPackagesToPackagesCommand extends Command
{
    protected $signature = 'lead-member:migrate-drop-in-packages-to-packages';

    protected $description = 'Command description';

    public function handle(): void
    {
        $this->migrateDropInPackagesToPackages();
    }

    private function migrateDropInPackagesToPackages(): void
    {
        DB::table('package_limit_types')->updateOrInsert([
            'package_limit_type_descr' => 'Drop In Package',
            'is_active' => 1,
        ]);

        $dropInPackages = DropInPackage::query()->get();

        $this->info('Migrate drop in packages table to packages table');
        $progress = $this->output->createProgressBar(count($dropInPackages));
        $progress->start();

        foreach ($dropInPackages as $dropInPackage) {
            Package::query()->firstOrCreate([
                'package_name' => $dropInPackage->name,
                'package_limit' => 1,
                'package_limit_type_id' => PackageType::DROP_IN,
                'package_price' => $dropInPackage->price,
                'package_descr' => $dropInPackage->description,
                'dt_added' => $dropInPackage->created_at,
                'dt_modified' => $dropInPackage->updated_at,
                'is_active' => $dropInPackage->is_active,
                'is_displayed' => 1,
                'is_display_on_buy_packages' => 1,
                'priority' => $dropInPackage->priority,
            ], [
                'drop_in_package_id' => $dropInPackage->id,
                'box_id' => $dropInPackage->box_id,
            ]);
            $progress->advance();
        }

        $progress->finish();
    }
}

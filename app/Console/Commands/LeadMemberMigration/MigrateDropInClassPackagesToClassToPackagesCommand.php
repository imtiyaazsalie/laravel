<?php

namespace App\Console\Commands\LeadMemberMigration;

use App\Models\ClassPackage;
use App\Models\DropInPackageClasses;
use Illuminate\Console\Command;

class MigrateDropInClassPackagesToClassToPackagesCommand extends Command
{
    protected $signature = 'lead-member:migrate-drop-in-class-packages-to-class-to-packages';

    protected $description = 'Command description';

    public function handle(): void
    {
        $this->migrateDropInClassPackagesToClassToPackages();
    }

    private function migrateDropInClassPackagesToClassToPackages(): void
    {
        $dropInClassPackages = DropInPackageClasses::query()
            ->join('packages', 'packages.drop_in_package_id', '=', 'drop_in_packages_to_classes.drop_in_package_id')
            ->join('classes', 'drop_in_packages_to_classes.class_id', '=', 'classes.class_id')
            ->select([
                'drop_in_packages_to_classes.class_id',
                'packages.package_id',
                'packages.drop_in_package_id',
                'classes.dt_added',
                'classes.dt_modified',
                'classes.is_active',
            ])
            ->get();

        $this->info('Migrate drop in class packages table to class packages table');
        $progress = $this->output->createProgressBar(count($dropInClassPackages));
        $progress->start();

        foreach ($dropInClassPackages as $dropInClassPackage) {
            ClassPackage::query()->updateOrInsert([
                'class_id' => $dropInClassPackage->class_id,
                'package_id' => $dropInClassPackage->package_id,
                'drop_in_package_id' => $dropInClassPackage->drop_in_package_id,
                'dt_added' => $dropInClassPackage->dt_added,
                'dt_modified' => $dropInClassPackage->dt_modified,
                'is_active' => $dropInClassPackage->is_active,
            ]);
            $progress->advance();
        }
        $progress->finish();
    }
}

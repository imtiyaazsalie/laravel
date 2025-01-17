<?php

namespace App\Console\Commands;

use App\Models\Classes;
use App\Models\ClassPackage;
use App\Models\Package;
use Illuminate\Console\Command;

class AddDiscoveryClassToPackages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:add-discovery-class-to-packages';

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
        $classes = Classes::all();

        foreach ($classes as $class) {
            if (! $class->tenant_id) {
                continue;
            }

            $packages = Package::where('box_id', '=', $class->tenant_id)->get();
            $packages->pluck('package_id');
            $classPackages = ClassPackage::where('class_id', $class->getKey())->get();

            $inserts = $packages->pluck('package_id')->diff($classPackages->pluck('package_id'));
            if ($inserts->isEmpty()) {
                continue;
            }
            $data = [];
            foreach ($inserts as $insert) {
                $data[] = [
                    'class_id' => $class->getKey(),
                    'dt_added' => now(),
                    'dt_modified' => now(),
                    'package_id' => $insert,
                    'is_active' => 1,
                ];
            }
            ClassPackage::insert($data);
        }

    }
}

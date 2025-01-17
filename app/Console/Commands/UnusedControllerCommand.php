<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

class UnusedControllerCommand extends Command
{
    protected $signature = 'unused:controller';

    protected $description = 'Find all unused controller classes';

    public function handle()
    {
        $this->info('Finding unused controllers...');

        $controllerFiles = glob(app_path('Http/Controllers/API/*.php'));

        $controllerClasses = collect($controllerFiles)->map(function ($file) {
            return str_replace([app_path('Http/Controllers/API/'), '.php'], '', $file);
        });

        $routes = Route::getRoutes();

        foreach ($routes as $route) {
            $action = $route->getAction();

            if (isset($action['controller'])) {
                $controller = $action['controller'];

                $controllerClasses = $controllerClasses->reject(function ($class) use ($controller) {

                    $class = str($class)->after('/')->toString();

                    return str_contains($controller, $class);
                });
            }
        }

        $unusedControllers = $controllerClasses->toArray();

        if (empty($unusedControllers)) {
            $this->info('No unused controllers found.');
        } else {
            $this->info('Unused controllers:');
            foreach ($unusedControllers as $unusedController) {
                $this->line($unusedController);
            }
        }
    }
}

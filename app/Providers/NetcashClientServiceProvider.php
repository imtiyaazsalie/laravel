<?php

namespace App\Providers;

use App\Services\PaymentGateways\NetcashClient;
use Illuminate\Support\ServiceProvider;

class NetcashClientServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(NetcashClient::class, function () {
            return new NetcashClient(config('netcash.api.url'));
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}

<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        Passport::tokensCan([
            'discovery-vitality' => 'Access Discovery Vitality partnership features.',
        ]);

        ResetPassword::createUrlUsing(function ($user, string $token) {
            return config('octiv.web_app_url').'/reset-password/?token='.$token.'&email='.urlencode($user->email);
        });

        Gate::define('viewPulse', function (?User $user) {
            return false;
        });
    }
}

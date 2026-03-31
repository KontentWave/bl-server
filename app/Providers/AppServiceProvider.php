<?php

namespace App\Providers;

use App\Contracts\EscortAdVerifier;
use App\Contracts\EscortPortalClient;
use App\Services\FixtureEscortPortalClient;
use App\Services\HttpEscortPortalClient;
use App\Services\JobEscortAdVerifier;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(EscortAdVerifier::class, JobEscortAdVerifier::class);
        $this->app->bind(EscortPortalClient::class, function ($app) {
            return match (config('services.escort_portal.driver', 'http')) {
                'fixture' => $app->make(FixtureEscortPortalClient::class),
                default => $app->make(HttpEscortPortalClient::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}

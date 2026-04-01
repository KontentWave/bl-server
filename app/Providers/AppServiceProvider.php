<?php

namespace App\Providers;

use App\Contracts\EscortPortalClient;
use App\Contracts\SmsSender;
use App\Services\FixtureEscortPortalClient;
use App\Services\HttpEscortPortalClient;
use App\Services\LogSmsSender;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(EscortPortalClient::class, function ($app) {
            return match (config('services.escort_portal.driver', 'http')) {
                'fixture' => $app->make(FixtureEscortPortalClient::class),
                default => $app->make(HttpEscortPortalClient::class),
            };
        });
        $this->app->bind(SmsSender::class, LogSmsSender::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}

<?php

namespace App\Providers;

use App\Contracts\EscortPortalClient;
use App\Contracts\SmsSender;
use App\Services\FixtureEscortPortalClient;
use App\Services\HttpEscortPortalClient;
use App\Services\LogSmsSender;
use App\Services\OtpAbuseProtection;
use App\Services\SmstoolsSmsSender;
use App\Services\VonageSmsSender;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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

        $this->app->bind(SmsSender::class, function ($app) {
            return match (config('services.sms.driver', 'log')) {
                'smstools' => $app->make(SmstoolsSmsSender::class),
                'vonage' => $app->make(VonageSmsSender::class),
                default => $app->make(LogSmsSender::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            app(OtpAbuseProtection::class)->assertSharedStore();

            return Limit::perMinute(max(1, (int) config('security.api_per_minute', 60)))
                ->by(hash('sha256', (string) $request->ip()));
        });

        RateLimiter::for('auth-initiate', fn (Request $request) => [
            Limit::perMinute(max(1, (int) config('security.initiate_per_minute', 3)))
                ->by('minute:'.hash('sha256', (string) $request->ip())),
            Limit::perHour(max(1, (int) config('security.initiate_per_hour', 10)))
                ->by('hour:'.hash('sha256', (string) $request->ip())),
        ]);

        RateLimiter::for('auth-verify', fn (Request $request) => Limit::perMinute(max(1, (int) config('security.verify_per_minute', 10)))
            ->by(hash('sha256', (string) $request->ip())));
    }
}

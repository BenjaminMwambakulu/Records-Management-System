<?php

namespace App\Providers;

use App\Auth\JwtVerifier;
use App\Auth\LogtoGuard;
use App\Listeners\SyncSuperadminToLogto;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Events\RoleUpdated;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureRateLimiting();

        Event::listen(RoleUpdated::class, SyncSuperadminToLogto::class);

        Auth::extend('logto', function (Application $app, string $name, array $config) {
            return new LogtoGuard(
                Auth::createUserProvider($config['provider'] ?? null),
                $app->make(JwtVerifier::class),
                $app->make(Container::class),
            );
        });
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('api-read', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('api-write', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('api-sensitive', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('guest', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });
    }
}

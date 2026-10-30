<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use App\Database\NeonPostgresConnector;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind('db.connector.pgsql', fn () => new NeonPostgresConnector);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('journal-login', fn (Request $request) => Limit::perMinute(5)
            ->by(hash('sha256', strtolower((string) $request->input('email')).'|'.$request->ip())));
        RateLimiter::for('journal-setup', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('journal-join', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('household-manage', fn (Request $request) => Limit::perMinute(20)->by((string) ($request->user()?->id ?? $request->ip())));
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}

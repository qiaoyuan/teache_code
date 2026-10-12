<?php

namespace App\Providers;

use App\Session\PortalDatabaseSessionHandler;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Session::extend('database', fn ($app) => new PortalDatabaseSessionHandler(
            $app['db']->connection(config('session.connection')),
            config('session.table'),
            config('session.lifetime'),
            $app,
        ));

        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(5)->by($r->ip().'|'.$r->input('phone', $r->input('username', ''))));
        RateLimiter::for('register', fn (Request $r) => Limit::perHour(6)->by($r->ip()));
        RateLimiter::for('password-reset', fn (Request $r) => Limit::perMinute(10)->by(Auth::guard('admin')->id() ?? $r->ip()));
    }
}

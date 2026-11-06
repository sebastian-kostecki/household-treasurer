<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->encryptCookies(except: ['appearance']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('backup:run')
            ->dailyAt('03:00')
            ->timezone('Europe/Warsaw')
            ->withoutOverlapping(120)
            ->onOneServer()
            ->environments(['production']);

        $schedule->command('backup:clean')
            ->dailyAt('03:30')
            ->timezone('Europe/Warsaw')
            ->withoutOverlapping(60)
            ->onOneServer()
            ->environments(['production']);

        $schedule->command('backup:monitor')
            ->dailyAt('04:00')
            ->timezone('Europe/Warsaw')
            ->withoutOverlapping(30)
            ->onOneServer()
            ->environments(['production']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

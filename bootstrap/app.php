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
        // Traefik reaches the app from the Docker proxy network and passes Cloudflare's
        // X-Forwarded-For on, so the client IP is the visitor's. Only the client IP and the
        // scheme are trusted: X-Forwarded-Host and -Port reach the app from the visitor
        // unchanged, and trusting them would let anyone change the host in generated links.
        $middleware->trustProxies(
            at: ['172.16.0.0/12'],
            headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO,
        );

        $middleware->encryptCookies(except: ['appearance']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Backups run in maintenance mode too: a failed deployment stays down on purpose, and
        // without this the nightly backup and its monitor would stop without a single mail.
        // Each run overwrites its own output file and mails it on a non-zero exit, so a crash
        // that skips the package's own failure mail (an Error, not an Exception) still arrives.
        $backupCommands = [
            'backup:run' => ['03:00', 120],
            'backup:clean' => ['03:30', 60],
            'backup:monitor' => ['04:00', 30],
        ];

        foreach ($backupCommands as $command => [$time, $lockMinutes]) {
            $schedule->command($command)
                ->dailyAt($time)
                ->timezone('Europe/Warsaw')
                ->withoutOverlapping($lockMinutes)
                ->onOneServer()
                ->evenInMaintenanceMode()
                ->environments(['production'])
                ->sendOutputTo(storage_path('logs/schedule-'.str_replace(':', '-', $command).'.log'))
                ->emailOutputOnFailure(config('backup.notifications.mail.to'));
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

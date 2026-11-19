<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use League\OAuth2\Server\Exception\OAuthServerException;

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

        // OAuth housekeeping of the MCP endpoint (ADR-0110): expired tokens hourly, unused registered clients daily.
        $schedule->command('passport:purge')
            ->hourly()
            ->onOneServer()
            ->environments(['production'])
            ->sendOutputTo(storage_path('logs/schedule-passport-purge.log'))
            ->emailOutputOnFailure(config('backup.notifications.mail.to'));
        $schedule->command('app:prune-oauth-clients')
            ->daily()
            ->onOneServer()
            ->environments(['production'])
            ->sendOutputTo(storage_path('logs/schedule-prune-oauth-clients.log'))
            ->emailOutputOnFailure(config('backup.notifications.mail.to'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Passport reports every invalid or expired bearer token: an expected 401, logged in the `mcp` channel instead.
        $exceptions->dontReport(OAuthServerException::class);

        // `/mcp` answers JSON even without a JSON Accept header: a guest gets 401 with `WWW-Authenticate`, never the login page.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->is('mcp') || $request->expectsJson(),
        );
    })->create();

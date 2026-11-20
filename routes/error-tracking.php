<?php

use App\Http\Controllers\ErrorAlertRelayController;
use App\Http\Controllers\ErrorTunnelController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Browser error tunnel (ADR-0112)
|--------------------------------------------------------------------------
|
| Registered from `bootstrap/app.php` without the `web` group: no session, CSRF, `auth` or `verified`, because the
| login, 2FA and passkey screens are visited by guests. The limiter `error-tunnel` is in `AppServiceProvider`.
*/

Route::post('/error-tracking/tunnel', ErrorTunnelController::class)
    ->middleware('throttle:error-tunnel')
    ->name('error-tracking.tunnel');

// Bugsink's webhook carries no secret header, so the secret is part of the URL. The limiter `error-alerts` is in `AppServiceProvider`.
Route::post('/error-tracking/alerts/{secret}', ErrorAlertRelayController::class)
    ->middleware('throttle:error-alerts')
    ->name('error-tracking.alerts');

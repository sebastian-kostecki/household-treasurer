<?php

use App\Http\Middleware\LogMcpCall;
use App\Mcp\Servers\LocalTreasurerServer;
use App\Mcp\Servers\TreasurerServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP servers (ADR-0108)
|--------------------------------------------------------------------------
|
| `php artisan mcp:start treasurer` serves the local stdio server. `/mcp` serves
| `TreasurerServer` (never the local one) behind Passport OAuth: discovery and
| client registration are limited to 10 requests a minute per IP.
|
*/

Mcp::local('treasurer', LocalTreasurerServer::class);

Route::middleware('throttle:10,1')->group(fn () => Mcp::oauthRoutes());

Mcp::web('/mcp', TreasurerServer::class)
    ->middleware(['auth:api', 'throttle:mcp', LogMcpCall::class]);

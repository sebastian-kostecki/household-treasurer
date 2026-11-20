<?php

use App\Http\Middleware\AddRouteContext;
use App\Http\Middleware\LogMcpCall;
use App\Mcp\Servers\LocalTreasurerServer;
use App\Mcp\Servers\TreasurerServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Passport\Http\Middleware\CheckToken;

/*
|--------------------------------------------------------------------------
| MCP servers (ADR-0110)
|--------------------------------------------------------------------------
|
| `php artisan mcp:start treasurer` serves the local stdio server. `/mcp` serves
| `TreasurerServer` (never the local one) behind Passport OAuth: discovery and
| client registration are limited to 10 requests a minute per IP. A token
| without the `mcp:use` scope is refused with 403. `LogMcpCall` runs first,
| so refused calls (401, 403, 429) are logged too.
|
*/

Mcp::local('treasurer', LocalTreasurerServer::class);

Route::middleware('throttle:10,1')->group(fn () => Mcp::oauthRoutes());

Mcp::web('/mcp', TreasurerServer::class)
    ->middleware([LogMcpCall::class, 'auth:api', CheckToken::using('mcp:use'), 'throttle:mcp', AddRouteContext::class]);

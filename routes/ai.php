<?php

use App\Mcp\Servers\LocalTreasurerServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP servers (ADR-0108)
|--------------------------------------------------------------------------
|
| Only the stdio server is registered here: `php artisan mcp:start treasurer`.
| The web endpoint registers `TreasurerServer`, never the local one.
|
*/

Mcp::local('treasurer', LocalTreasurerServer::class);

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs one line per remote MCP call to the `mcp` channel: user id, JSON-RPC method, tool name, HTTP status and duration.
 * Never the arguments, amounts or descriptions (.ai/rules/mcp.md).
 */
class LogMcpCall
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);

        $response = $next($request);

        $method = $request->input('method');
        $toolName = $method === 'tools/call' ? $request->input('params.name') : null;

        Log::channel('mcp')->info('mcp call', [
            'user_id' => $request->user()?->getAuthIdentifier(),
            'method' => is_string($method) ? $method : null,
            'tool' => is_string($toolName) ? $toolName : null,
            'status' => $response->getStatusCode(),
            'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
        ]);

        return $response;
    }
}

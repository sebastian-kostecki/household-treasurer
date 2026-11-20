<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds the route and the user id to the log context. It runs after the session starts (end of the
 * `web` group) or after the Passport guard authenticates (the MCP group), when both are known.
 * The user id is the number only, never the e-mail, and no input goes into the context.
 */
class AddRouteContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $userId = Auth::id();

        Context::add([
            'route' => $route?->getName() ?? $route?->uri(),
            'user_id' => is_numeric($userId) ? (int) $userId : null,
        ]);

        return $next($request);
    }
}

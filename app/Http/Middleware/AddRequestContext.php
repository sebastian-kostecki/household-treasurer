<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global middleware: gives every log entry and queued job of the request a fresh request id and
 * the release (git SHA). It runs before routing and the session, so the route and the user come
 * from `AddRouteContext`. The id is returned to the client so a report can be matched to a request;
 * an incoming `X-Request-Id` is ignored.
 */
class AddRequestContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::uuid();

        Context::add([
            'request_id' => $requestId,
            'release' => config('app.release'),
        ]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}

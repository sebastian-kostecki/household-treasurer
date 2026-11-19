<?php

namespace App\Http\Responses\Concerns;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * An Inertia sign-in that ends at `/oauth/authorize` must leave the XHR chain: the authorization endpoint may answer
 * with a 302 to an external redirect_uri (claude.ai, a loopback address), which an XHR cannot follow across origins.
 */
trait LeavesInertiaForOAuthAuthorization
{
    /**
     * A full-page navigation to the intended URL, or null when the sign-in keeps Fortify's own response.
     */
    protected function oauthAuthorizationLocation(Request $request): ?Response
    {
        if (! $request->header('X-Inertia') || $request->wantsJson()) {
            return null;
        }

        $intended = $request->session()->get('url.intended');

        if (! is_string($intended)) {
            return null;
        }

        $path = parse_url($intended, PHP_URL_PATH);

        if (! is_string($path) || ! str_starts_with($path, '/oauth/authorize')) {
            return null;
        }

        $request->session()->forget('url.intended');

        return Inertia::location($intended);
    }
}

<?php

namespace App\Http\Controllers;

use App\Support\ErrorTracking\ErrorTrackerIngest;
use App\Support\ErrorTracking\SentryEnvelope;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Same-origin tunnel for browser error envelopes (ADR-0112). It sits outside the `web` group (no session, CSRF or
 * `auth`) so guests on the login, 2FA and passkey screens are covered, and it relays only `event` envelopes of
 * our own project to Bugsink over the private network. The body is forwarded exactly as received.
 */
class ErrorTunnelController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $dsn = config('sentry.dsn');
        $ingest = ErrorTrackerIngest::fromDsn($dsn);

        if ($ingest === null) {
            abort(404);
        }

        $body = $request->getContent();

        if (strlen($body) > (int) config('error-tracking.tunnel.max_body_bytes')) {
            return response('', 413);
        }

        $envelope = SentryEnvelope::parse($body);

        if ($envelope === null) {
            return response('', 422);
        }

        if (! is_string($envelope->dsn) || ! hash_equals($dsn, $envelope->dsn)) {
            return response('', 403);
        }

        if ($envelope->itemTypes === [] || array_diff($envelope->itemTypes, ['event']) !== []) {
            return response('', 422);
        }

        try {
            $upstream = $ingest->forward($body);
        } catch (ConnectionException $e) {
            // Without this the failure would leave no trace: the tracker that should receive it is the one that is down.
            report($e);

            return response('', 502);
        }

        return response($upstream->body(), $upstream->status(), [
            'Content-Type' => $upstream->header('Content-Type') ?: 'application/json',
        ]);
    }
}

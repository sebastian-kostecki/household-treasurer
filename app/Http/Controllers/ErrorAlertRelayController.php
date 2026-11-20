<?php

namespace App\Http\Controllers;

use App\Support\ErrorTracking\NtfyAlert;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Relays Bugsink's custom webhook (no secret header, so the secret is in the URL) to ntfy. Outside the `web` group
 * (ADR-0112). Only the alert reason, the exception class and the issue link are forwarded; Bugsink's `title` and
 * `calculated_value` can hold amounts and never leave. An ntfy failure propagates, so it is reported and Bugsink
 * marks the webhook as failed.
 */
class ErrorAlertRelayController extends Controller
{
    public function __invoke(Request $request, string $secret, NtfyAlert $ntfy): Response
    {
        $expected = (string) config('error-tracking.alert_secret');
        $ntfyUrl = (string) config('error-tracking.alert_ntfy_url');

        if ($expected === '' || $ntfyUrl === '' || ! hash_equals($expected, $secret)) {
            abort(404);
        }

        $reason = $this->token($request->input('alert_reason'), 'ALERT');
        $exceptionClass = $this->token($request->input('calculated_type'), 'unknown');

        $ntfy->send($ntfyUrl, $reason, $exceptionClass, $this->issueUrl($request->input('url')));

        return response()->noContent();
    }

    /**
     * Keeps a short plain token: a value of an unexpected shape is replaced, never forwarded.
     */
    private function token(mixed $value, string $fallback): string
    {
        if (is_string($value) && preg_match('/^[A-Za-z0-9_\\\\.:-]{1,128}$/D', $value) === 1) {
            return $value;
        }

        return $fallback;
    }

    /**
     * Only a URL under the configured Bugsink base becomes the Click link.
     */
    private function issueUrl(mixed $url): ?string
    {
        // A prefix without a trailing slash would also match another host that starts with the same name.
        $prefix = rtrim((string) config('error-tracking.alert_issue_url_prefix'), '/');
        $prefix = $prefix === '' ? '' : $prefix.'/';

        if ($prefix === '' || ! is_string($url) || ! str_starts_with($url, $prefix) || preg_match('/^[\x21-\x7E]+$/D', $url) !== 1) {
            return null;
        }

        return $url;
    }
}

<?php

/**
 * Same-origin tunnel for browser error envelopes (ADR-0112): the browser sends to the app, the app forwards to
 * Bugsink over the private network. The DSN is read at request time from `config('sentry.dsn')` (the server SDK's).
 */
return [

    'tunnel' => [
        // An error envelope is a few KB; anything larger is not ours.
        'max_body_bytes' => 256 * 1024,

        // Bugsink answers in milliseconds on the private network; a hung tracker must not hold a worker.
        'connect_timeout_seconds' => 1,
        'timeout_seconds' => 2,

        // Requests per minute per IP, for the `error-tunnel` limiter.
        'requests_per_minute' => 30,
    ],

    // Bugsink's custom webhook has no secret header, so the secret sits in the relay URL (ERROR_ALERT_SECRET).
    // An empty secret turns the relay off (404).
    'alert_secret' => env('ERROR_ALERT_SECRET', ''),

    // The ntfy topic URL. The topic name is the secret, so it is never logged or put in a test fixture.
    'alert_ntfy_url' => env('ERROR_ALERT_NTFY_URL', ''),

    // Public base URL of Bugsink's issue pages (its BASE_URL, e.g. https://errors.example/). Only a payload URL
    // under this prefix becomes the notification's Click link; without it no link is sent.
    'alert_issue_url_prefix' => env('ERROR_ALERT_ISSUE_URL_PREFIX', ''),

    // Requests per minute per IP, for the `error-alerts` limiter.
    'alert_requests_per_minute' => 10,

];

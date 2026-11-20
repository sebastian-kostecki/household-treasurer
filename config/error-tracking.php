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

];

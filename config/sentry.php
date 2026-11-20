<?php

use App\Support\ErrorTracking\SentryEventScrubber;

/**
 * Sentry Laravel SDK (4.x) pointed at the self-hosted Bugsink (ADR-0112). The SDK is inert while
 * `SENTRY_LARAVEL_DSN` is empty, so local, CI and test runs send nothing. Every privacy-relevant option is
 * set explicitly because the SDK defaults are permissive. This file holds no Closure, so `config:cache` works.
 *
 * @see https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/
 */
return [

    // Internal Bugsink address on the `errors-treasurer` network: http://<key>@bugsink:8000/<project>.
    'dsn' => env('SENTRY_LARAVEL_DSN'),

    // The image sets APP_RELEASE to the git revision (same source as `config('app.release')`).
    'release' => env('APP_RELEASE'),

    'environment' => env('APP_ENV', 'production'),

    'sample_rate' => 1.0,

    // No performance data, profiles, logs or metrics: only errors.
    'traces_sample_rate' => null,
    'profiles_sample_rate' => null,
    'enable_logs' => false,
    'enable_metrics' => false,

    // Privacy: no default PII, no request bodies, and no `data_collection` array (a partial array would flip
    // every omitted key to permissive). The scrubber drops what is left of the request and the frame variables.
    'send_default_pii' => false,
    'max_request_body_size' => 'never',
    'data_collection' => null,
    'before_send' => [SentryEventScrubber::class, 'beforeSend'],

    // A slow or unreachable tracker must not hold a request or a job: connect 1 s, whole request 2 s.
    'http_connect_timeout' => 1,
    'http_timeout' => 2,

    'ignore_transactions' => [
        '/up',
    ],

    'breadcrumbs' => [
        // A log entry and its context become a breadcrumb, and the scrubber does not clean them.
        'logs' => false,
        'cache' => false,
        'livewire' => false,
        'sql_queries' => true,
        // Bound values are amounts and descriptions.
        'sql_bindings' => false,
        'queue_info' => true,
        'command_info' => true,
        'http_client_requests' => false,
        'notifications' => false,
    ],

    'tracing' => [
        'queue_job_transactions' => false,
        'queue_jobs' => false,
        'sql_queries' => false,
        'sql_bindings' => false,
        'views' => false,
        'livewire' => false,
        'http_client_requests' => false,
        'cache' => false,
        'redis_commands' => false,
        'notifications' => false,
        'missing_routes' => false,
        'default_integrations' => false,
    ],

];

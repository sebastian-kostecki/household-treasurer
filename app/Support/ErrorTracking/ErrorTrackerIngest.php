<?php

namespace App\Support\ErrorTracking;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The internal envelope endpoint of the error tracker project, derived from its DSN
 * (`scheme://key@host:port/project` becomes `scheme://host:port/api/project/envelope/`).
 */
final readonly class ErrorTrackerIngest
{
    private function __construct(
        private string $url,
        private string $publicKey,
    ) {}

    /**
     * Null when the DSN is empty or not of the expected shape.
     */
    public static function fromDsn(mixed $dsn): ?self
    {
        $parts = is_string($dsn) ? parse_url($dsn) : false;
        $project = is_array($parts) && isset($parts['path']) ? trim($parts['path'], '/') : '';

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'], $parts['user']) || ! ctype_digit($project)) {
            return null;
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return new self($parts['scheme'].'://'.$parts['host'].$port.'/api/'.$project.'/envelope/', $parts['user']);
    }

    /**
     * Sends the envelope bytes as received. Throws when the tracker cannot be reached; its status is not an error.
     *
     * @throws ConnectionException
     */
    public function forward(string $envelope): Response
    {
        return Http::withBody($envelope, 'application/x-sentry-envelope')
            ->withHeaders(['X-Sentry-Auth' => 'Sentry sentry_version=7, sentry_key='.$this->publicKey])
            ->connectTimeout((int) config('error-tracking.tunnel.connect_timeout_seconds'))
            ->timeout((int) config('error-tracking.tunnel.timeout_seconds'))
            ->post($this->url);
    }
}

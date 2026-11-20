<?php

namespace App\Support\ErrorTracking;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Posts the one-line alert to the ntfy topic. ntfy.sh is public, so the message is built from fixed text, the
 * alert reason, the exception class and the issue link only: never Bugsink's title or exception value.
 */
final readonly class NtfyAlert
{
    public const TITLE = 'Domowy Skarbnik: nowy błąd';

    /**
     * @param  string  $reason  Bugsink's alert_reason (NEW, REGRESSED, UNMUTED, TEST)
     * @param  string  $exceptionClass  Bugsink's calculated_type
     * @param  string|null  $issueUrl  Already restricted to the Bugsink host
     *
     * @throws RequestException when ntfy answers with a non-2xx status
     * @throws NtfyUnreachable when ntfy cannot be reached
     */
    public function send(string $ntfyUrl, string $reason, string $exceptionClass, ?string $issueUrl): void
    {
        $headers = [
            // RFC 2047, which ntfy decodes; a raw UTF-8 header is not safe in every HTTP client.
            'Title' => '=?UTF-8?B?'.base64_encode(self::TITLE).'?=',
            'Tags' => 'warning',
        ];

        if ($issueUrl !== null) {
            $headers['Click'] = $issueUrl;
        }

        try {
            Http::withBody($reason.': '.$exceptionClass, 'text/plain')
                ->withHeaders($headers)
                ->connectTimeout((int) config('error-tracking.tunnel.connect_timeout_seconds'))
                ->timeout((int) config('error-tracking.tunnel.timeout_seconds') * 2)
                ->post($ntfyUrl)
                ->throw();
        } catch (ConnectionException) {
            // The cURL message names the full URL, and the topic in it is the secret. Not chained as previous.
            throw new NtfyUnreachable;
        }
    }
}

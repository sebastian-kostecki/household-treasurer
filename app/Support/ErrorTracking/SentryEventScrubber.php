<?php

namespace App\Support\ErrorTracking;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Context;
use PDOException;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\Stacktrace;

/**
 * `before_send` hook of the error tracker (ADR-0112). It is a static callable, never a Closure, because a
 * Closure in `config/sentry.php` breaks `config:cache`.
 *
 * The SDK defaults are permissive, so this is the second line of defence next to the explicit options and
 * `zend.exception_ignore_args`: what the owner typed (request body, query string, cookies, headers) and the
 * values of frame arguments never leave the app. What stays is the stack trace, the exception, the method and
 * the URL path, and the ids from the Laravel Context as tags.
 */
final class SentryEventScrubber
{
    /**
     * Context keys that become searchable tags in the tracker. Ids and names only, never input.
     *
     * @var list<string>
     */
    private const TAGGED_CONTEXT_KEYS = ['route', 'request_id', 'user_id', 'release'];

    /**
     * Exception classes whose message embeds bound values.
     *
     * @var list<class-string>
     */
    private const DATABASE_EXCEPTIONS = [QueryException::class, PDOException::class];

    public static function beforeSend(Event $event, ?EventHint $hint = null): Event
    {
        self::scrubRequest($event);
        self::scrubFrames($event);
        self::redactDatabaseMessages($event);
        self::tagFromContext($event);

        return $event;
    }

    private static function scrubRequest(Event $event): void
    {
        $request = $event->getRequest();

        if ($request === []) {
            return;
        }

        $scrubbed = [];

        if (isset($request['method']) && is_string($request['method'])) {
            $scrubbed['method'] = $request['method'];
        }

        if (isset($request['url']) && is_string($request['url'])) {
            $url = explode('?', $request['url'], 2)[0];
            // The relay route carries its secret in the path.
            $scrubbed['url'] = preg_replace('#(/error-tracking/alerts/)[^/]+#', '$1{secret}', $url) ?? $url;
        }

        $event->setRequest($scrubbed);
    }

    private static function scrubFrames(Event $event): void
    {
        self::stripVars($event->getStacktrace());

        foreach ($event->getExceptions() as $exception) {
            self::stripVars($exception->getStacktrace());
        }
    }

    /**
     * A database exception message carries the SQL with its bound values (amounts, descriptions) and the
     * driver's `DETAIL: Key (...)=(...)` line. Only the type and the SQLSTATE stay.
     */
    private static function redactDatabaseMessages(Event $event): void
    {
        foreach ($event->getExceptions() as $exception) {
            if (! in_array($exception->getType(), self::DATABASE_EXCEPTIONS, true)) {
                continue;
            }

            $sqlState = preg_match('/SQLSTATE\[([0-9A-Za-z]{1,5})\]/', $exception->getValue(), $match) === 1
                ? $match[1]
                : 'unknown';

            $exception->setValue("SQLSTATE[{$sqlState}]: database error, details redacted");
        }
    }

    private static function stripVars(?Stacktrace $stacktrace): void
    {
        foreach ($stacktrace?->getFrames() ?? [] as $frame) {
            $frame->setVars([]);
        }
    }

    private static function tagFromContext(Event $event): void
    {
        foreach (self::TAGGED_CONTEXT_KEYS as $key) {
            $value = Context::get($key);

            if (is_scalar($value) && $value !== '') {
                $event->setTag($key, (string) $value);
            }
        }
    }
}

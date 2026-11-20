<?php

namespace App\Support\ErrorTracking;

use Illuminate\Support\Facades\Context;
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

    public static function beforeSend(Event $event, ?EventHint $hint = null): Event
    {
        self::scrubRequest($event);
        self::scrubFrames($event);
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
            $scrubbed['url'] = explode('?', $request['url'], 2)[0];
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

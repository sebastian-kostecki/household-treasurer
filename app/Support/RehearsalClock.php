<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Local-only rehearsal clock: a stored moment that replaces "now" for the running app.
 */
class RehearsalClock
{
    public const FILE = 'rehearsal-clock';

    /**
     * Fix "now" to the stored moment, only in the local environment and only when the file exists.
     */
    public static function apply(): void
    {
        if (! app()->isLocal()) {
            return;
        }

        $moment = self::current();

        if ($moment !== null) {
            Date::setTestNow($moment);
        }
    }

    /**
     * The stored moment (UTC), or null when the clock is off.
     */
    public static function current(): ?CarbonImmutable
    {
        $disk = Storage::disk('local');

        if (! $disk->exists(self::FILE)) {
            return null;
        }

        $content = trim((string) $disk->get(self::FILE));

        try {
            return CarbonImmutable::parse($content, 'UTC')->utc();
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Unreadable rehearsal clock file "'.self::FILE.'", content: "'.$content.'".',
                previous: $exception,
            );
        }
    }

    public static function set(CarbonImmutable $moment): void
    {
        Storage::disk('local')->put(self::FILE, $moment->utc()->toIso8601String());
    }

    public static function clear(): void
    {
        Storage::disk('local')->delete(self::FILE);
    }
}

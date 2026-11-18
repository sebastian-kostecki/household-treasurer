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

    public const COMMAND = 'app:rehearsal-clock';

    /**
     * Fix "now" to the stored moment, only in the local environment and only when the file exists.
     * The clock command itself runs on the real clock, so it can always repair or remove the file.
     */
    public static function apply(): void
    {
        if (! app()->isLocal() || app()->runningConsoleCommand(self::COMMAND)) {
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
                'Unreadable rehearsal clock file "'.$disk->path(self::FILE).'", content: "'.$content.'". '
                .'Delete the file or run `php artisan '.self::COMMAND.' --off`.',
                previous: $exception,
            );
        }
    }

    public static function set(CarbonImmutable $moment): void
    {
        $disk = Storage::disk('local');

        if (! $disk->put(self::FILE, $moment->utc()->toIso8601String())) {
            throw new RuntimeException('Could not write the rehearsal clock file "'.$disk->path(self::FILE).'".');
        }
    }

    public static function clear(): void
    {
        $disk = Storage::disk('local');

        if ($disk->exists(self::FILE) && ! $disk->delete(self::FILE)) {
            throw new RuntimeException('Could not delete the rehearsal clock file "'.$disk->path(self::FILE).'".');
        }
    }
}

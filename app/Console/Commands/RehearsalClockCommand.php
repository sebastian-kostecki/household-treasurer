<?php

namespace App\Console\Commands;

use App\Support\RehearsalClock;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:rehearsal-clock {moment? : Data i godzina w UTC, np. "2027-01-01 00:30"} {--off : Wyłącz zegar próby}')]
#[Description('Ustawia, pokazuje albo wyłącza lokalny zegar próby (tylko środowisko local)')]
class RehearsalClockCommand extends Command
{
    /**
     * Accepted moment formats, read in UTC.
     */
    private const FORMATS = ['Y-m-d H:i', 'Y-m-d H:i:s'];

    public function handle(): int
    {
        if (! app()->isLocal()) {
            $this->components->error('Zegar próby działa tylko w środowisku local.');

            return self::FAILURE;
        }

        if ($this->option('off')) {
            RehearsalClock::clear();
            $this->components->info('Zegar próby wyłączony.');

            return self::SUCCESS;
        }

        $input = $this->argument('moment');

        if ($input === null) {
            $this->showCurrent();

            return self::SUCCESS;
        }

        $moment = $this->parseMoment($input);

        if ($moment === null) {
            $this->components->error("Nieczytelny moment: „{$input}”. Podaj datę i godzinę w UTC, np. „2027-01-01 00:30”.");

            return self::FAILURE;
        }

        $lifetimeMinutes = (int) config('session.lifetime');

        if ($moment->lessThan(CarbonImmutable::now()->subMinutes($lifetimeMinutes))) {
            $this->components->error(
                "Moment {$input} UTC leży ponad {$lifetimeMinutes} min w przeszłości: przeglądarka odrzuci ciasteczka sesji jako wygasłe i logowanie nie przejdzie.",
            );

            return self::FAILURE;
        }

        RehearsalClock::set($moment);
        $this->components->info($this->describe(RehearsalClock::current() ?? $moment));

        return self::SUCCESS;
    }

    /**
     * The moment in one of the exact formats, or null; a date past the end of the month is rejected.
     */
    private function parseMoment(string $input): ?CarbonImmutable
    {
        foreach (self::FORMATS as $format) {
            $moment = DateTimeImmutable::createFromFormat('!'.$format, $input, new DateTimeZone('UTC'));

            if ($moment !== false && $moment->format($format) === $input) {
                return CarbonImmutable::instance($moment);
            }
        }

        return null;
    }

    private function showCurrent(): void
    {
        $moment = RehearsalClock::current();

        if ($moment === null) {
            $this->line('zegar próby wyłączony');

            return;
        }

        $this->line($this->describe($moment));
    }

    private function describe(CarbonImmutable $moment): string
    {
        return 'UTC: '.$moment->format('Y-m-d H:i:s').', Europe/Warsaw: '
            .$moment->setTimezone('Europe/Warsaw')->format('Y-m-d H:i:s');
    }
}

<?php

namespace App\Console\Commands;

use App\Support\RehearsalClock;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('app:rehearsal-clock {moment? : Data i godzina w UTC, np. "2027-01-01 00:30"} {--off : Wyłącz zegar próby}')]
#[Description('Set, show or switch off the local rehearsal clock (local environment only)')]
class RehearsalClockCommand extends Command
{
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

        try {
            $moment = CarbonImmutable::parse($input, 'UTC')->utc();
        } catch (Throwable) {
            $this->components->error("Nieczytelny moment: {$input}");

            return self::FAILURE;
        }

        RehearsalClock::set($moment);
        $this->components->info($this->describe($moment));

        return self::SUCCESS;
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

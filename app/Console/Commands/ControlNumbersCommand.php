<?php

namespace App\Console\Commands;

use App\Support\Console\ReadsAndWritesJsonFiles;
use App\Support\ControlNumberAdditions;
use App\Support\ControlNumbers;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use JsonException;

#[Signature('app:control-numbers {--output= : Write the control numbers to this JSON file} {--compare= : Compare with a JSON file written earlier; exit code 1 on any difference} {--allow-additions : With --compare, accept only the rows the legacy budget import adds (ADR-0085)}')]
#[Description('Print control numbers (row counts, sums per budget and per budget line, balances) and compare them with an earlier file')]
class ControlNumbersCommand extends Command
{
    use ReadsAndWritesJsonFiles;

    public function handle(ControlNumbers $controlNumbers, ControlNumberAdditions $additions): int
    {
        $current = $controlNumbers->collect();

        $output = $this->option('output');

        if (is_string($output) && $output !== '') {
            $this->writeJson($this->resolvePath($output), $current);
            $this->components->info("Control numbers written to {$output}.");
        }

        $compare = $this->option('compare');

        if ($this->option('allow-additions') && (! is_string($compare) || $compare === '')) {
            $this->components->error('--allow-additions needs --compare=<file>.');

            return self::FAILURE;
        }

        if (! is_string($compare) || $compare === '') {
            $this->line(json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        try {
            $before = $this->readJson($this->resolvePath($compare));
        } catch (JsonException $exception) {
            $this->components->error("Cannot read {$compare}: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $allowAdditions = (bool) $this->option('allow-additions');
        $differences = $allowAdditions
            ? $additions->violations($before, $current)
            : $controlNumbers->differences($before, $current);

        if ($differences === []) {
            if ($allowAdditions) {
                $this->components->info('Only additions: '.collect($additions->summary($before, $current))
                    ->map(fn (int $count, string $kind): string => "{$kind} +{$count}")
                    ->implode(', ').'.');
            } else {
                $this->components->info('Control numbers match: no differences.');
            }

            return self::SUCCESS;
        }

        $this->components->error(count($differences).($allowAdditions ? ' control number(s) differ beyond additions.' : ' control number(s) differ.'));

        foreach ($differences as $difference) {
            $this->line($difference);
        }

        return self::FAILURE;
    }
}

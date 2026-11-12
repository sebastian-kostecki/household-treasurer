<?php

namespace App\Console\Commands;

use App\Support\Console\ReadsAndWritesJsonFiles;
use App\Support\Console\ResolvesConsoleUser;
use App\Support\CsvImport\ImportCategorySuggestionAccuracy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use JsonException;

#[Signature('import:suggestion-accuracy {user : The user ID or email address} {--year= : Evaluation year; default the latest year of imported rows} {--output= : Write the result to this JSON file} {--compare= : Compare with a JSON file written earlier; exit code 1 on any drop}')]
#[Description('Replay the Import history chronologically and print how many rows get a suggestion and how many are right; read-only')]
class ImportSuggestionAccuracyCommand extends Command
{
    use ReadsAndWritesJsonFiles;
    use ResolvesConsoleUser;

    private const array PERIOD_LABELS = ['year' => 'whole year', 'aug_nov' => 'Aug-Nov'];

    public function handle(ImportCategorySuggestionAccuracy $accuracy): int
    {
        try {
            $user = $this->resolveUser((string) $this->argument('user'));
        } catch (ModelNotFoundException) {
            $this->components->error("User not found: {$this->argument('user')}");

            return self::FAILURE;
        }

        $optionError = $this->optionError();

        if ($optionError !== null) {
            $this->components->error($optionError);

            return self::FAILURE;
        }

        $year = $this->filledOption('year');
        $year = $year !== null ? (int) $year : $accuracy->latestYear($user);

        if ($year === null) {
            $this->components->error('The user has no imported rows.');

            return self::FAILURE;
        }

        $result = $accuracy->measure($user, $year);

        if ($result['rows'] === []) {
            $this->components->error("The user has no imported rows in {$year}.");

            return self::FAILURE;
        }

        $this->printTable($result);

        $output = $this->filledOption('output');

        if ($output !== null) {
            $this->writeJson($this->resolvePath($output), $result);
            $this->components->info("Result written to {$output}.");
        }

        $compare = $this->filledOption('compare');

        return $compare !== null ? $this->compare($accuracy, $compare, $result) : self::SUCCESS;
    }

    /**
     * A non-numeric year would read as year 0, and an output file that is also the
     * compare file would be compared with itself and always pass.
     */
    private function optionError(): ?string
    {
        $year = $this->filledOption('year');

        if ($year !== null && ! ctype_digit($year)) {
            return "--year must be a year, got \"{$year}\".";
        }

        $output = $this->filledOption('output');
        $compare = $this->filledOption('compare');

        if ($output !== null && $compare !== null && $this->isSameFile($this->resolvePath($output), $this->resolvePath($compare))) {
            return '--output and --compare name the same file: the run would compare with itself.';
        }

        return null;
    }

    private function filledOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function isSameFile(string $first, string $second): bool
    {
        if ($first === $second) {
            return true;
        }

        $firstReal = realpath($first);

        return $firstReal !== false && $firstReal === realpath($second);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function compare(ImportCategorySuggestionAccuracy $accuracy, string $compare, array $result): int
    {
        try {
            $before = $this->readJson($this->resolvePath($compare));
        } catch (JsonException $exception) {
            $this->components->error("Cannot read {$compare}: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $differences = $accuracy->differences($before, $result);

        if ($differences['error'] !== null) {
            $this->components->error($differences['error']);

            return self::FAILURE;
        }

        foreach ($differences['rows'] as $line) {
            $this->line($line);
        }

        if ($differences['drops'] === []) {
            $this->components->info($differences['rows'] === [] ? 'No drop and no differing row.' : 'No drop; '.count($differences['rows']).' differing flag(s) listed above.');

            return self::SUCCESS;
        }

        $this->components->error(count($differences['drops']).' drop(s) against the earlier file.');

        foreach ($differences['drops'] as $drop) {
            $this->line($drop);
        }

        return self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function printTable(array $result): void
    {
        $tableRows = [];

        foreach (self::PERIOD_LABELS as $period => $label) {
            foreach (['model', 'suggestion'] as $level) {
                $stats = $result['periods'][$period][$level];
                $tableRows[] = [
                    $label,
                    $level,
                    $stats['rows'],
                    $this->percent($stats['suggested'], $stats['rows']),
                    $this->percent($stats['correct'], $stats['suggested']),
                    isset($stats['top3']) ? $this->percent($stats['top3'], $stats['rows']) : '-',
                ];
            }
        }

        $this->line("User {$result['user_id']}, year {$result['year']}");
        $this->table(['Period', 'Level', 'Rows', 'With suggestion', 'Correct of suggested', 'Top 3'], $tableRows);
    }

    private function percent(int $part, int $whole): string
    {
        return $whole === 0 ? '-' : number_format($part / $whole * 100, 1).'% ('.$part.'/'.$whole.')';
    }
}

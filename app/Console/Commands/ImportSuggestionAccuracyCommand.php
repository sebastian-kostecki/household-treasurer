<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\CsvImport\ImportCategorySuggestionAccuracy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use JsonException;

#[Signature('import:suggestion-accuracy {user : The user ID or email address} {--year= : Evaluation year; default the latest year of imported rows} {--output= : Write the result to this JSON file} {--compare= : Compare with a JSON file written earlier; exit code 1 on any drop}')]
#[Description('Replay the Import history chronologically and print how many rows get a suggestion and how many are right; read-only')]
class ImportSuggestionAccuracyCommand extends Command
{
    private const array PERIOD_LABELS = ['year' => 'whole year', 'aug_nov' => 'Aug-Nov'];

    public function handle(ImportCategorySuggestionAccuracy $accuracy): int
    {
        $user = $this->resolveUser((string) $this->argument('user'));

        if ($user === null) {
            $this->components->error('User not found.');

            return self::FAILURE;
        }

        $year = $this->option('year');
        $year = is_string($year) && $year !== '' ? (int) $year : $accuracy->latestYear($user);

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

        $output = $this->option('output');

        if (is_string($output) && $output !== '') {
            $this->writeJson($this->resolvePath($output), $result);
            $this->components->info("Result written to {$output}.");
        }

        $compare = $this->option('compare');

        return is_string($compare) && $compare !== '' ? $this->compare($accuracy, $compare, $result) : self::SUCCESS;
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

    private function resolveUser(string $identifier): ?User
    {
        return ctype_digit($identifier)
            ? User::query()->find((int) $identifier)
            : User::query()->where('email', $identifier)->first();
    }

    private function resolvePath(string $path): string
    {
        return str_starts_with($path, '/') ? $path : base_path($path);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function writeJson(string $path, array $values): void
    {
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function readJson(string $path): array
    {
        if (! File::isFile($path)) {
            throw new JsonException('the file does not exist');
        }

        $decoded = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new JsonException('the file does not hold a JSON object');
        }

        return $decoded;
    }
}

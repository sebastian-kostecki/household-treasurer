<?php

namespace App\Console\Commands;

use App\Support\Console\ReadsAndWritesJsonFiles;
use App\Support\CsvImport\ImportCategorySuggestionReference;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;
use JsonException;

#[Signature('import:suggestion-reference {snapshot : A user:backup snapshot file (version 10 or newer)} {--year= : Year of the rows; default the latest year of imported rows} {--output= : Write the set to this JSON file; default tests/Fixtures/suggestions/reference-<year>.json}')]
#[Description('Build the pseudonymised reference set of the suggestion benchmark from a snapshot; no bank text and no amounts are written')]
class ImportSuggestionReferenceCommand extends Command
{
    use ReadsAndWritesJsonFiles;

    public function handle(ImportCategorySuggestionReference $reference): int
    {
        $path = $this->resolvePath((string) $this->argument('snapshot'));

        try {
            $snapshot = $this->readJson($path);
        } catch (JsonException $exception) {
            $this->components->error("Cannot read the snapshot: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $year = (string) $this->option('year');

        if ($year !== '' && ! ctype_digit($year)) {
            $this->components->error("--year must be a year, got \"{$year}\".");

            return self::FAILURE;
        }

        $year = $year !== '' ? (int) $year : $reference->latestYear($snapshot);

        if ($year === null) {
            $this->components->error('The snapshot has no imported rows.');

            return self::FAILURE;
        }

        try {
            $set = $reference->build($snapshot, $year, [
                'snapshot' => basename($path),
                'sha256' => hash_file('sha256', $path) ?: '',
                'created_at' => is_string($snapshot['created_at'] ?? null) ? $snapshot['created_at'] : null,
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $output = $this->option('output');
        $output = is_string($output) && $output !== '' ? $output : "tests/Fixtures/suggestions/reference-{$year}.json";
        $this->writeJson($this->resolvePath($output), $set);

        $this->components->info(count($set['rows']).' rows and '.count($set['categories'])." categories written to {$output}.");

        return self::SUCCESS;
    }
}

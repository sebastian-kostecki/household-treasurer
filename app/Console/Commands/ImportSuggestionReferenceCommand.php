<?php

namespace App\Console\Commands;

use App\Support\Console\ReadsAndWritesJsonFiles;
use App\Support\CsvImport\ImportCategorySuggestionReference;
use App\Support\CsvImport\ImportSuggestionReferenceException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use JsonException;

#[Signature('import:suggestion-reference {snapshot : A user:backup snapshot file (version 10 or newer)} {--year= : Year of the rows; default the latest year of imported rows} {--output= : Write the set to this JSON file; default tests/Fixtures/suggestions/reference-<year>.json}')]
#[Description('Build the pseudonymised reference set of the suggestion benchmark from a snapshot; no bank text and no amounts are written')]
class ImportSuggestionReferenceCommand extends Command
{
    use ReadsAndWritesJsonFiles;

    public function handle(ImportCategorySuggestionReference $reference): int
    {
        $path = $this->resolvePath((string) $this->argument('snapshot'));
        $optionError = $this->optionError($path);

        if ($optionError !== null) {
            $this->components->error($optionError);

            return self::FAILURE;
        }

        try {
            $snapshot = $this->readJson($path);
        } catch (JsonException $exception) {
            $this->components->error("Cannot read the snapshot: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $sha256 = hash_file('sha256', $path);

        if ($sha256 === false) {
            $this->components->error('Cannot hash the snapshot.');

            return self::FAILURE;
        }

        try {
            $year = $this->filledOption('year');
            $year = $year !== null ? (int) $year : $reference->latestYear($snapshot);

            if ($year === null) {
                $this->components->error('The snapshot has no imported rows.');

                return self::FAILURE;
            }

            $set = $reference->build($snapshot, $year, [
                'snapshot' => basename($path),
                'sha256' => $sha256,
                'created_at' => is_string($snapshot['created_at'] ?? null) ? $snapshot['created_at'] : null,
            ]);
        } catch (ImportSuggestionReferenceException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $output = $this->filledOption('output') ?? "tests/Fixtures/suggestions/reference-{$year}.json";

        if ($this->isSameFile($this->resolvePath($output), $path)) {
            $this->components->error('--output names the snapshot: the run would overwrite it.');

            return self::FAILURE;
        }

        $this->writeJson($this->resolvePath($output), $set);

        $this->components->info(count($set['rows']).' rows and '.count($set['categories'])." categories written to {$output}.");

        return self::SUCCESS;
    }

    /**
     * Checked before the snapshot is read: a non-numeric year would read as year
     * 0, and an output file that is the snapshot would replace the backup.
     */
    private function optionError(string $snapshotPath): ?string
    {
        $year = $this->filledOption('year');

        if ($year !== null && ! ctype_digit($year)) {
            return "--year must be a year, got \"{$year}\".";
        }

        $output = $this->filledOption('output');

        if ($output !== null && $this->isSameFile($this->resolvePath($output), $snapshotPath)) {
            return '--output names the snapshot: the run would overwrite it.';
        }

        return null;
    }
}

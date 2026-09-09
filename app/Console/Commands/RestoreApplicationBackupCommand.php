<?php

namespace App\Console\Commands;

use App\Actions\RestoreApplicationBackup;
use App\Support\Backup\ApplicationBackupException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('backup:restore {path : Path to the Spatie backup zip archive} {--force : Restore without confirmation}')]
#[Description('Restore the application database and storage files from a Spatie backup archive')]
class RestoreApplicationBackupCommand extends Command
{
    public function handle(RestoreApplicationBackup $restoreApplicationBackup): int
    {
        $path = $this->argument('path');

        try {
            $summary = $restoreApplicationBackup->inspectArchive($path);
        } catch (ApplicationBackupException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $databaseDump = $summary['database_dumps'][0] ?? 'unknown';
        $sizeInMegabytes = number_format($summary['archive_size_bytes'] / 1024 / 1024, 2);

        if (! $this->option('force') && ! $this->confirm(
            "Restore application backup from {$summary['archive_path']} ({$sizeInMegabytes} MB, dump: {$databaseDump}, files: {$summary['file_entries']})? This replaces the current database and storage files.",
        )) {
            $this->components->warn('Restore cancelled.');

            return self::SUCCESS;
        }

        try {
            $restoreApplicationBackup->handle($path);
        } catch (ApplicationBackupException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Restored application backup from {$summary['archive_path']}.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Actions;

use App\Support\Backup\ApplicationBackupException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Symfony\Component\Process\Process;
use ZipArchive;

class RestoreApplicationBackup
{
    /**
     * @return array{
     *     archive_path: string,
     *     archive_size_bytes: int,
     *     database_dumps: list<string>,
     *     file_entries: int,
     * }
     */
    public function inspectArchive(string $archivePath): array
    {
        $resolvedPath = $this->resolveArchivePath($archivePath);
        $temporaryDirectory = TemporaryDirectory::make();

        try {
            $extractedPath = $this->extractArchive($resolvedPath, $temporaryDirectory);

            return [
                'archive_path' => $resolvedPath,
                'archive_size_bytes' => filesize($resolvedPath),
                'database_dumps' => $this->findDatabaseDumps($extractedPath),
                'file_entries' => $this->countFileEntries($extractedPath),
            ];
        } finally {
            $temporaryDirectory->delete();
        }
    }

    public function handle(string $archivePath): void
    {
        $connection = config('database.default');
        DB::disconnect($connection);
        DB::purge($connection);

        $resolvedPath = $this->resolveArchivePath($archivePath);
        $temporaryDirectory = TemporaryDirectory::make();

        try {
            $extractedPath = $this->extractArchive($resolvedPath, $temporaryDirectory);
            $this->restoreDatabase($extractedPath);
            $this->restoreFiles($extractedPath);
        } finally {
            $temporaryDirectory->delete();
        }
    }

    private function resolveArchivePath(string $path): string
    {
        if (! is_file($path)) {
            throw ApplicationBackupException::archiveNotFound($path);
        }

        if (! str_ends_with(strtolower($path), '.zip')) {
            throw ApplicationBackupException::invalidArchive($path);
        }

        return $path;
    }

    private function extractArchive(string $archivePath, TemporaryDirectory $temporaryDirectory): string
    {
        $zip = new ZipArchive;

        if ($zip->open($archivePath) !== true) {
            throw ApplicationBackupException::cannotOpenArchive($archivePath);
        }

        $password = config('backup.backup.password');

        if ($password !== null) {
            $zip->setPassword($password);
        }

        $extractTo = $temporaryDirectory->path('extracted');
        File::ensureDirectoryExists($extractTo);

        if (! $zip->extractTo($extractTo)) {
            $zip->close();

            throw ApplicationBackupException::cannotExtractArchive($archivePath);
        }

        $zip->close();

        return $extractTo;
    }

    /**
     * @return list<string>
     */
    private function findDatabaseDumps(string $extractedPath): array
    {
        $dumpDirectory = $extractedPath.DIRECTORY_SEPARATOR.'db-dumps';

        if (! is_dir($dumpDirectory)) {
            return [];
        }

        $dumps = [];

        foreach (scandir($dumpDirectory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dumpDirectory.DIRECTORY_SEPARATOR.$entry;

            if (is_file($path)) {
                $dumps[] = $entry;
            }
        }

        sort($dumps);

        return $dumps;
    }

    private function countFileEntries(string $extractedPath): int
    {
        $count = 0;

        foreach ($this->fileEntries($extractedPath) as $file) {
            $count++;
        }

        return $count;
    }

    private function restoreDatabase(string $extractedPath): void
    {
        $dumps = $this->findDatabaseDumps($extractedPath);

        if ($dumps === []) {
            throw ApplicationBackupException::databaseDumpNotFound();
        }

        if (count($dumps) > 1) {
            throw ApplicationBackupException::databaseRestoreFailed('Expected exactly one database dump in the archive.');
        }

        $dumpPath = $extractedPath.DIRECTORY_SEPARATOR.'db-dumps'.DIRECTORY_SEPARATOR.$dumps[0];

        /** @var list<string> $backupConnections */
        $backupConnections = config('backup.backup.source.databases', [config('database.default')]);
        $connection = $backupConnections[0] ?? config('database.default');

        if (str_starts_with($dumps[0], 'postgresql-')) {
            $this->restorePostgresDatabase($dumpPath, $connection);

            return;
        }

        if (str_starts_with($dumps[0], 'sqlite-')) {
            $this->restoreSqliteDatabase($dumpPath, $connection);

            return;
        }

        throw ApplicationBackupException::unsupportedDatabaseDump($dumps[0]);
    }

    private function restorePostgresDatabase(string $dumpPath, string $connection): void
    {
        $config = config("database.connections.{$connection}");

        if (($config['driver'] ?? null) !== 'pgsql') {
            throw ApplicationBackupException::databaseRestoreFailed('Active database connection is not PostgreSQL.');
        }

        DB::disconnect($connection);

        DB::connection($connection)->statement('DROP SCHEMA public CASCADE');
        DB::connection($connection)->statement('CREATE SCHEMA public');
        DB::connection($connection)->statement('GRANT ALL ON SCHEMA public TO public');

        $this->runProcess($this->buildPostgresRestoreProcess($dumpPath, $config));

        DB::purge($connection);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function buildPostgresRestoreProcess(string $dumpPath, array $config): Process
    {
        $command = [
            'psql',
            '--host='.$config['host'],
            '--port='.$config['port'],
            '--username='.$config['username'],
            '--dbname='.$config['database'],
            '--file='.$dumpPath,
            '--single-transaction',
            '--set=ON_ERROR_STOP=on',
        ];

        return new Process($command, null, [
            'PGPASSWORD' => (string) ($config['password'] ?? ''),
        ]);
    }

    private function restoreSqliteDatabase(string $dumpPath, string $connection): void
    {
        $config = config("database.connections.{$connection}");

        if (($config['driver'] ?? null) !== 'sqlite') {
            throw ApplicationBackupException::databaseRestoreFailed('Active database connection is not SQLite.');
        }

        $databasePath = $config['database'];

        if ($databasePath === ':memory:') {
            throw ApplicationBackupException::databaseRestoreFailed('Cannot restore a SQLite backup into an in-memory database.');
        }

        DB::disconnect($connection);
        DB::purge($connection);

        File::ensureDirectoryExists(dirname($databasePath));

        if (is_file($databasePath)) {
            unlink($databasePath);
        }

        touch($databasePath);

        $sqlite = new \SQLite3($databasePath);
        $sqlite->busyTimeout(5000);

        if ($sqlite->lastErrorCode() !== 0) {
            throw ApplicationBackupException::databaseRestoreFailed($sqlite->lastErrorMsg());
        }

        $result = $sqlite->exec((string) file_get_contents($dumpPath));

        if ($result === false) {
            throw ApplicationBackupException::databaseRestoreFailed($sqlite->lastErrorMsg());
        }

        $sqlite->close();

        DB::purge($connection);
    }

    private function runProcess(Process $process): void
    {
        $process->run();

        if (! $process->isSuccessful()) {
            throw ApplicationBackupException::databaseRestoreFailed(trim($process->getErrorOutput() ?: $process->getOutput()));
        }
    }

    private function restoreFiles(string $extractedPath): void
    {
        $storageRoot = storage_path('app');
        $protectedDirectories = [
            'backups',
            'backup-temp',
        ];

        foreach ($this->fileEntries($extractedPath) as $relativePath) {
            if (str_starts_with($relativePath, 'db-dumps/') || str_starts_with($relativePath, 'db-dumps\\')) {
                continue;
            }

            $firstSegment = strtok(str_replace('\\', '/', $relativePath), '/');

            if (in_array($firstSegment, $protectedDirectories, true)) {
                continue;
            }

            $source = $extractedPath.DIRECTORY_SEPARATOR.$relativePath;
            $target = $storageRoot.DIRECTORY_SEPARATOR.$relativePath;

            File::ensureDirectoryExists(dirname($target));
            File::copy($source, $target);
        }
    }

    /**
     * @return \Generator<int, string>
     */
    private function fileEntries(string $directory): \Generator
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relativePath = substr($file->getPathname(), strlen($directory) + 1);

            yield str_replace('\\', '/', $relativePath);
        }
    }
}

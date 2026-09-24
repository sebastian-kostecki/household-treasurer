<?php

namespace Tests\Feature;

use App\Actions\RestoreApplicationBackup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class ApplicationBackupRestoreTest extends TestCase
{
    use RefreshDatabase;

    private string $databasePath;

    private string $originalDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (! $this->usesFileDatabase()) {
            return;
        }

        $this->databasePath = storage_path('app/testing/application-backup-'.uniqid('', true).'.sqlite');

        File::ensureDirectoryExists(dirname($this->databasePath));

        if (is_file($this->databasePath)) {
            unlink($this->databasePath);
        }

        touch($this->databasePath);

        $this->originalDatabase = Config::string('database.connections.sqlite.database');

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', $this->databasePath);

        // Migrations only run once per test run (RefreshDatabaseState::$migrated),
        // so force a re-migration against this freshly created file database.
        RefreshDatabaseState::$migrated = false;
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->usesFileDatabase()) {
            return;
        }

        if (! is_file($this->databasePath)) {
            touch($this->databasePath);
        }

        DB::purge('sqlite');
        DB::reconnect('sqlite');
    }

    protected function tearDown(): void
    {
        if ($this->usesFileDatabase() && isset($this->databasePath) && is_file($this->databasePath)) {
            unlink($this->databasePath);
        }

        if ($this->usesFileDatabase() && isset($this->originalDatabase)) {
            Config::set('database.connections.sqlite.database', $this->originalDatabase);

            // Restore migrations for the default connection so later tests
            // in this run get a freshly migrated database.
            RefreshDatabaseState::$migrated = false;
        }

        parent::tearDown();
    }

    private function usesFileDatabase(): bool
    {
        return $this->name() === 'test_restore_command_replays_database_and_storage_files';
    }

    public function test_restore_command_replays_database_and_storage_files(): void
    {
        $user = User::factory()->create([
            'email' => 'backup-user@example.com',
        ]);

        Storage::disk('local')->put('restore-marker.txt', 'before-restore');

        $this->assertDatabaseHas('users', [
            'email' => 'backup-user@example.com',
        ]);

        $archivePath = $this->createBackupArchive(
            $this->createSqliteDump(),
            [
                'private/restore-marker.txt' => 'before-restore',
            ],
        );

        Storage::disk('local')->put('restore-marker.txt', 'after-delete');

        $restoredDatabasePath = storage_path('app/testing/restored-'.uniqid('', true).'.sqlite');

        Config::set('database.connections.sqlite.database', $restoredDatabasePath);

        DB::disconnect('sqlite');
        DB::purge('sqlite');

        app(RestoreApplicationBackup::class)->handle($archivePath);

        $pdo = new \PDO('sqlite:'.$restoredDatabasePath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->assertSame(
            1,
            (int) $pdo->query("SELECT COUNT(*) FROM users WHERE email = 'backup-user@example.com'")->fetchColumn(),
        );

        $this->assertSame(
            'before-restore',
            Storage::disk('local')->get('restore-marker.txt'),
        );
    }

    public function test_restore_command_fails_when_archive_is_missing(): void
    {
        $this->artisan('backup:restore', [
            'path' => storage_path('app/missing-backup.zip'),
            '--force' => true,
        ])->assertFailed();
    }

    public function test_restore_command_fails_for_non_zip_path(): void
    {
        $path = storage_path('app/not-a-backup.txt');
        file_put_contents($path, 'nope');

        $this->artisan('backup:restore', [
            'path' => $path,
            '--force' => true,
        ])->assertFailed();

        unlink($path);
    }

    /**
     * @param  array<string, string>  $storageFiles
     */
    private function createBackupArchive(string $databaseDump, array $storageFiles = []): string
    {
        $sourceDirectory = storage_path('app/testing/backup-source-'.uniqid());
        $dumpDirectory = $sourceDirectory.'/db-dumps';

        File::ensureDirectoryExists($dumpDirectory);
        file_put_contents($dumpDirectory.'/sqlite-testing-database.sql', $databaseDump);

        foreach ($storageFiles as $relativePath => $contents) {
            $target = $sourceDirectory.'/'.$relativePath;
            File::ensureDirectoryExists(dirname($target));
            file_put_contents($target, $contents);
        }

        $archivePath = $sourceDirectory.'.zip';
        $zip = new ZipArchive;

        if ($zip->open($archivePath, ZipArchive::CREATE) !== true) {
            throw new \RuntimeException('Could not create test backup archive.');
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDirectory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relativePath = substr($file->getPathname(), strlen($sourceDirectory) + 1);
            $zip->addFile($file->getPathname(), str_replace('\\', '/', $relativePath));
        }

        $zip->close();
        File::deleteDirectory($sourceDirectory);

        return $archivePath;
    }

    private function createSqliteDump(): string
    {
        $pdo = new \PDO('sqlite:'.$this->databasePath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $parts = [
            'PRAGMA foreign_keys=OFF;',
            'BEGIN TRANSACTION;',
        ];

        $createStatements = $pdo
            ->query("SELECT sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")
            ->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($createStatements as $statement) {
            $parts[] = $statement.';';
        }

        $tables = $pdo
            ->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")
            ->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $rows = $pdo->query('SELECT * FROM "'.$table.'"')->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $columns = array_map(
                    fn (string $column): string => '"'.$column.'"',
                    array_keys($row),
                );

                $values = array_map(
                    fn (mixed $value) => $value === null ? 'NULL' : $pdo->quote((string) $value),
                    array_values($row),
                );

                $parts[] = sprintf(
                    'INSERT INTO "%s" (%s) VALUES (%s);',
                    $table,
                    implode(', ', $columns),
                    implode(', ', $values),
                );
            }
        }

        $parts[] = 'COMMIT;';

        return implode("\n", $parts);
    }
}

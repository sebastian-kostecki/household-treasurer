<?php

namespace App\Support\Backup;

use RuntimeException;

class ApplicationBackupException extends RuntimeException
{
    public static function archiveNotFound(string $path): self
    {
        return new self("Backup archive not found: {$path}");
    }

    public static function invalidArchive(string $path): self
    {
        return new self("Backup archive must be a .zip file: {$path}");
    }

    public static function cannotOpenArchive(string $path): self
    {
        return new self("Could not open backup archive: {$path}");
    }

    public static function cannotExtractArchive(string $path): self
    {
        return new self("Could not extract backup archive: {$path}");
    }

    public static function databaseDumpNotFound(): self
    {
        return new self('No database dump found in backup archive.');
    }

    public static function cannotDetermineArchiveSize(string $path): self
    {
        return new self("Could not determine backup archive size: {$path}");
    }

    public static function unsupportedDatabaseDump(string $filename): self
    {
        return new self("Unsupported database dump in backup archive: {$filename}");
    }

    public static function databaseRestoreFailed(string $message): self
    {
        return new self("Database restore failed: {$message}");
    }
}

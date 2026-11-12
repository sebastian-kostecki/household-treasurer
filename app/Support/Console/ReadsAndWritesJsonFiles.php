<?php

namespace App\Support\Console;

use Illuminate\Support\Facades\File;
use JsonException;

/**
 * The `--output` / `--compare` files of the read-only console readings
 * (`app:control-numbers`, `import:suggestion-accuracy`): a relative path counts
 * from `base_path()`, and a missing or non-object file is an error, never an
 * empty comparison.
 */
trait ReadsAndWritesJsonFiles
{
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

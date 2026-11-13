<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Lang;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class TranslationKeysTest extends TestCase
{
    public function test_every_literal_translation_key_used_in_app_has_a_polish_translation(): void
    {
        $missing = collect($this->literalKeysUsedInApp())
            ->reject(fn (string $key): bool => Lang::hasForLocale($key, 'pl'))
            ->values()
            ->all();

        $this->assertSame(
            [],
            $missing,
            "Missing Polish translations:\n".implode("\n", $missing),
        );
    }

    /**
     * @return list<string>
     */
    private function literalKeysUsedInApp(): array
    {
        $keys = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path(), RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());
            $pattern = '/(?<![\w>:$])(?:__|trans|trans_choice)\(\s*(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)")\s*[,)]/s';

            if (preg_match_all($pattern, $source, $matches, PREG_SET_ORDER) === false) {
                continue;
            }

            foreach ($matches as $match) {
                if (($match[1] ?? '') !== '') {
                    $keys[] = str_replace(["\\'", '\\\\'], ["'", '\\'], $match[1]);
                } elseif (! str_contains($match[2], '$')) {
                    $keys[] = stripcslashes($match[2]);
                }
            }
        }

        $keys = array_values(array_unique($keys));
        sort($keys);

        return $keys;
    }
}

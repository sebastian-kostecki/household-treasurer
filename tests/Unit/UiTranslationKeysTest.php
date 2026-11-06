<?php

use Symfony\Component\Finder\Finder;

/**
 * Every `t.<group>.<key>` used in resources/js exists in lang/pl/ui.php.
 *
 * This is a lower bound: dynamic keys (`t.group[name]`) and bracket access are
 * invisible to the scan, so a green run does not prove every key resolves.
 */
it('defines every translation key used in the frontend', function (): void {
    $ui = require dirname(__DIR__, 2).'/lang/pl/ui.php';
    $jsRoot = dirname(__DIR__, 2).'/resources/js';

    $files = Finder::create()
        ->files()
        ->in($jsRoot)
        ->exclude(['actions', 'routes', 'wayfinder'])
        ->name(['*.vue', '*.ts'])
        ->notName('*.test.ts');

    $missing = [];

    foreach ($files as $file) {
        // Prettier breaks member access over two lines, so match across the whole file.
        $contents = $file->getContents();

        preg_match_all('/\bt\s*\.\s*([a-z_]+)\s*\.\s*([a-z0-9_]+)/', $contents, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($matches as $match) {
            [$group, $key] = [$match[1][0], $match[2][0]];

            if (isset($ui[$group]) && is_array($ui[$group]) && array_key_exists($key, $ui[$group])) {
                continue;
            }

            $lineNumber = substr_count(substr($contents, 0, $match[0][1]), "\n") + 1;
            $missing[] = 'resources/js/'.$file->getRelativePathname().':'.$lineNumber.': '.$group.'.'.$key;
        }
    }

    $missing = array_values(array_unique($missing));

    expect($missing)->toBe([], "Missing keys in lang/pl/ui.php:\n".implode("\n", $missing));
});

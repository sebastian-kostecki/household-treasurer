<?php

/**
 * @return list<string>
 */
function adrFileNames(): array
{
    $paths = glob(__DIR__.'/../../context/foundation/adr/*.md') ?: [];
    $names = array_map('basename', $paths);
    sort($names);

    return $names;
}

test('every ADR file name starts with a four digit number', function () {
    $invalid = array_values(array_filter(
        adrFileNames(),
        fn (string $name): bool => preg_match('/^\d{4}-[a-z0-9-]+\.md$/', $name) !== 1,
    ));

    expect($invalid)->toBe([]);
});

test('no two ADR files share a number', function () {
    $byNumber = [];

    foreach (adrFileNames() as $name) {
        $byNumber[substr($name, 0, 4)][] = $name;
    }

    $duplicates = array_filter($byNumber, fn (array $names): bool => count($names) > 1);
    $report = array_map(
        fn (string $number, array $names): string => $number.': '.implode(', ', $names),
        array_keys($duplicates),
        $duplicates,
    );

    expect($report)->toBe([]);
});

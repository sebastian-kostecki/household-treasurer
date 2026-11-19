<?php

/**
 * Code in app/ reads the calendar day only through App\Support\AppDay (ADR-0103, "Server"). A clock read for a
 * domain decision elsewhere gives a different day than the rest of the application between 00:00 in Poland and
 * 00:00 UTC. `now()` as a timestamp and `now('Europe/Warsaw')` stay allowed.
 *
 * The exception list can only shrink.
 */
const APP_DAY_CLOCK_ALLOWED_FILES = [
    'app/Support/AppDay.php',
    'app/Support/RehearsalClock.php',
    'app/Console/Commands/RehearsalClockCommand.php',
];

const APP_DAY_CLOCK_FORBIDDEN_PATTERNS = [
    'static today() other than AppDay' => '/(?<!AppDay)::today\(/',
    'today() helper' => '/(?<![\w:>$])today\(/',
    'now() read as a day' => '/now\(\)->(?:year|month|toDateString)\b/',
    'before_or_equal:today' => '/before_or_equal:today/',
];

/**
 * @return list<string> findings as "file:line: kind: match"
 */
function appDayClockFindings(string $relativePath, string $source): array
{
    $findings = [];

    foreach (explode("\n", $source) as $index => $line) {
        foreach (APP_DAY_CLOCK_FORBIDDEN_PATTERNS as $kind => $pattern) {
            if (preg_match($pattern, $line, $match) === 1) {
                $findings[] = sprintf('%s:%d: %s: %s', $relativePath, $index + 1, $kind, $match[0]);
            }
        }
    }

    return $findings;
}

test('app code reads the calendar day only through AppDay', function () {
    $root = dirname(__DIR__, 2);
    $findings = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app', FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $relativePath = substr($file->getPathname(), strlen($root) + 1);

        if (in_array($relativePath, APP_DAY_CLOCK_ALLOWED_FILES, true)) {
            continue;
        }

        $findings = [...$findings, ...appDayClockFindings($relativePath, file_get_contents($file->getPathname()))];
    }

    expect($findings)->toBe([]);
});

test('the clock guard flags the readings it exists for and leaves AppDay and timestamps alone', function () {
    $flagged = [
        "CarbonImmutable::today('UTC')",
        'Carbon::today()',
        'today()',
        'now()->year',
        'now()->month',
        'now()->toDateString()',
        "'before_or_equal:today'",
    ];

    foreach ($flagged as $source) {
        expect(appDayClockFindings('x.php', $source))->not->toBe([], $source);
    }

    foreach (['AppDay::today()', 'now()', "now('Europe/Warsaw')", '$this->today()'] as $source) {
        expect(appDayClockFindings('x.php', $source))->toBe([], $source);
    }
});

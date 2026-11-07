<?php

/**
 * The welcome page files take values from tokens and named classes in app.css,
 * never from literals (ADR-0057). app.css itself is the token source and is not
 * scanned.
 */
const WELCOME_VIEW_FILES = [
    'resources/js/pages/Welcome.vue',
    'resources/js/components/LandingAppPreview.vue',
    'resources/js/components/LandingFeatureCard.vue',
];

const WELCOME_FORBIDDEN_PATTERNS = [
    'hex colour' => '/#[0-9a-fA-F]{3,8}\b/',
    'colour function' => '/\b(?:rgb|rgba|hsl|hsla|oklch)\(/',
    'Tailwind palette class' => '/\b(?:bg|text|border|ring|fill|stroke|from|to|via|divide|outline|shadow)-(?:slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)-\d{2,3}\b/',
    'arbitrary value' => '/-\[[^\]]*\d(?:px|rem|em|%|vh|vw|fr)?[^\]]*\]/',
    'arbitrary grid' => '/grid-cols-\[/',
    'pre-Atrament text size' => '/\btext-(?:lg|xl|2xl|3xl)\b/',
    'pre-Atrament tracking' => '/\btracking-(?:wider|tight)\b/',
    'pre-Atrament radius' => '/\brounded-(?:xl|2xl)\b/',
    'pre-Atrament border opacity' => '/\bborder-border\/\d+/',
    'opacity utility' => '/\bopacity-\d+\b/',
    'pre-Atrament body text size' => '/\btext-base\b/',
    'pre-Atrament content width' => '/\bmax-w-6xl\b/',
    'pre-Atrament button size' => '/\bsize="lg"/',
    'resting shadow' => '/\bshadow-sm\b/',
    'blurred bar' => '/\bbackdrop-blur\b/',
    'income colour' => '/\btext-success\b/',
];

/**
 * @return list<string> findings as "file:line: kind: match"
 */
function welcomeHardcodedUiFindings(string $relativePath, string $source): array
{
    $findings = [];

    foreach (explode("\n", $source) as $index => $line) {
        foreach (WELCOME_FORBIDDEN_PATTERNS as $kind => $pattern) {
            if (preg_match($pattern, $line, $match) === 1) {
                $findings[] = sprintf('%s:%d: %s: %s', $relativePath, $index + 1, $kind, $match[0]);
            }
        }
    }

    return $findings;
}

test('welcome page files contain no hardcoded colours, arbitrary values or pre-Atrament styles', function () {
    $findings = [];

    foreach (WELCOME_VIEW_FILES as $relativePath) {
        $source = file_get_contents(__DIR__.'/../../'.$relativePath);

        expect($source)->not->toBeFalse("{$relativePath} is missing");

        $findings = [...$findings, ...welcomeHardcodedUiFindings($relativePath, $source)];
    }

    expect($findings)->toBe([], "Use tokens or a named class in app.css instead:\n".implode("\n", $findings));
});

test('the scan reports file and line for an arbitrary value', function () {
    $findings = welcomeHardcodedUiFindings('resources/js/components/LandingAppPreview.vue', "<div>\n<div class=\"min-h-[22rem]\">\n</div>");

    expect($findings)->toBe(['resources/js/components/LandingAppPreview.vue:2: arbitrary value: -[22rem]']);
});

test('the scan catches styles from before the welcome page was cleaned', function () {
    $source = implode("\n", [
        'class="text-3xl"',
        'class="text-base"',
        'class="tracking-tight"',
        'class="rounded-2xl"',
        'class="border-border/60"',
        'class="opacity-60"',
        'class="max-w-6xl"',
        '<Button size="lg">',
        'class="shadow-sm"',
        'class="backdrop-blur"',
        'class="text-success"',
    ]);

    $kinds = array_map(
        fn (string $finding): string => explode(': ', $finding)[1],
        welcomeHardcodedUiFindings('x.vue', $source),
    );

    expect($kinds)->toBe([
        'pre-Atrament text size',
        'pre-Atrament body text size',
        'pre-Atrament tracking',
        'pre-Atrament radius',
        'pre-Atrament border opacity',
        'opacity utility',
        'pre-Atrament content width',
        'pre-Atrament button size',
        'resting shadow',
        'blurred bar',
        'income colour',
    ]);
});

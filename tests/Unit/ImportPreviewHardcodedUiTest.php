<?php

/**
 * Import step 2 view files take values from tokens and named classes in app.css,
 * never from literals (ADR-0052). app.css itself is the token source and is not scanned.
 * Sink and fixtures join the list in phase 4. The scanner is a copy of AccountsHardcodedUiTest; a shared helper for all
 * *HardcodedUiTest files is the open debt F6 of the change accounts-atrament-ui.
 */
const IMPORT_PREVIEW_VIEW_FILES = [
    'resources/js/pages/OperationsImportPreview.vue',
    'resources/js/components/ImportPreviewRow.vue',
    'resources/js/components/ImportPreviewSubmitBar.vue',
];

const IMPORT_PREVIEW_FORBIDDEN_PATTERNS = [
    'hex colour' => '/#[0-9a-fA-F]{3,8}\b/',
    'colour function' => '/\b(?:rgb|rgba|hsl|hsla|oklch)\(/',
    'Tailwind palette class' => '/\b(?:bg|text|border|ring|fill|stroke|from|to|via|divide|outline|shadow)-(?:slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)-\d{2,3}\b/',
    'arbitrary value' => '/-\[[^\]]*\d(?:px|rem|em|%|vh|vw|fr)?[^\]]*\]/',
    'arbitrary grid' => '/grid-cols-\[/',
    'pre-Atrament text size' => '/\btext-(?:lg|xl|2xl|3xl)\b/',
    'pre-Atrament tracking' => '/\btracking-wider\b/',
    'pre-Atrament radius' => '/\brounded-(?:xl|2xl)\b/',
    'pre-Atrament border opacity' => '/\bborder-border\/\d+/',
    'opacity utility' => '/\bopacity-\d+\b/',
    'pre-Atrament body text size' => '/\btext-base\b/',
    'pre-Atrament divider opacity' => '/\bdivide-border\/\d+/',
    'removed section-label' => '/\bsection-label\b/',
    'pre-Atrament caption size' => '/\btext-(?:xs|sm)\b/',
    'pre-Atrament weight' => '/\bfont-semibold\b/',
    'full-colour badge' => '/variant="(?:secondary|destructive)"/',
    'signed tone on operation amount' => '/\bamountToneClass\b/',
    'card title' => '/\bCardTitle\b/',
];

/**
 * @return list<string> findings as "file:line: kind: match"
 */
function importPreviewHardcodedUiFindings(string $relativePath, string $source): array
{
    $findings = [];

    foreach (explode("\n", $source) as $index => $line) {
        foreach (IMPORT_PREVIEW_FORBIDDEN_PATTERNS as $kind => $pattern) {
            if (preg_match($pattern, $line, $match) === 1) {
                $findings[] = sprintf('%s:%d: %s: %s', $relativePath, $index + 1, $kind, $match[0]);
            }
        }
    }

    return $findings;
}

test('import preview view files contain no hardcoded colours, arbitrary values or pre-Atrament styles', function () {
    $findings = [];

    foreach (IMPORT_PREVIEW_VIEW_FILES as $relativePath) {
        $source = file_get_contents(__DIR__.'/../../'.$relativePath);

        expect($source)->not->toBeFalse("{$relativePath} is missing");

        $findings = [...$findings, ...importPreviewHardcodedUiFindings($relativePath, $source)];
    }

    expect($findings)->toBe([], "Use tokens or a named class in app.css instead:\n".implode("\n", $findings));
});

test('the scan reports file and line for an arbitrary value', function () {
    $findings = importPreviewHardcodedUiFindings('resources/js/pages/OperationsImport.vue', "<div>\n<th class=\"text-[11px]\">\n</div>");

    expect($findings)->toBe(['resources/js/pages/OperationsImport.vue:2: arbitrary value: -[11px]']);
});

test('the scan catches styles from before the Atrament system', function () {
    $source = implode("\n", [
        'class="text-3xl"',
        'class="text-base"',
        'class="tracking-wider"',
        'class="rounded-2xl"',
        'class="border-border/60"',
        'class="divide-border/60"',
        'class="opacity-60"',
        'class="section-label"',
        'class="text-xs"',
        'class="font-semibold"',
        '<Badge variant="secondary">',
        ':class="amountToneClass(1)"',
        '<CardTitle>',
    ]);

    $kinds = array_map(
        fn (string $finding): string => explode(': ', $finding)[1],
        importPreviewHardcodedUiFindings('x.vue', $source),
    );

    expect($kinds)->toBe([
        'pre-Atrament text size',
        'pre-Atrament body text size',
        'pre-Atrament tracking',
        'pre-Atrament radius',
        'pre-Atrament border opacity',
        'pre-Atrament divider opacity',
        'opacity utility',
        'removed section-label',
        'pre-Atrament caption size',
        'pre-Atrament weight',
        'full-colour badge',
        'signed tone on operation amount',
        'card title',
    ]);
});

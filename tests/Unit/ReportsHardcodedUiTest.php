<?php

/**
 * Reports view files take values from tokens and named classes in app.css,
 * never from literals. app.css itself is the token source and is not scanned.
 */
const REPORTS_VIEW_FILES = [
    'resources/js/pages/Reports.vue',
    'resources/js/pages/dev/ReportsKitchenSink.vue',
    'resources/js/lib/reports-fixtures.ts',
    'resources/js/components/ReportStatPanel.vue',
    'resources/js/components/ReportEntryTable.vue',
    'resources/js/components/ReportGroupRow.vue',
    'resources/js/components/ReportCategoryRow.vue',
    'resources/js/components/ReportBarChart.vue',
];

const REPORTS_FORBIDDEN_PATTERNS = [
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
    'removed section-label' => '/\bsection-label\b/',
];

/**
 * @return list<string> findings as "file:line: kind: match"
 */
function reportsHardcodedUiFindings(string $relativePath, string $source): array
{
    $findings = [];

    foreach (explode("\n", $source) as $index => $line) {
        foreach (REPORTS_FORBIDDEN_PATTERNS as $kind => $pattern) {
            if (preg_match($pattern, $line, $match) === 1) {
                $findings[] = sprintf('%s:%d: %s: %s', $relativePath, $index + 1, $kind, $match[0]);
            }
        }
    }

    return $findings;
}

test('reports view files contain no hardcoded colours, arbitrary values or arbitrary grids', function () {
    $findings = [];

    foreach (REPORTS_VIEW_FILES as $relativePath) {
        $source = file_get_contents(__DIR__.'/../../'.$relativePath);

        expect($source)->not->toBeFalse("{$relativePath} is missing");

        $findings = [...$findings, ...reportsHardcodedUiFindings($relativePath, $source)];
    }

    expect($findings)->toBe([], "Use tokens or a named class in app.css instead:\n".implode("\n", $findings));
});

test('the reports scan reports file and line for an arbitrary value', function () {
    $findings = reportsHardcodedUiFindings('resources/js/pages/Reports.vue', "<div>\n<th class=\"text-[11px]\">\n</div>");

    expect($findings)->toBe(['resources/js/pages/Reports.vue:2: arbitrary value: -[11px]']);
});

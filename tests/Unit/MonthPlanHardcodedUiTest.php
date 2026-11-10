<?php

/**
 * Month plan view files take values from tokens and named classes in app.css,
 * never from literals. app.css itself is the token source and is not scanned.
 */
const MONTH_PLAN_VIEW_FILES = [
    'resources/js/pages/MonthPlan.vue',
    'resources/js/pages/dev/MonthPlanSink.vue',
    'resources/js/components/MonthPlanItemRow.vue',
    'resources/js/components/CategoryChip.vue',
    'resources/js/components/PlanChangeArrow.vue',
    'resources/js/components/MonthPlanMatchActual.vue',
    'resources/js/components/ColumnHeaderHint.vue',
    'resources/js/components/MonthPlanStatPanel.vue',
    'resources/js/components/MonthPlanTable.vue',
    'resources/js/components/MonthPlanTitleRow.vue',
    'resources/js/components/StatPanel.vue',
    'resources/js/components/MonthSummaryCard.vue',
    'resources/js/components/MonthYearSummary.vue',
    'resources/js/components/BudgetMonthPicker.vue',
    'resources/js/components/SegmentedControl.vue',
    'resources/js/components/MonthPlanEditBar.vue',
];

const MONTH_PLAN_FORBIDDEN_PATTERNS = [
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
function monthPlanHardcodedUiFindings(string $relativePath, string $source): array
{
    $findings = [];

    foreach (explode("\n", $source) as $index => $line) {
        foreach (MONTH_PLAN_FORBIDDEN_PATTERNS as $kind => $pattern) {
            if (preg_match($pattern, $line, $match) === 1) {
                $findings[] = sprintf('%s:%d: %s: %s', $relativePath, $index + 1, $kind, $match[0]);
            }
        }
    }

    return $findings;
}

test('month plan view files contain no hardcoded colours, arbitrary values or arbitrary grids', function () {
    $findings = [];

    foreach (MONTH_PLAN_VIEW_FILES as $relativePath) {
        $source = file_get_contents(__DIR__.'/../../'.$relativePath);

        expect($source)->not->toBeFalse("{$relativePath} is missing");

        $findings = [...$findings, ...monthPlanHardcodedUiFindings($relativePath, $source)];
    }

    expect($findings)->toBe([], "Use tokens or a named class in app.css instead:\n".implode("\n", $findings));
});

test('the scan reports file and line for an arbitrary value', function () {
    $findings = monthPlanHardcodedUiFindings('resources/js/pages/MonthPlan.vue', "<div>\n<th class=\"text-[11px]\">\n</div>");

    expect($findings)->toBe(['resources/js/pages/MonthPlan.vue:2: arbitrary value: -[11px]']);
});

test('the scan catches palette classes, hex colours and arbitrary grids', function () {
    $source = implode("\n", ['class="bg-red-500"', 'style="color: #f53003"', 'class="grid grid-cols-[1fr_auto]"']);

    expect(monthPlanHardcodedUiFindings('x.vue', $source))->toHaveCount(4);
});

test('the scan catches styles from before the Atrament system', function () {
    $source = implode("\n", [
        'class="text-3xl"',
        'class="tracking-wider"',
        'class="rounded-2xl"',
        'class="border-border/60"',
        'class="opacity-60"',
        'class="section-label"',
    ]);

    $kinds = array_map(
        fn (string $finding): string => explode(': ', $finding)[1],
        monthPlanHardcodedUiFindings('x.vue', $source),
    );

    expect($kinds)->toBe([
        'pre-Atrament text size',
        'pre-Atrament tracking',
        'pre-Atrament radius',
        'pre-Atrament border opacity',
        'opacity utility',
        'removed section-label',
    ]);
});

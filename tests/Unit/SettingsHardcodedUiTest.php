<?php

/**
 * Settings view files take values from tokens and named classes in app.css,
 * never from literals (ADR-0049). Every t.settings.<key> used in them must exist
 * in lang/pl/ui.php, because the Record<string, string> type does not catch typos.
 */
const SETTINGS_VIEW_FILES = [
    'resources/js/layouts/settings/Layout.vue',
    'resources/js/pages/settings/Profile.vue',
    'resources/js/pages/settings/Security.vue',
    'resources/js/pages/settings/Appearance.vue',
    'resources/js/components/DeleteUser.vue',
    'resources/js/components/AppearanceTabs.vue',
    'resources/js/components/SettingsPanel.vue',
    'resources/js/pages/dev/SettingsKitchenSink.vue',
];

const SETTINGS_FORBIDDEN_PATTERNS = [
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
    'destructive tint' => '/\bbg-destructive\/\d+/',
    'Heading import' => '/import\s+Heading\b/',
];

/**
 * @return list<string> findings as "file:line: kind: match"
 */
function settingsHardcodedUiFindings(string $relativePath, string $source): array
{
    $findings = [];

    foreach (explode("\n", $source) as $index => $line) {
        foreach (SETTINGS_FORBIDDEN_PATTERNS as $kind => $pattern) {
            if (preg_match($pattern, $line, $match) === 1) {
                $findings[] = sprintf('%s:%d: %s: %s', $relativePath, $index + 1, $kind, $match[0]);
            }
        }
    }

    return $findings;
}

test('settings view files contain no hardcoded colours, arbitrary values or pre-Atrament styles', function () {
    $findings = [];

    foreach (SETTINGS_VIEW_FILES as $relativePath) {
        $source = file_get_contents(__DIR__.'/../../'.$relativePath);

        expect($source)->not->toBeFalse("{$relativePath} is missing");

        $findings = [...$findings, ...settingsHardcodedUiFindings($relativePath, $source)];
    }

    expect($findings)->toBe([], "Use tokens or a named class in app.css instead:\n".implode("\n", $findings));
});

test('every settings translation key used in the view files exists in lang/pl/ui.php', function () {
    $translations = require __DIR__.'/../../lang/pl/ui.php';
    $missing = [];

    foreach (SETTINGS_VIEW_FILES as $relativePath) {
        $source = file_get_contents(__DIR__.'/../../'.$relativePath);

        preg_match_all('/\bt\.settings\.([A-Za-z0-9_]+)/', (string) $source, $matches);

        foreach (array_unique($matches[1]) as $key) {
            if (! array_key_exists($key, $translations['settings'])) {
                $missing[] = "{$relativePath}: t.settings.{$key}";
            }
        }
    }

    expect($missing)->toBe([], "Add the key to lang/pl/ui.php:\n".implode("\n", $missing));
});

test('the scan catches the destructive tint and the Heading import', function () {
    $kinds = array_map(
        fn (string $finding): string => explode(': ', $finding)[1],
        settingsHardcodedUiFindings('x.vue', "class=\"bg-destructive/5\"\nimport Heading from '@/components/Heading.vue';"),
    );

    expect($kinds)->toBe(['destructive tint', 'Heading import']);
});

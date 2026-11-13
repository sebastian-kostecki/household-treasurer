<?php

use App\Support\Categories\CategoryColors;

/**
 * The category tile (ADR-0095) is `--category-color` at `--category-tile-tint` over the card,
 * with the icon in `--category-color` mixed with the foreground at `--category-icon-tint`.
 * Both mixes are done in sRGB, so the maths here is the maths of the browser (`color-mix(in srgb, ...)`).
 * A missing token falls back to the values from before ADR-0095 (tile 15%, icon 100%),
 * which keeps the test red for the old CSS.
 */
const CATEGORY_TILE_MINIMUM_CONTRAST = 3.0;

/**
 * @return array{light: array<string, string>, dark: array<string, string>}
 */
function categoryTileTokenBlocks(): array
{
    $css = file_get_contents(__DIR__.'/../../resources/css/app.css');

    expect($css)->not->toBeFalse('resources/css/app.css is missing');

    $blocks = [];

    foreach (['light' => ':root', 'dark' => '.dark'] as $theme => $selector) {
        preg_match('/^'.preg_quote($selector, '/').' \{(.*?)^\}/ms', $css, $block);

        expect($block)->not->toBe([], "app.css has no {$selector} block");

        preg_match_all('/^\s*(--[a-z0-9-]+):\s*([^;]+);/m', $block[1], $declarations, PREG_SET_ORDER);

        $blocks[$theme] = [];

        foreach ($declarations as $declaration) {
            $blocks[$theme][$declaration[1]] = trim($declaration[2]);
        }
    }

    return $blocks;
}

/**
 * @return array{0: float, 1: float, 2: float}
 */
function categoryTileRgb(string $hex): array
{
    expect($hex)->toMatch('/^#[0-9a-f]{6}$/i');

    return [
        hexdec(substr($hex, 1, 2)) / 255,
        hexdec(substr($hex, 3, 2)) / 255,
        hexdec(substr($hex, 5, 2)) / 255,
    ];
}

/**
 * @param  array{0: float, 1: float, 2: float}  $first
 * @param  array{0: float, 1: float, 2: float}  $second
 * @return array{0: float, 1: float, 2: float}
 */
function categoryTileMix(array $first, float $firstShare, array $second): array
{
    return [
        $first[0] * $firstShare + $second[0] * (1 - $firstShare),
        $first[1] * $firstShare + $second[1] * (1 - $firstShare),
        $first[2] * $firstShare + $second[2] * (1 - $firstShare),
    ];
}

/**
 * @param  array{0: float, 1: float, 2: float}  $rgb
 */
function categoryTileLuminance(array $rgb): float
{
    [$red, $green, $blue] = array_map(
        fn (float $channel): float => $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4,
        $rgb,
    );

    return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
}

/**
 * @param  array{0: float, 1: float, 2: float}  $first
 * @param  array{0: float, 1: float, 2: float}  $second
 */
function categoryTileContrast(array $first, array $second): float
{
    $lighter = max(categoryTileLuminance($first), categoryTileLuminance($second));
    $darker = min(categoryTileLuminance($first), categoryTileLuminance($second));

    return ($lighter + 0.05) / ($darker + 0.05);
}

/**
 * @param  array<string, string>  $tokens
 */
function categoryTileShare(array $tokens, string $name, float $fallback): float
{
    return isset($tokens[$name]) ? (float) rtrim($tokens[$name], '%') / 100 : $fallback;
}

/**
 * @param  array<string, string>  $tokens
 */
function categoryTileIconContrast(array $tokens, string $color): float
{
    $card = categoryTileRgb($tokens['--card']);
    $foreground = categoryTileRgb($tokens['--foreground']);
    $category = categoryTileRgb($color);

    $tile = categoryTileMix($category, categoryTileShare($tokens, '--category-tile-tint', 0.15), $card);
    $icon = categoryTileMix($category, categoryTileShare($tokens, '--category-icon-tint', 1.0), $foreground);

    return categoryTileContrast($icon, $tile);
}

test('the icon on a category tile has at least 3:1 against the tile for every palette colour in both themes', function () {
    $blocks = categoryTileTokenBlocks();
    $colors = CategoryColors::values();
    $failures = [];
    $pairs = 0;

    foreach ($blocks as $theme => $tokens) {
        foreach ($colors as $color) {
            $pairs++;
            $contrast = categoryTileIconContrast($tokens, $color);

            if ($contrast < CATEGORY_TILE_MINIMUM_CONTRAST) {
                $failures[] = sprintf('%s in the %s theme: %.2f:1', $color, $theme, $contrast);
            }
        }
    }

    expect($pairs)->toBe(48)
        ->and($failures)->toBe([], "Icon below 3:1 on the category tile:\n".implode("\n", $failures));
});

test('the contrast maths matches known WCAG values', function () {
    expect(categoryTileContrast([0.0, 0.0, 0.0], [1.0, 1.0, 1.0]))->toEqualWithDelta(21.0, 0.001);
});

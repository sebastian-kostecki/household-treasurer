<?php

use App\Support\CategoryList\CategoryNameKey;
use App\Support\CategoryList\LegacyCategoryConversion;
use Carbon\CarbonImmutable;

/**
 * @param  array<string, mixed>  $overrides
 * @return array{id: int, budget_id: int, name: string, icon: string, color: string, sort_order: int, type: string, archived_at: string|null}
 */
function legacyRow(int $id, int $budgetId, string $name, array $overrides = []): array
{
    return array_merge([
        'id' => $id,
        'budget_id' => $budgetId,
        'name' => $name,
        'icon' => 'tag',
        'color' => '#868e96',
        'sort_order' => 0,
        'type' => 'expense',
        'archived_at' => null,
    ], $overrides);
}

/**
 * @return list<array{id: int, year: int, phase: string}>
 */
function legacyBudgets(): array
{
    return [
        ['id' => 1, 'year' => 2026, 'phase' => 'execution'],
        ['id' => 2, 'year' => 2027, 'phase' => 'draft'],
    ];
}

/**
 * @param  array{categories: list<array<string, mixed>>, lineCategory: array<int, string>}  $result
 * @return array<string, mixed>
 */
function categoryOfLine(array $result, int $lineId): array
{
    foreach ($result['categories'] as $category) {
        if ($category['key'] === $result['lineCategory'][$lineId]) {
            return $category;
        }
    }

    throw new RuntimeException("No category for line {$lineId}.");
}

function migratedAt(): CarbonImmutable
{
    return CarbonImmutable::parse('2026-11-10 12:00:00');
}

test('LegacyCategoryConversion name key trims Unicode white space and ignores letter case', function () {
    expect(CategoryNameKey::of("  Rozrywka\u{00A0}"))->toBe('rozrywka')
        ->and(CategoryNameKey::of("\tŻYWNOŚĆ\n"))->toBe('żywność')
        ->and(CategoryNameKey::of('Rozrywka'))->toBe(CategoryNameKey::of('ROZRYWKA'));
});

test('LegacyCategoryConversion name key keeps diacritics and inner spaces', function () {
    expect(CategoryNameKey::of('Prad'))->not->toBe(CategoryNameKey::of('Prąd'))
        ->and(CategoryNameKey::of('Opłaty  stałe'))->toBe('opłaty  stałe');
});

test('LegacyCategoryConversion joins the same name in two Budgets into one Category', function () {
    $result = LegacyCategoryConversion::convert(legacyBudgets(), [
        legacyRow(10, 1, 'Rozrywka'),
        legacyRow(20, 2, 'Rozrywka'),
    ], migratedAt());

    expect($result['categories'])->toHaveCount(1)
        ->and($result['lineCategory'][10])->toBe($result['lineCategory'][20])
        ->and($result['categories'][0]['archived_at'])->toBeNull();
});

test('LegacyCategoryConversion numbers a repeated key inside one Budget by ascending id', function () {
    $result = LegacyCategoryConversion::convert(legacyBudgets(), [
        legacyRow(12, 1, 'Rozrywka'),
        legacyRow(10, 1, 'Rozrywka'),
        legacyRow(20, 2, 'Rozrywka'),
        legacyRow(21, 2, 'rozrywka'),
    ], migratedAt());

    expect(categoryOfLine($result, 10)['name'])->toBe('Rozrywka')
        ->and(categoryOfLine($result, 12)['name'])->toBe('rozrywka (2)')
        ->and($result['lineCategory'][20])->toBe($result['lineCategory'][10])
        ->and($result['lineCategory'][21])->toBe($result['lineCategory'][12]);
});

test('LegacyCategoryConversion skips a number held by an original record', function () {
    $result = LegacyCategoryConversion::convert(legacyBudgets(), [
        legacyRow(10, 1, 'Rozrywka'),
        legacyRow(11, 1, 'Rozrywka'),
        legacyRow(12, 1, 'Rozrywka'),
        legacyRow(13, 1, 'Rozrywka (2)'),
    ], migratedAt());

    expect(categoryOfLine($result, 10)['name'])->toBe('Rozrywka')
        ->and(categoryOfLine($result, 13)['name'])->toBe('Rozrywka (2)')
        ->and(categoryOfLine($result, 11)['name'])->toBe('Rozrywka (3)')
        ->and(categoryOfLine($result, 12)['name'])->toBe('Rozrywka (4)');
});

test('LegacyCategoryConversion gives the duplicate the same number in every Budget', function () {
    $result = LegacyCategoryConversion::convert(legacyBudgets(), [
        legacyRow(10, 1, 'Rozrywka'),
        legacyRow(11, 1, 'Rozrywka'),
        legacyRow(20, 2, 'Rozrywka'),
        legacyRow(21, 2, 'Rozrywka'),
        legacyRow(22, 2, 'Rozrywka (2)'),
    ], migratedAt());

    expect($result['lineCategory'][11])->toBe($result['lineCategory'][21])
        ->and(categoryOfLine($result, 11)['name'])->toBe('Rozrywka (3)');
});

test('LegacyCategoryConversion shortens the name so the numbered name fits in 255 characters', function () {
    $name = str_repeat('Ł', 255);

    $result = LegacyCategoryConversion::convert(legacyBudgets(), [
        legacyRow(10, 1, $name),
        legacyRow(11, 1, $name),
    ], migratedAt());

    $numbered = categoryOfLine($result, 11);

    expect(mb_strlen($numbered['name']))->toBe(255)
        ->and($numbered['name'])->toEndWith(' (2)')
        ->and(categoryOfLine($result, 10)['name'])->toBe($name);
});

test('LegacyCategoryConversion takes spelling and appearance from the newest Budget', function () {
    $result = LegacyCategoryConversion::convert(legacyBudgets(), [
        legacyRow(10, 1, ' ZDROWIE ', ['icon' => 'heart', 'color' => '#111111', 'sort_order' => 3]),
        legacyRow(20, 2, 'Zdrowie', ['icon' => 'pill', 'color' => '#222222', 'sort_order' => 7]),
    ], migratedAt());

    expect($result['categories'])->toHaveCount(1)
        ->and($result['categories'][0])->toMatchArray([
            'name' => 'Zdrowie',
            'name_key' => 'zdrowie',
            'icon' => 'pill',
            'color' => '#222222',
            'sort_order' => 7,
        ]);
});

test('LegacyCategoryConversion keeps Prad and Prąd as two Categories', function () {
    $result = LegacyCategoryConversion::convert(legacyBudgets(), [
        legacyRow(10, 1, 'Prad'),
        legacyRow(11, 1, 'Prąd'),
    ], migratedAt());

    expect($result['categories'])->toHaveCount(2);
});

test('LegacyCategoryConversion keeps an income and an expense of one name apart', function () {
    $result = LegacyCategoryConversion::convert(legacyBudgets(), [
        legacyRow(10, 1, 'Zwrot', ['type' => 'income']),
        legacyRow(11, 1, 'Zwrot', ['type' => 'expense']),
    ], migratedAt());

    expect($result['categories'])->toHaveCount(2)
        ->and(categoryOfLine($result, 10)['name'])->toBe('Zwrot')
        ->and(categoryOfLine($result, 11)['name'])->toBe('Zwrot');
});

test('LegacyCategoryConversion archives a Category present only in an older Budget with the migration moment', function () {
    $budgets = [
        ['id' => 1, 'year' => 2025, 'phase' => 'execution'],
        ['id' => 2, 'year' => 2026, 'phase' => 'execution'],
    ];

    $result = LegacyCategoryConversion::convert($budgets, [
        legacyRow(10, 1, 'Rok 2025 tylko'),
        legacyRow(20, 2, 'Aktywna'),
    ], migratedAt());

    $archived = categoryOfLine($result, 10);

    expect($archived['archived_at']?->equalTo(migratedAt()))->toBeTrue()
        ->and(categoryOfLine($result, 20)['archived_at'])->toBeNull();
});

test('LegacyCategoryConversion archives with the latest archived_at of the Budget Lines', function () {
    $result = LegacyCategoryConversion::convert(legacyBudgets(), [
        legacyRow(10, 1, 'Wakacje', ['archived_at' => '2026-09-09 10:00:00']),
    ], migratedAt());

    expect(categoryOfLine($result, 10)['archived_at']?->format('Y-m-d H:i:s'))->toBe('2026-09-09 10:00:00');
});

test('LegacyCategoryConversion is active with a non-archived line in the newest Budget even when it is a draft', function () {
    $result = LegacyCategoryConversion::convert(legacyBudgets(), [
        legacyRow(10, 1, 'Pensja', ['archived_at' => '2026-03-01 00:00:00']),
        legacyRow(20, 2, 'Pensja'),
    ], migratedAt());

    expect($result['categories'][0]['archived_at'])->toBeNull();
});

test('LegacyCategoryConversion is active with a non-archived line in the newest Budget in execution', function () {
    $result = LegacyCategoryConversion::convert(legacyBudgets(), [
        legacyRow(10, 1, 'Mieszkanie'),
    ], migratedAt());

    expect($result['categories'][0]['archived_at'])->toBeNull();
});

test('LegacyCategoryConversion archives a Category whose only non-archived line is in an old execution Budget', function () {
    $budgets = [
        ['id' => 1, 'year' => 2025, 'phase' => 'execution'],
        ['id' => 2, 'year' => 2026, 'phase' => 'execution'],
    ];

    $result = LegacyCategoryConversion::convert($budgets, [
        legacyRow(10, 1, 'Stara'),
        legacyRow(20, 2, 'Nowa'),
    ], migratedAt());

    expect(categoryOfLine($result, 10)['archived_at'])->not->toBeNull()
        ->and(categoryOfLine($result, 20)['archived_at'])->toBeNull();
});

test('LegacyCategoryConversion rejects a record that points at an unknown Budget', function () {
    LegacyCategoryConversion::convert(legacyBudgets(), [legacyRow(10, 99, 'Duch')], migratedAt());
})->throws(InvalidArgumentException::class);

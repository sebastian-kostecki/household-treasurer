<?php

use App\Enums\CategoryType;
use App\Enums\Rhythm;
use App\Support\CollectedCalculator;
use App\Support\ToSpendCalculator;

/**
 * Cases shared with resources/js/lib/to-spend-calculator.parity.test.ts. The expected
 * values are derived by hand from the glossary (To Spend, Irregular Rate, Collected).
 *
 * @return array<string, mixed>
 */
function parityCalculatorCases(): array
{
    /** @var array<string, mixed> */
    return json_decode(
        (string) file_get_contents(__DIR__.'/../Fixtures/amount-invariants/to-spend-calculator-cases.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
}

/**
 * @param  array<string, mixed>  $item
 * @return array{type: CategoryType, rhythm: ?Rhythm, plan: int, actual: int, has_positive_amount: bool, collected_at_start: int}
 */
function parityToSpendItem(array $item): array
{
    return [
        'type' => CategoryType::from($item['type']),
        'rhythm' => $item['rhythm'] === null ? null : Rhythm::from($item['rhythm']),
        'plan' => $item['plan'],
        'actual' => $item['actual'],
        'has_positive_amount' => $item['has_positive_amount'],
        'collected_at_start' => $item['collected_at_start'],
    ];
}

/**
 * @return array<string, array{array<string, mixed>}>
 */
function parityDataset(string $section): array
{
    $rows = [];

    foreach (parityCalculatorCases()[$section] as $case) {
        $rows[$case['name']] = [$case];
    }

    return $rows;
}

dataset('parity item cases', fn () => parityDataset('items'));
dataset('parity breakdown cases', fn () => parityDataset('breakdowns'));
dataset('parity chain cases', fn () => parityDataset('chains'));

test('the parity fixture is in format 1', function () {
    expect(parityCalculatorCases()['format'])->toBe(1);
});

test('item amount matches the hand-computed table', function (array $case) {
    $item = parityToSpendItem($case);

    expect(ToSpendCalculator::itemMinorUnits(
        $item['type'],
        $item['rhythm'],
        $item['plan'],
        $item['actual'],
        $item['has_positive_amount'],
        $item['collected_at_start'],
    ))->toBe($case['expected']);
})->with('parity item cases');

test('breakdown matches the hand-computed table', function (array $case) {
    $items = array_map(parityToSpendItem(...), $case['items']);

    expect(ToSpendCalculator::breakdown($items, $case['set_aside']))->toBe([
        'income_minor_units' => $case['expected']['income'],
        'expense_minor_units' => $case['expected']['expense'],
        'to_spend_minor_units' => $case['expected']['to_spend'],
    ]);
})->with('parity breakdown cases');

test('collected chain matches the hand-computed table', function (array $case) {
    expect(CollectedCalculator::chain($case['opening'], $case['months']))->toBe($case['expected']);
})->with('parity chain cases');

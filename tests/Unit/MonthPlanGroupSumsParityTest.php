<?php

use App\Support\MonthPlanGroupSums;

/**
 * Cases shared with resources/js/lib/month-plan-groups.parity.test.ts. The expected values are
 * counted by hand from ADR-0079 (the example Mieszkanie: rent 2 500 / 2 500, electricity rate
 * 150 with a bill of 600 gives Plan 2 650, Actual 3 100, over plan).
 *
 * @return array{format: int, cases: list<array<string, mixed>>}
 */
function monthPlanGroupSumsFixture(): array
{
    /** @var array{format: int, cases: list<array<string, mixed>>} */
    return json_decode(
        (string) file_get_contents(__DIR__.'/../Fixtures/month-plan-groups/month-plan-group-sums-cases.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
}

/**
 * @return array<string, array{array<string, mixed>}>
 */
function monthPlanGroupSumsDataset(): array
{
    $cases = [];

    foreach (monthPlanGroupSumsFixture()['cases'] as $case) {
        $cases[$case['name']] = [$case];
    }

    return $cases;
}

dataset('month plan group sums cases', fn () => monthPlanGroupSumsDataset());

test('the parity fixture is in format 1 with cases', function () {
    $fixture = monthPlanGroupSumsFixture();

    expect($fixture['format'])->toBe(1)
        ->and($fixture['cases'])->not->toBeEmpty();
});

test('group sums match the hand-counted table', function (array $case) {
    $actual = array_map(fn (array $group): array => [
        'group_id' => $group['id'],
        'category_count' => $group['category_count'],
        'plan_minor_units' => $group['plan_minor_units'],
        'actual_minor_units' => $group['actual_minor_units'],
        'over_plan' => $group['over_plan'],
        'category_ids' => $group['category_ids'],
    ], MonthPlanGroupSums::forRows($case['groups'], $case['rows']));

    expect($actual)->toBe($case['expected']);
})->with('month plan group sums cases');

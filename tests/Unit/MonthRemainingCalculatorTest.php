<?php

use App\Enums\Rhythm;
use App\Support\MonthRemainingCalculator;
use Carbon\CarbonImmutable;

test('draft splits paced current plan into twelfths with december remainder', function () {
    $calculator = new MonthRemainingCalculator(
        isDraft: true,
        budgetYear: 2026,
        today: CarbonImmutable::parse('2026-06-15'),
    );

    expect($calculator->poolCategoryMonthRemainingMinorUnits(
        currentPlanMinorUnits: 120_000,
        executionBeforeViewedMonth: 0,
        executionInViewedMonth: 0,
        viewedMonth: 3,
        executionBeforeCurrentMonth: 0,
        executionInCurrentMonth: 0,
    ))->toBe(10_000);

    expect($calculator->poolCategoryMonthRemainingMinorUnits(
        currentPlanMinorUnits: 120_000,
        executionBeforeViewedMonth: 0,
        executionInViewedMonth: 0,
        viewedMonth: 12,
        executionBeforeCurrentMonth: 0,
        executionInCurrentMonth: 0,
    ))->toBe(10_000);
});

test('execution catch up subtracts paced execution in the viewed month', function () {
    $calculator = new MonthRemainingCalculator(
        isDraft: false,
        budgetYear: 2026,
        today: CarbonImmutable::parse('2026-06-15'),
    );

    expect($calculator->poolCategoryMonthRemainingMinorUnits(
        currentPlanMinorUnits: 120_000,
        executionBeforeViewedMonth: 25_000,
        executionInViewedMonth: 10_000,
        viewedMonth: 3,
        executionBeforeCurrentMonth: 0,
        executionInCurrentMonth: 0,
    ))->toBe(-500);
});

test('execution floors leftover toward negative infinity', function () {
    $calculator = new MonthRemainingCalculator(
        isDraft: false,
        budgetYear: 2026,
        today: CarbonImmutable::parse('2026-06-15'),
    );

    expect($calculator->poolCategoryMonthRemainingMinorUnits(
        currentPlanMinorUnits: 0,
        executionBeforeViewedMonth: 7,
        executionInViewedMonth: 0,
        viewedMonth: 1,
        executionBeforeCurrentMonth: 0,
        executionInCurrentMonth: 0,
    ))->toBe(-1);
});

test('execution december uses months left of one', function () {
    $calculator = new MonthRemainingCalculator(
        isDraft: false,
        budgetYear: 2026,
        today: CarbonImmutable::parse('2026-12-20'),
    );

    expect($calculator->poolCategoryMonthRemainingMinorUnits(
        currentPlanMinorUnits: 120_000,
        executionBeforeViewedMonth: 110_000,
        executionInViewedMonth: 5_000,
        viewedMonth: 12,
        executionBeforeCurrentMonth: 110_000,
        executionInCurrentMonth: 5_000,
    ))->toBe(5_000);
});

test('execution future month shows current month plaster', function () {
    $calculator = new MonthRemainingCalculator(
        isDraft: false,
        budgetYear: 2026,
        today: CarbonImmutable::parse('2026-03-15'),
    );

    expect($calculator->poolCategoryMonthRemainingMinorUnits(
        currentPlanMinorUnits: 120_000,
        executionBeforeViewedMonth: 0,
        executionInViewedMonth: 0,
        viewedMonth: 8,
        executionBeforeCurrentMonth: 20_000,
        executionInCurrentMonth: 2_000,
    ))->toBe(8_000);
});

test('execution future budget year uses even plaster without catch up', function () {
    $calculator = new MonthRemainingCalculator(
        isDraft: false,
        budgetYear: 2027,
        today: CarbonImmutable::parse('2026-06-15'),
    );

    expect($calculator->poolCategoryMonthRemainingMinorUnits(
        currentPlanMinorUnits: 120_000,
        executionBeforeViewedMonth: 0,
        executionInViewedMonth: 0,
        viewedMonth: 5,
        executionBeforeCurrentMonth: 0,
        executionInCurrentMonth: 0,
    ))->toBe(10_000);
});

test('paced pool floors summed leftover once', function () {
    $calculator = new MonthRemainingCalculator(
        isDraft: false,
        budgetYear: 2026,
        today: CarbonImmutable::parse('2026-06-15'),
    );

    $categoryA = $calculator->poolCategoryMonthRemainingMinorUnits(
        currentPlanMinorUnits: 50_001,
        executionBeforeViewedMonth: 0,
        executionInViewedMonth: 0,
        viewedMonth: 1,
        executionBeforeCurrentMonth: 0,
        executionInCurrentMonth: 0,
    );

    $categoryB = $calculator->poolCategoryMonthRemainingMinorUnits(
        currentPlanMinorUnits: 50_001,
        executionBeforeViewedMonth: 0,
        executionInViewedMonth: 0,
        viewedMonth: 1,
        executionBeforeCurrentMonth: 0,
        executionInCurrentMonth: 0,
    );

    $pool = $calculator->poolMonthRemainingMinorUnits(
        totalCurrentPlanMinorUnits: 100_002,
        totalExecutionBeforeViewedMonth: 0,
        totalExecutionInViewedMonth: 0,
        viewedMonth: 1,
        totalExecutionBeforeCurrentMonth: 0,
        totalExecutionInCurrentMonth: 0,
        hasPoolCategories: true,
    );

    expect($categoryA + $categoryB)->not->toBe($pool);
    expect($pool)->toBe(8_333);
});

test('empty paced pool month remaining is zero', function () {
    $calculator = new MonthRemainingCalculator(
        isDraft: false,
        budgetYear: 2026,
        today: CarbonImmutable::parse('2026-06-15'),
    );

    expect($calculator->poolMonthRemainingMinorUnits(
        totalCurrentPlanMinorUnits: 0,
        totalExecutionBeforeViewedMonth: 0,
        totalExecutionInViewedMonth: 0,
        viewedMonth: 6,
        totalExecutionBeforeCurrentMonth: 0,
        totalExecutionInCurrentMonth: 0,
        hasPoolCategories: false,
    ))->toBe(0);
});

test('the pool holds fixed charges and running categories, not even annual ones', function () {
    expect(MonthRemainingCalculator::isPoolRhythm(Rhythm::FixedCharge))->toBeTrue()
        ->and(MonthRemainingCalculator::isPoolRhythm(Rhythm::Running))->toBeTrue()
        ->and(MonthRemainingCalculator::isPoolRhythm(Rhythm::EvenAnnual))->toBeFalse()
        ->and(MonthRemainingCalculator::isPoolRhythm(Rhythm::Irregular))->toBeFalse()
        ->and(MonthRemainingCalculator::isPoolRhythm(null))->toBeFalse();
});

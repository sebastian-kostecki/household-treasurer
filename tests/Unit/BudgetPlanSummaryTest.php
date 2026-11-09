<?php

use App\Enums\BudgetPhase;
use App\Enums\CategoryType;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Support\BudgetPlanSummary;

test('empty type totals use zeros and null percent complete', function () {
    expect(BudgetPlanSummary::emptyTypeTotals())->toBe([
        'initial_plan_minor_units' => 0,
        'current_plan_minor_units' => 0,
        'execution_minor_units' => 0,
        'remaining_minor_units' => 0,
        'percent_complete' => null,
    ]);
});

test('empty collection returns empty type totals', function () {
    $budget = new Budget(['phase' => BudgetPhase::Execution]);

    $summary = BudgetPlanSummary::forBudget($budget, collect());

    expect($summary['income'])->toBe(BudgetPlanSummary::emptyTypeTotals())
        ->and($summary['expense'])->toBe(BudgetPlanSummary::emptyTypeTotals())
        ->and($summary['planned_balance_minor_units'])->toBe(0);
});

test('for type sums initial plan current plan execution remaining and percent', function () {
    $budget = new Budget(['phase' => BudgetPhase::Execution]);
    $categories = collect([
        new BudgetLine([
            'type' => CategoryType::Income,
            'planned_amount_minor_units' => 500000,
            'initial_plan_minor_units' => 400000,
            'execution_minor_units' => 250000,
        ]),
        new BudgetLine([
            'type' => CategoryType::Income,
            'planned_amount_minor_units' => 100000,
            'initial_plan_minor_units' => 100000,
            'execution_minor_units' => 50000,
        ]),
    ]);

    expect(BudgetPlanSummary::forType($budget, $categories, CategoryType::Income))->toBe([
        'initial_plan_minor_units' => 500000,
        'current_plan_minor_units' => 600000,
        'execution_minor_units' => 300000,
        'remaining_minor_units' => 300000,
        'percent_complete' => 50,
    ]);
});

test('draft budget uses current plan as initial plan sum', function () {
    $budget = new Budget(['phase' => BudgetPhase::Draft]);
    $categories = collect([
        new BudgetLine([
            'type' => CategoryType::Income,
            'planned_amount_minor_units' => 300000,
            'initial_plan_minor_units' => 0,
            'execution_minor_units' => 0,
        ]),
        new BudgetLine([
            'type' => CategoryType::Expense,
            'planned_amount_minor_units' => 200000,
            'initial_plan_minor_units' => 0,
            'execution_minor_units' => 0,
        ]),
    ]);

    $summary = BudgetPlanSummary::forBudget($budget, $categories);

    expect($summary['income']['initial_plan_minor_units'])->toBe(300000)
        ->and($summary['expense']['initial_plan_minor_units'])->toBe(200000)
        ->and($summary['planned_balance_minor_units'])->toBe(100000);
});

test('percent complete is null when section current plan is zero', function () {
    $budget = new Budget(['phase' => BudgetPhase::Execution]);
    $categories = collect([
        new BudgetLine([
            'type' => CategoryType::Expense,
            'planned_amount_minor_units' => 0,
            'initial_plan_minor_units' => 0,
            'execution_minor_units' => 5000,
        ]),
    ]);

    expect(BudgetPlanSummary::forType($budget, $categories, CategoryType::Expense)['percent_complete'])->toBeNull();
});

test('planned balance is income current plan minus expense current plan', function () {
    $budget = new Budget(['phase' => BudgetPhase::Execution]);
    $categories = collect([
        new BudgetLine([
            'type' => CategoryType::Income,
            'planned_amount_minor_units' => 400000,
            'initial_plan_minor_units' => 400000,
            'execution_minor_units' => 0,
        ]),
        new BudgetLine([
            'type' => CategoryType::Expense,
            'planned_amount_minor_units' => 550000,
            'initial_plan_minor_units' => 500000,
            'execution_minor_units' => 0,
        ]),
    ]);

    expect(BudgetPlanSummary::forBudget($budget, $categories)['planned_balance_minor_units'])->toBe(-150000);
});

test('for type ignores categories of the other type', function () {
    $budget = new Budget(['phase' => BudgetPhase::Execution]);
    $categories = collect([
        new BudgetLine([
            'type' => CategoryType::Income,
            'planned_amount_minor_units' => 400000,
            'initial_plan_minor_units' => 400000,
            'execution_minor_units' => 100000,
        ]),
        new BudgetLine([
            'type' => CategoryType::Expense,
            'planned_amount_minor_units' => 250000,
            'initial_plan_minor_units' => 250000,
            'execution_minor_units' => 50000,
        ]),
    ]);

    expect(BudgetPlanSummary::forType($budget, $categories, CategoryType::Income)['current_plan_minor_units'])->toBe(400000)
        ->and(BudgetPlanSummary::forType($budget, $categories, CategoryType::Expense)['current_plan_minor_units'])->toBe(250000);
});

test('collection sums include every category passed in', function () {
    $budget = new Budget(['phase' => BudgetPhase::Execution]);
    $categories = collect([
        new BudgetLine([
            'type' => CategoryType::Expense,
            'planned_amount_minor_units' => 50000,
            'initial_plan_minor_units' => 50000,
            'execution_minor_units' => 10000,
        ]),
        new BudgetLine([
            'type' => CategoryType::Expense,
            'planned_amount_minor_units' => 30000,
            'initial_plan_minor_units' => 30000,
            'execution_minor_units' => 5000,
        ]),
    ]);

    expect(BudgetPlanSummary::forType($budget, $categories, CategoryType::Expense)['current_plan_minor_units'])->toBe(80000);
});

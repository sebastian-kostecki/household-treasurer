<?php

use App\Enums\BudgetPhase;
use App\Enums\CategoryType;
use App\Models\Budget;
use App\Models\Category;
use App\Support\BudgetPlanSummary;

test('empty type totals use zeros and null percent complete', function () {
    expect(BudgetPlanSummary::emptyTypeTotals())->toBe([
        'initial_plan_grosze' => 0,
        'current_plan_grosze' => 0,
        'execution_grosze' => 0,
        'remaining_grosze' => 0,
        'percent_complete' => null,
    ]);
});

test('empty collection returns empty type totals', function () {
    $budget = new Budget(['phase' => BudgetPhase::Execution]);

    $summary = BudgetPlanSummary::forBudget($budget, collect());

    expect($summary['income'])->toBe(BudgetPlanSummary::emptyTypeTotals())
        ->and($summary['expense'])->toBe(BudgetPlanSummary::emptyTypeTotals())
        ->and($summary['planned_balance_grosze'])->toBe(0);
});

test('for type sums initial plan current plan execution remaining and percent', function () {
    $budget = new Budget(['phase' => BudgetPhase::Execution]);
    $categories = collect([
        new Category([
            'type' => CategoryType::Income,
            'planned_amount_grosze' => 500000,
            'initial_plan_grosze' => 400000,
            'execution_grosze' => 250000,
        ]),
        new Category([
            'type' => CategoryType::Income,
            'planned_amount_grosze' => 100000,
            'initial_plan_grosze' => 100000,
            'execution_grosze' => 50000,
        ]),
    ]);

    expect(BudgetPlanSummary::forType($budget, $categories, CategoryType::Income))->toBe([
        'initial_plan_grosze' => 500000,
        'current_plan_grosze' => 600000,
        'execution_grosze' => 300000,
        'remaining_grosze' => 300000,
        'percent_complete' => 50,
    ]);
});

test('draft budget uses current plan as initial plan sum', function () {
    $budget = new Budget(['phase' => BudgetPhase::Draft]);
    $categories = collect([
        new Category([
            'type' => CategoryType::Income,
            'planned_amount_grosze' => 300000,
            'initial_plan_grosze' => 0,
            'execution_grosze' => 0,
        ]),
        new Category([
            'type' => CategoryType::Expense,
            'planned_amount_grosze' => 200000,
            'initial_plan_grosze' => 0,
            'execution_grosze' => 0,
        ]),
    ]);

    $summary = BudgetPlanSummary::forBudget($budget, $categories);

    expect($summary['income']['initial_plan_grosze'])->toBe(300000)
        ->and($summary['expense']['initial_plan_grosze'])->toBe(200000)
        ->and($summary['planned_balance_grosze'])->toBe(100000);
});

test('percent complete is null when section current plan is zero', function () {
    $budget = new Budget(['phase' => BudgetPhase::Execution]);
    $categories = collect([
        new Category([
            'type' => CategoryType::Expense,
            'planned_amount_grosze' => 0,
            'initial_plan_grosze' => 0,
            'execution_grosze' => 5000,
        ]),
    ]);

    expect(BudgetPlanSummary::forType($budget, $categories, CategoryType::Expense)['percent_complete'])->toBeNull();
});

test('planned balance is income current plan minus expense current plan', function () {
    $budget = new Budget(['phase' => BudgetPhase::Execution]);
    $categories = collect([
        new Category([
            'type' => CategoryType::Income,
            'planned_amount_grosze' => 400000,
            'initial_plan_grosze' => 400000,
            'execution_grosze' => 0,
        ]),
        new Category([
            'type' => CategoryType::Expense,
            'planned_amount_grosze' => 550000,
            'initial_plan_grosze' => 500000,
            'execution_grosze' => 0,
        ]),
    ]);

    expect(BudgetPlanSummary::forBudget($budget, $categories)['planned_balance_grosze'])->toBe(-150000);
});

test('for type ignores categories of the other type', function () {
    $budget = new Budget(['phase' => BudgetPhase::Execution]);
    $categories = collect([
        new Category([
            'type' => CategoryType::Income,
            'planned_amount_grosze' => 400000,
            'initial_plan_grosze' => 400000,
            'execution_grosze' => 100000,
        ]),
        new Category([
            'type' => CategoryType::Expense,
            'planned_amount_grosze' => 250000,
            'initial_plan_grosze' => 250000,
            'execution_grosze' => 50000,
        ]),
    ]);

    expect(BudgetPlanSummary::forType($budget, $categories, CategoryType::Income)['current_plan_grosze'])->toBe(400000)
        ->and(BudgetPlanSummary::forType($budget, $categories, CategoryType::Expense)['current_plan_grosze'])->toBe(250000);
});

test('collection sums include every category passed in', function () {
    $budget = new Budget(['phase' => BudgetPhase::Execution]);
    $categories = collect([
        new Category([
            'type' => CategoryType::Expense,
            'planned_amount_grosze' => 50000,
            'initial_plan_grosze' => 50000,
            'execution_grosze' => 10000,
        ]),
        new Category([
            'type' => CategoryType::Expense,
            'planned_amount_grosze' => 30000,
            'initial_plan_grosze' => 30000,
            'execution_grosze' => 5000,
        ]),
    ]);

    expect(BudgetPlanSummary::forType($budget, $categories, CategoryType::Expense)['current_plan_grosze'])->toBe(80000);
});

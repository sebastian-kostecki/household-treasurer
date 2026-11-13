<?php

use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\FinancialOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * ADR-0089: an operation of a Sheet Account changes only its Category; the
 * amount moves between the lines of the loaded Budget in Execution, Current
 * Plan and Initial Plan together.
 *
 * @return array{user: User, sheet: Account, budget: Budget, from: BudgetLine, to: BudgetLine, operation: FinancialOperation}
 */
function sheetExpenseSetup(): array
{
    $user = User::factory()->create();
    $sheet = Account::factory()->for($user)->sheetAccount(2025)->create();
    $budget = Budget::factory()->for($user)->execution()->create(['year' => 2025]);
    $from = BudgetLine::factory()->for($budget)->expense()->create([
        'initial_plan_minor_units' => 30000,
        'planned_amount_minor_units' => 30000,
        'execution_minor_units' => 30000,
    ]);
    $to = BudgetLine::factory()->for($budget)->expense()->create([
        'initial_plan_minor_units' => 10000,
        'planned_amount_minor_units' => 10000,
        'execution_minor_units' => 10000,
    ]);
    $operation = FinancialOperation::factory()->expense()->create([
        'user_id' => $user->id,
        'account_id' => $sheet->id,
        'budget_id' => $budget->id,
        'budget_line_id' => $from->id,
        'operation_date' => '2025-03-31',
        'amount_minor_units' => 5000,
        'description' => 'Artykuły spożywcze',
    ]);

    return compact('user', 'sheet', 'budget', 'from', 'to', 'operation');
}

/**
 * @return array{0: int, 1: int, 2: int}
 */
function linePlanAndExecution(BudgetLine $line): array
{
    $line->refresh();

    return [$line->initial_plan_minor_units, $line->planned_amount_minor_units, $line->execution_minor_units];
}

it('moves a sheet expense to another Category with its plan and execution', function (): void {
    ['user' => $user, 'sheet' => $sheet, 'from' => $from, 'to' => $to, 'operation' => $operation] = sheetExpenseSetup();

    $this->actingAs($user)
        ->patch(route('operations.category.update', $operation), ['category_id' => $to->category_id])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('operations'));

    $operation->refresh();

    expect($operation->budget_line_id)->toBe($to->id)
        ->and($operation->amount_minor_units)->toBe(5000)
        ->and($operation->operation_date->toDateString())->toBe('2025-03-31')
        ->and($operation->account_id)->toBe($sheet->id)
        ->and(linePlanAndExecution($from))->toBe([25000, 25000, 25000])
        ->and(linePlanAndExecution($to))->toBe([15000, 15000, 15000])
        ->and($sheet->refresh()->current_balance_minor_units)->toBe(0);
});

it('moves a sheet income to another income Category', function (): void {
    ['user' => $user, 'sheet' => $sheet, 'budget' => $budget] = sheetExpenseSetup();
    $from = BudgetLine::factory()->for($budget)->income()->create([
        'initial_plan_minor_units' => 800000,
        'planned_amount_minor_units' => 800000,
        'execution_minor_units' => 800000,
    ]);
    $to = BudgetLine::factory()->for($budget)->income()->create([
        'initial_plan_minor_units' => 0,
        'planned_amount_minor_units' => 0,
        'execution_minor_units' => 0,
    ]);
    $operation = FinancialOperation::factory()->income()->create([
        'user_id' => $user->id,
        'account_id' => $sheet->id,
        'budget_id' => $budget->id,
        'budget_line_id' => $from->id,
        'operation_date' => '2025-06-30',
        'amount_minor_units' => 200000,
    ]);

    $this->actingAs($user)
        ->patch(route('operations.category.update', $operation), ['category_id' => $to->category_id])
        ->assertSessionHasNoErrors();

    expect($operation->refresh()->budget_line_id)->toBe($to->id)
        ->and(linePlanAndExecution($from))->toBe([600000, 600000, 600000])
        ->and(linePlanAndExecution($to))->toBe([200000, 200000, 200000]);
});

it('joins a Category without a line in the loaded Budget and moves the amount there', function (): void {
    ['user' => $user, 'budget' => $budget, 'from' => $from, 'operation' => $operation] = sheetExpenseSetup();
    $category = Category::factory()->for($user)->expense()->create();

    $this->actingAs($user)
        ->patch(route('operations.category.update', $operation), ['category_id' => $category->id])
        ->assertSessionHasNoErrors();

    $joined = BudgetLine::query()->where('budget_id', $budget->id)->where('category_id', $category->id)->sole();

    expect($operation->refresh()->budget_line_id)->toBe($joined->id)
        ->and(linePlanAndExecution($joined))->toBe([5000, 5000, 5000])
        ->and(linePlanAndExecution($from))->toBe([25000, 25000, 25000]);
});

it('refuses a Category of the other type or an archived Category', function (string $case): void {
    ['user' => $user, 'budget' => $budget, 'from' => $from, 'operation' => $operation] = sheetExpenseSetup();
    $category = match ($case) {
        'income' => BudgetLine::factory()->for($budget)->income()->create()->category,
        'archived' => tap(BudgetLine::factory()->for($budget)->expense()->create()->category)
            ->update(['archived_at' => now()]),
    };

    $this->actingAs($user)
        ->patch(route('operations.category.update', $operation), ['category_id' => $category->id])
        ->assertSessionHasErrors(['category_id' => __('The selected category is invalid.')]);

    expect($operation->refresh()->budget_line_id)->toBe($from->id)
        ->and(linePlanAndExecution($from))->toBe([30000, 30000, 30000]);
})->with(['income', 'archived']);

it('lets the owner change the Category only of a sheet operation', function (): void {
    ['user' => $user, 'budget' => $budget, 'from' => $from, 'to' => $to, 'operation' => $sheetOperation] = sheetExpenseSetup();
    $realAccount = Account::factory()->for($user)->create(['opening_date' => '2025-01-01']);
    $realOperation = FinancialOperation::factory()->expense()->create([
        'user_id' => $user->id,
        'account_id' => $realAccount->id,
        'budget_id' => $budget->id,
        'budget_line_id' => $from->id,
        'operation_date' => '2025-03-31',
    ]);
    $stranger = User::factory()->create();

    expect($user->can('changeCategory', $sheetOperation))->toBeTrue()
        ->and($user->can('update', $sheetOperation))->toBeFalse()
        ->and($user->can('changeCategory', $realOperation))->toBeFalse()
        ->and($stranger->can('changeCategory', $sheetOperation))->toBeFalse();

    $this->actingAs($user)
        ->patch(route('operations.category.update', $realOperation), ['category_id' => $to->category_id])
        ->assertForbidden();

    $this->actingAs($stranger)
        ->patch(route('operations.category.update', $sheetOperation), ['category_id' => $to->category_id])
        ->assertForbidden();

    expect($realOperation->refresh()->budget_line_id)->toBe($from->id)
        ->and($sheetOperation->refresh()->budget_line_id)->toBe($from->id);
});

it('marks sheet operations on the Operations page', function (): void {
    ['user' => $user, 'budget' => $budget, 'from' => $from] = sheetExpenseSetup();
    $realAccount = Account::factory()->for($user)->create(['opening_date' => '2025-01-01']);
    FinancialOperation::factory()->expense()->create([
        'user_id' => $user->id,
        'account_id' => $realAccount->id,
        'budget_id' => $budget->id,
        'budget_line_id' => $from->id,
        'operation_date' => '2025-04-01',
    ]);

    $this->actingAs($user)
        ->get(route('operations'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Operations')
            ->has('operations.data', 2)
            ->where('operations.data.0.sheet_operation', false)
            ->where('operations.data.1.sheet_operation', true),
        );
});

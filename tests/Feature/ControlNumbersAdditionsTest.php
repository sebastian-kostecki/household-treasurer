<?php

use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\FinancialOperation;
use App\Models\User;
use App\Support\ControlNumberAdditions;
use App\Support\ControlNumbers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

/*
 * `app:control-numbers --compare=<file> --allow-additions` is the proof of the legacy
 * budget import (ADR-0085): the import adds rows and changes no existing value.
 */
uses(RefreshDatabase::class);

afterEach(function (): void {
    File::deleteDirectory(storage_path('framework/testing/control-numbers-additions'));
});

/**
 * A household before the import, as a document: one Budget with a line, one real
 * Account, one Category.
 *
 * @return array<string, mixed>
 */
function additionsBefore(): array
{
    return [
        'meta' => ['version' => ControlNumbers::VERSION],
        'categories' => [
            1 => ['type' => 'expense', 'name_key' => 'rozrywka', 'archived' => false, 'group' => null, 'rhythm_entries' => 1],
        ],
        'row_counts' => ['accounts' => 1, 'budget_lines' => 1, 'budgets' => 1, 'financial_operations' => 1, 'month_plans' => 0],
        'max_financial_operation_id' => 10,
        'budgets' => [1 => ['operations' => 500, 'initial_plan' => 900, 'planned' => 900, 'execution' => 500]],
        'lines' => [1 => ['budget_id' => 1, 'initial_plan' => 900, 'planned' => 900, 'execution' => 500, 'operations' => 500]],
        'own_transfers_total' => 0,
        'capital_movements_by_direction' => [],
        'accounts' => [1 => ['opening' => 1_000, 'current' => 1_500, 'archived' => false, 'sheet_year' => null]],
        'term_deposits' => [],
    ];
}

/**
 * The same household after an import of 2025: a new Budget with a line and operations,
 * an archived Sheet Account, an archived Category.
 *
 * @return array<string, mixed>
 */
function additionsAfterImport(): array
{
    $after = additionsBefore();
    $after['categories'][2] = ['type' => 'expense', 'name_key' => 'kosmetyki', 'archived' => true, 'group' => null, 'rhythm_entries' => 1];
    $after['row_counts'] = ['accounts' => 2, 'budget_lines' => 3, 'budgets' => 2, 'financial_operations' => 41, 'month_plans' => 0];
    $after['max_financial_operation_id'] = 50;
    $after['budgets'][2] = ['operations' => 7_000, 'initial_plan' => 7_000, 'planned' => 7_000, 'execution' => 7_000];
    $after['lines'][2] = ['budget_id' => 2, 'initial_plan' => 4_000, 'planned' => 4_000, 'execution' => 4_000, 'operations' => 4_000];
    $after['lines'][3] = ['budget_id' => 2, 'initial_plan' => 3_000, 'planned' => 3_000, 'execution' => 3_000, 'operations' => 3_000];
    $after['accounts'][2] = ['opening' => -300, 'current' => 0, 'archived' => true, 'sheet_year' => 2025];

    return $after;
}

test('an import that only adds a Budget with lines, a Sheet Account and a Category has no violations', function () {
    $additions = new ControlNumberAdditions(new ControlNumbers);

    expect($additions->violations(additionsBefore(), additionsAfterImport()))->toBe([])
        ->and($additions->summary(additionsBefore(), additionsAfterImport()))->toBe([
            'budgets' => 1,
            'lines' => 2,
            'accounts' => 1,
            'categories' => 1,
            'financial_operations' => 40,
        ]);
});

test('every change to existing data or an addition outside the allowed kinds is a violation', function (string $expected, Closure $change) {
    $after = additionsAfterImport();
    $change($after);

    $violations = (new ControlNumberAdditions(new ControlNumbers))->violations(additionsBefore(), $after);

    expect(implode("\n", $violations))->toContain($expected);
})->with([
    'an existing line amount changes' => ['lines.1.planned', function (array &$after): void {
        $after['lines'][1]['planned'] = 901;
    }],
    'a line is added to an existing Budget, even with plan 0' => ['lines.4: a line added to the existing Budget 1', function (array &$after): void {
        $after['lines'][4] = ['budget_id' => 1, 'initial_plan' => 0, 'planned' => 0, 'execution' => 0, 'operations' => 0];
    }],
    'a new active Category' => ['categories.2: a new Category must be archived', function (array &$after): void {
        $after['categories'][2]['archived'] = false;
    }],
    'an existing Category changes' => ['categories.1.archived', function (array &$after): void {
        $after['categories'][1]['archived'] = true;
    }],
    'an existing Category gets another Rhythm entry' => ['categories.1.rhythm_entries', function (array &$after): void {
        $after['categories'][1]['rhythm_entries'] = 2;
    }],
    'a new Account with a balance other than 0' => ['accounts.2: a new Account must be an archived Sheet Account', function (array &$after): void {
        $after['accounts'][2]['current'] = 10;
    }],
    'a new active Account' => ['accounts.2: a new Account must be an archived Sheet Account', function (array &$after): void {
        $after['accounts'][2]['archived'] = false;
    }],
    'a new Account without a sheet year' => ['accounts.2: a new Account must be an archived Sheet Account', function (array &$after): void {
        $after['accounts'][2]['sheet_year'] = null;
    }],
    'the balance of a real Account moves' => ['accounts.1.current', function (array &$after): void {
        $after['accounts'][1]['current'] = 1_400;
    }],
    'month plans grow' => ['row_counts.month_plans', function (array &$after): void {
        $after['row_counts']['month_plans'] = 1;
    }],
    'operations of an existing Budget grow' => ['budgets.1.operations', function (array &$after): void {
        $after['budgets'][1]['operations'] = 600;
    }],
    'a row count shrinks' => ['row_counts.financial_operations', function (array &$after): void {
        $after['row_counts']['financial_operations'] = 0;
    }],
    'an existing Budget disappears' => ['budgets.1', function (array &$after): void {
        unset($after['budgets'][1]);
    }],
]);

test('a document without the Category section (the old shape) is compared on the rest only', function () {
    $before = additionsBefore();
    $before['categories'] = null;

    expect((new ControlNumberAdditions(new ControlNumbers))->violations($before, additionsAfterImport()))->toBe([]);
});

test('the control numbers report the archive flag and sheet year of every account and every list category', function () {
    $user = User::factory()->create();
    $real = Account::factory()->for($user)->create();
    $sheet = Account::factory()->for($user)->create(['archived_at' => now(), 'sheet_year' => 2025]);
    $category = BudgetLine::factory()->for(Budget::factory()->for($user)->create())->expense()->create()->category;

    $document = (new ControlNumbers)->collect();

    expect($document['accounts'][$real->id])->toMatchArray(['archived' => false, 'sheet_year' => null])
        ->and($document['accounts'][$sheet->id])->toMatchArray(['archived' => true, 'sheet_year' => 2025])
        ->and($document['categories'][$category->id])->toBe([
            'type' => $category->type->value,
            'name_key' => $category->name_key,
            'archived' => false,
            'group' => $category->category_group_id,
            'rhythm_entries' => $category->rhythms()->count(),
        ]);
});

test('the command accepts an additions-only change and fails on a changed amount', function () {
    $user = User::factory()->create();
    $budget = Budget::factory()->for($user)->create(['year' => 2026]);
    $line = BudgetLine::factory()->for($budget)->expense()->create(['execution_minor_units' => 7_500]);
    $account = Account::factory()->for($user)->create();
    FinancialOperation::factory()->expense()->create([
        'user_id' => $user->id, 'account_id' => $account->id, 'budget_id' => $budget->id,
        'budget_line_id' => $line->id, 'amount_minor_units' => 7_500,
    ]);

    $path = storage_path('framework/testing/control-numbers-additions/before.json');
    $this->artisan('app:control-numbers', ['--output' => $path])->assertExitCode(0);

    $sheetAccount = Account::factory()->for($user)->create(['archived_at' => now(), 'sheet_year' => 2025, 'opening_balance_minor_units' => -100, 'current_balance_minor_units' => 0]);
    $sheetBudget = Budget::factory()->for($user)->create(['year' => 2025]);
    $sheetLine = BudgetLine::factory()->for($sheetBudget)->expense()->create(['execution_minor_units' => 100]);
    // The factory made a new Category with the line; the import only adds archived ones.
    $sheetLine->category->update(['archived_at' => now()]);
    FinancialOperation::factory()->expense()->create([
        'user_id' => $user->id, 'account_id' => $sheetAccount->id, 'budget_id' => $sheetBudget->id,
        'budget_line_id' => $sheetLine->id, 'amount_minor_units' => 100,
    ]);

    $this->artisan('app:control-numbers', ['--compare' => $path, '--allow-additions' => true])
        ->expectsOutputToContain('Only additions')
        ->assertExitCode(0);

    // Without the flag the same change is a difference (the deploy behaviour is unchanged).
    $this->artisan('app:control-numbers', ['--compare' => $path])->assertExitCode(1);

    $line->increment('execution_minor_units');

    $this->artisan('app:control-numbers', ['--compare' => $path, '--allow-additions' => true])
        ->expectsOutputToContain('lines.'.$line->id.'.execution')
        ->assertExitCode(1);
});

test('--allow-additions without --compare fails', function () {
    $this->artisan('app:control-numbers', ['--allow-additions' => true])->assertExitCode(1);
});

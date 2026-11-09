<?php

use App\Enums\BudgetPhase;
use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\FinancialOperation;
use App\Models\User;
use App\Support\CsvImport\ImportCategorySuggester;
use App\Support\CsvImport\ImportRowFields;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function suggesterRecordHistory(
    User $user,
    BudgetLine $category,
    string $description,
    ?string $rawStatementDescription = 'RAW',
    FinancialOperationType $type = FinancialOperationType::Expense,
    int $amountMinorUnits = 1_000,
): void {
    FinancialOperation::factory()->for($user)->create([
        'type' => $type,
        'account_id' => Account::factory()->for($user),
        'budget_line_id' => $category->id,
        'budget_id' => $category->budget_id,
        'operation_date' => '2025-05-10',
        'amount_minor_units' => $amountMinorUnits,
        'description' => $description,
        'raw_statement_description' => $rawStatementDescription,
    ]);
}

function suggesterRow(string $description, string $date = '2026-03-15', bool $valid = true): array
{
    return [
        'index' => 0,
        'operation_date' => $date,
        'description' => $description,
        'counterparty' => null,
        'raw_statement_description' => 'RAW',
        'amount_minor_units' => -1_000,
        'valid' => $valid,
        'invalid_reason' => null,
    ];
}

function suggesterFor(User $user, array $row): array
{
    return app(ImportCategorySuggester::class)->annotateRows($user, [$row])[0]['category_suggestion'];
}

function suggesterBudgets(User $user): array
{
    return [
        Budget::factory()->for($user)->execution()->create(['year' => 2025]),
        Budget::factory()->for($user)->execution()->create(['year' => 2026]),
    ];
}

test('history from the 2025 budget maps to the category of the same name and type in 2026', function () {
    $user = User::factory()->create();
    [$budget2025, $budget2026] = suggesterBudgets($user);
    $old = BudgetLine::factory()->for($budget2025)->expense()->create(['name' => 'Zakupy']);
    $current = BudgetLine::factory()->for($budget2026)->expense()->create(['name' => 'Zakupy']);
    suggesterRecordHistory($user, $old, 'BIEDRONKA WARSZAWA');

    expect(suggesterFor($user, suggesterRow('BIEDRONKA 4521')))
        ->toBe(['income' => null, 'expense' => $current->id]);
});

test('two categories with the same name in the target budget give null', function () {
    $user = User::factory()->create();
    [$budget2025, $budget2026] = suggesterBudgets($user);
    $old = BudgetLine::factory()->for($budget2025)->expense()->create(['name' => 'Zakupy']);
    BudgetLine::factory()->for($budget2026)->expense()->create(['name' => 'Zakupy']);
    BudgetLine::factory()->for($budget2026)->expense()->create(['name' => 'Zakupy']);
    suggesterRecordHistory($user, $old, 'BIEDRONKA WARSZAWA');

    expect(suggesterFor($user, suggesterRow('BIEDRONKA')))->toBe(['income' => null, 'expense' => null]);
});

test('an archived target category gives null', function () {
    $user = User::factory()->create();
    [$budget2025, $budget2026] = suggesterBudgets($user);
    $old = BudgetLine::factory()->for($budget2025)->expense()->create(['name' => 'Zakupy']);
    BudgetLine::factory()->for($budget2026)->expense()->archived()->create(['name' => 'Zakupy']);
    suggesterRecordHistory($user, $old, 'BIEDRONKA WARSZAWA');

    expect(suggesterFor($user, suggesterRow('BIEDRONKA')))->toBe(['income' => null, 'expense' => null]);
});

test('a draft budget or a missing budget in the row year gives null', function () {
    $user = User::factory()->create();
    [$budget2025, $budget2026] = suggesterBudgets($user);
    $old = BudgetLine::factory()->for($budget2025)->expense()->create(['name' => 'Zakupy']);
    BudgetLine::factory()->for($budget2026)->expense()->create(['name' => 'Zakupy']);
    suggesterRecordHistory($user, $old, 'BIEDRONKA WARSZAWA');

    $budget2026->update(['phase' => BudgetPhase::Draft]);

    expect(suggesterFor($user, suggesterRow('BIEDRONKA')))->toBe(['income' => null, 'expense' => null])
        ->and(suggesterFor($user, suggesterRow('BIEDRONKA', '2027-01-05')))->toBe(['income' => null, 'expense' => null]);
});

test('history of another user is ignored', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    [, $budget2026] = suggesterBudgets($user);
    BudgetLine::factory()->for($budget2026)->expense()->create(['name' => 'Zakupy']);
    [$otherBudget2025] = suggesterBudgets($other);
    $otherCategory = BudgetLine::factory()->for($otherBudget2025)->expense()->create(['name' => 'Zakupy']);
    suggesterRecordHistory($other, $otherCategory, 'BIEDRONKA WARSZAWA');

    expect(suggesterFor($user, suggesterRow('BIEDRONKA')))->toBe(['income' => null, 'expense' => null]);
});

test('manual entries and saved operations without a description are not history', function () {
    $user = User::factory()->create();
    [$budget2025, $budget2026] = suggesterBudgets($user);
    $old = BudgetLine::factory()->for($budget2025)->expense()->create(['name' => 'Zakupy']);
    BudgetLine::factory()->for($budget2026)->expense()->create(['name' => 'Zakupy']);
    suggesterRecordHistory($user, $old, 'BIEDRONKA WARSZAWA', null);
    suggesterRecordHistory($user, $old, ImportRowFields::EMPTY_DESCRIPTION_FALLBACK);

    expect(suggesterFor($user, suggesterRow('BIEDRONKA')))->toBe(['income' => null, 'expense' => null])
        ->and(suggesterFor($user, suggesterRow(ImportRowFields::EMPTY_DESCRIPTION_FALLBACK)))
        ->toBe(['income' => null, 'expense' => null]);
});

test('a refund saved as an expense teaches the expense suggestion', function () {
    $user = User::factory()->create();
    [$budget2025, $budget2026] = suggesterBudgets($user);
    $old = BudgetLine::factory()->for($budget2025)->expense()->create(['name' => 'Zakupy']);
    $current = BudgetLine::factory()->for($budget2026)->expense()->create(['name' => 'Zakupy']);
    suggesterRecordHistory($user, $old, 'ZWROT ALLEGRO', amountMinorUnits: -2_500);

    expect(suggesterFor($user, suggesterRow('ZWROT ALLEGRO')))
        ->toBe(['income' => null, 'expense' => $current->id]);
});

test('invalid rows and rows without a description get both suggestions null', function () {
    $user = User::factory()->create();
    [$budget2025, $budget2026] = suggesterBudgets($user);
    $old = BudgetLine::factory()->for($budget2025)->expense()->create(['name' => 'Zakupy']);
    BudgetLine::factory()->for($budget2026)->expense()->create(['name' => 'Zakupy']);
    suggesterRecordHistory($user, $old, 'BIEDRONKA WARSZAWA');

    expect(suggesterFor($user, suggesterRow('BIEDRONKA', valid: false)))->toBe(['income' => null, 'expense' => null])
        ->and(suggesterFor($user, suggesterRow(ImportRowFields::EMPTY_DESCRIPTION_FALLBACK)))
        ->toBe(['income' => null, 'expense' => null]);
});

test('income and expense are computed independently in one row', function () {
    $user = User::factory()->create();
    [$budget2025, $budget2026] = suggesterBudgets($user);
    $oldExpense = BudgetLine::factory()->for($budget2025)->expense()->create(['name' => 'Zakupy']);
    $oldIncome = BudgetLine::factory()->for($budget2025)->income()->create(['name' => 'Sprzedaż']);
    $currentExpense = BudgetLine::factory()->for($budget2026)->expense()->create(['name' => 'Zakupy']);
    $currentIncome = BudgetLine::factory()->for($budget2026)->income()->create(['name' => 'Sprzedaż']);
    suggesterRecordHistory($user, $oldExpense, 'ALLEGRO PACZKA');
    suggesterRecordHistory($user, $oldIncome, 'ALLEGRO PACZKA', type: FinancialOperationType::Income);

    expect(suggesterFor($user, suggesterRow('ALLEGRO PACZKA')))
        ->toBe(['income' => $currentIncome->id, 'expense' => $currentExpense->id]);
});

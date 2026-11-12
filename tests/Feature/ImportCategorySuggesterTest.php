<?php

use App\Actions\MergeCategories;
use App\Enums\BudgetPhase;
use App\Enums\CategoryType;
use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\FinancialOperation;
use App\Models\User;
use App\Support\CategoryList\CategoryChoiceOptions;
use App\Support\CsvImport\ImportCategorySuggester;
use App\Support\CsvImport\ImportRowFields;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

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
        ->toBe(['income' => null, 'expense' => $current->category_id]);
});

test('an active winner without a line in the budget of the row year gives its id', function () {
    $user = User::factory()->create();
    [$budget2025] = suggesterBudgets($user);
    $winner = BudgetLine::factory()->for($budget2025)->expense()->create(['name' => 'Zakupy']);
    suggesterRecordHistory($user, $winner, 'BIEDRONKA WARSZAWA');

    expect(suggesterFor($user, suggesterRow('BIEDRONKA WARSZAWA')))
        ->toBe(['income' => null, 'expense' => $winner->category_id]);
});

test('a winner archived on the list gives null and does not fall through to an active candidate with a line', function () {
    $user = User::factory()->create();
    [$budget2025, $budget2026] = suggesterBudgets($user);
    $winner = BudgetLine::factory()->for($budget2025)->expense()->create(['name' => 'Zakupy']);
    $other = BudgetLine::factory()->for($budget2025)->expense()->create(['name' => 'Paliwo']);
    BudgetLine::factory()->for($budget2026)->expense()->create(['category_id' => $other->category_id]);
    suggesterRecordHistory($user, $winner, 'BIEDRONKA WARSZAWA');
    suggesterRecordHistory($user, $other, 'BIEDRONKA');
    $winner->category->update(['archived_at' => now()]);

    expect(suggesterFor($user, suggesterRow('BIEDRONKA WARSZAWA')))->toBe(['income' => null, 'expense' => null]);
});

test('the history of a merged source category votes for the target category', function () {
    Storage::fake('local');
    $this->travelTo('2026-11-10');
    $user = User::factory()->create();
    [$budget2025, $budget2026] = suggesterBudgets($user);
    $source = BudgetLine::factory()->for($budget2025)->expense()->create(['name' => 'Jedzenie poza domem']);
    $target = BudgetLine::factory()->for($budget2026)->expense()->create(['name' => 'Restauracje']);
    suggesterRecordHistory($user, $source, 'PIZZERIA ROMA');

    app(MergeCategories::class)->handle($source->category, $target->category, CarbonImmutable::create(2026, 11, 10));

    expect(suggesterFor($user, suggesterRow('PIZZERIA ROMA')))
        ->toBe(['income' => null, 'expense' => $target->category_id]);
});

test('the suggestion targets are exactly the categories the selector offers in the row year', function () {
    $user = User::factory()->create();
    [$budget2025, $budget2026] = suggesterBudgets($user);
    $descriptions = ['withLine' => 'ALFA SKLEP', 'withoutLine' => 'BRAVO SKLEP', 'lineArchived' => 'CHARLIE SKLEP', 'listArchived' => 'DELTA SKLEP'];
    $categories = [];

    foreach ($descriptions as $key => $description) {
        $line2025 = BudgetLine::factory()->for($budget2025)->expense()->create(['name' => $key]);
        suggesterRecordHistory($user, $line2025, $description);
        $categories[$key] = $line2025->category_id;
    }

    BudgetLine::factory()->for($budget2026)->expense()->create(['category_id' => $categories['withLine']]);
    BudgetLine::factory()->for($budget2026)->expense()->archived()->create(['category_id' => $categories['lineArchived']]);
    BudgetLine::factory()->for($budget2026)->expense()->create(['category_id' => $categories['listArchived']]);
    $user->categories()->whereKey($categories['listArchived'])->update(['archived_at' => now()]);

    $offered = collect(CategoryChoiceOptions::forType($user, CategoryType::Expense))
        ->filter(fn (array $option): bool => $option['budget_year'] === 2026)
        ->map(fn (array $option): int => $option['category']->id)
        ->sort()
        ->values()
        ->all();
    $suggested = collect($descriptions)
        ->map(fn (string $description): ?int => suggesterFor($user, suggesterRow($description))['expense'])
        ->filter()
        ->sort()
        ->values()
        ->all();

    expect($offered)->toBe([$categories['withLine'], $categories['withoutLine']])
        ->and($suggested)->toBe($offered);
});

test('a winner archived on the list gives null even when its line in the row year is active', function () {
    $user = User::factory()->create();
    [$budget2025, $budget2026] = suggesterBudgets($user);
    $old = BudgetLine::factory()->for($budget2025)->expense()->create(['name' => 'Zakupy']);
    BudgetLine::factory()->for($budget2026)->expense()->create(['category_id' => $old->category_id]);
    suggesterRecordHistory($user, $old, 'BIEDRONKA WARSZAWA');
    $old->category->update(['archived_at' => now()]);

    expect(suggesterFor($user, suggesterRow('BIEDRONKA')))->toBe(['income' => null, 'expense' => null]);
});

test('without name variants the vote by list category gives what a vote by name gave', function () {
    $user = User::factory()->create();
    [$budget2025, $budget2026] = suggesterBudgets($user);
    $names = ['Zakupy' => 'BIEDRONKA WARSZAWA', 'Paliwo' => 'ORLEN STACJA', 'Rozrywka' => 'KINO HELIOS', 'Bez pozycji' => 'ZOO LAND'];
    $lines2026 = [];
    $withoutLine = null;

    foreach ($names as $name => $description) {
        $line2025 = BudgetLine::factory()->for($budget2025)->expense()->create(['name' => $name]);
        suggesterRecordHistory($user, $line2025, $description);

        if ($name === 'Bez pozycji') {
            $withoutLine = $line2025;
        } else {
            $lines2026[$name] = BudgetLine::factory()->for($budget2026)->expense()->create(['name' => $name]);
        }
    }

    // The vote per exact name, then the Category of that name in the row year; an active Category without a line is a target too (ADR-0063).
    $byName = fn (string $description): ?int => match (true) {
        str_contains($description, 'BIEDRONKA') => $lines2026['Zakupy']->category_id,
        str_contains($description, 'ORLEN') => $lines2026['Paliwo']->category_id,
        str_contains($description, 'KINO') => $lines2026['Rozrywka']->category_id,
        str_contains($description, 'ZOO') => $withoutLine->category_id,
        default => null,
    };

    foreach (['BIEDRONKA 4521', 'ORLEN 12', 'KINO HELIOS', 'ZOO LAND', 'NIEZNANY SKLEP'] as $description) {
        expect(suggesterFor($user, suggesterRow($description))['expense'])->toBe($byName($description));
    }
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
        ->toBe(['income' => null, 'expense' => $current->category_id]);
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
        ->toBe(['income' => $currentIncome->category_id, 'expense' => $currentExpense->category_id]);
});

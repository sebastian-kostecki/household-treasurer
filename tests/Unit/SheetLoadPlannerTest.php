<?php

use App\Enums\FinancialOperationType;
use App\Support\SheetLoad\BudgetSheetReader;
use App\Support\SheetLoad\PlannedOperation;
use App\Support\SheetLoad\SheetLoadException;
use App\Support\SheetLoad\SheetLoadPlan;
use App\Support\SheetLoad\SheetLoadPlanner;
use App\Support\SheetLoad\SheetMapping;
use Tests\Support\SheetWorkbookFactory;

/**
 * @param  array<string, mixed>  $options
 */
function planSheet(array $options = [], bool $withOverrides = false): SheetLoadPlan
{
    $plan = null;

    SheetWorkbookFactory::with($options, function (string $path) use (&$plan, $withOverrides): void {
        $data = require __DIR__.'/../../database/sheet-load/mapping.php';

        if (! $withOverrides) {
            $data['overrides'] = [];
        }

        $mapping = new SheetMapping($data);
        $plan = (new SheetLoadPlanner($mapping))->plan((new BudgetSheetReader)->read($path));
    });

    return $plan;
}

/**
 * @param  array<string, mixed>  $options
 */
function planFailure(array $options, bool $withOverrides = false): SheetLoadException
{
    $failure = null;

    try {
        planSheet($options, $withOverrides);
    } catch (SheetLoadException $e) {
        $failure = $e;
    }

    expect($failure)->toBeInstanceOf(SheetLoadException::class);

    return $failure;
}

/**
 * @return list<PlannedOperation>
 */
function operationsOf(SheetLoadPlan $plan, string $date, int|string|null $category = null): array
{
    return array_values(array_filter(
        $plan->operations,
        fn (PlannedOperation $op): bool => $op->date === $date && ($category === null || $op->category === $category),
    ));
}

/**
 * @param  array<int, float>  $janaByMonth
 * @return array<int, array<string, mixed>>
 */
function janaIncomeMonths(array $janaByMonth): array
{
    $months = [];

    foreach ($janaByMonth as $month => $jana) {
        $months[$month] = ['income' => ['Praca: Sebastian' => 4000.00, 'work: Jana' => $jana, 'oszczędności' => 0.0]];
    }

    return $months;
}

test('totals equal Analiza to the grosz, dates are month ends and the savings rows are skipped', function () {
    $plan = planSheet(['year' => 2024]);

    expect($plan->year)->toBe(2024)
        ->and($plan->incomeTotal())->toBe($plan->analysisIncome->amountMinorUnits)
        ->and($plan->expenseTotal())->toBe($plan->analysisExpense->amountMinorUnits)
        ->and($plan->openingBalanceMinorUnits())->toBe($plan->expenseTotal() - $plan->incomeTotal())
        ->and(operationsOf($plan, '2024-02-29'))->not->toBeEmpty()
        ->and(operationsOf($plan, '2024-01-31'))->not->toBeEmpty()
        ->and(count($plan->operations))->toBe(12 * 15)
        ->and(collect($plan->operations)->pluck('description')->all())->toContain('Prąd (arkusz 2024)')
        ->and($plan->categoryTotals())->not->toHaveKey('skip');
});

test('a tax refund override becomes its own operation and is taken off the cell', function () {
    $months = [2 => ['income' => ['Praca: Sebastian' => 4000.00, 'work: Jana' => 3000.00, 'pozostałe' => 7000.00, 'oszczędności' => 0.0]]];
    $plan = planSheet(['year' => 2023, 'months' => $months], true);

    $refund = operationsOf($plan, '2023-02-28', 6);
    $rest = operationsOf($plan, '2023-02-28', 7);

    expect($refund)->toHaveCount(1)
        ->and($refund[0]->amountMinorUnits)->toBe(683_107)
        ->and($refund[0]->description)->toBe('Zwrot podatku')
        ->and($refund[0]->type)->toBe(FinancialOperationType::Income)
        ->and($rest)->toHaveCount(1)
        ->and($rest[0]->amountMinorUnits)->toBe(700_000 - 683_107);
});

test('refuses an override above the cell amount and an override without a cell', function () {
    $above = planFailure(['year' => 2023, 'months' => [2 => ['income' => ['Praca: Sebastian' => 4000.00, 'pozostałe' => 100.00, 'oszczędności' => 0.0]]]], true);
    $missing = planFailure(['year' => 2023, 'months' => [2 => ['income' => ['Praca: Sebastian' => 4000.00, 'oszczędności' => 0.0]]]], true);

    expect($above->getMessage())->toContain('przekracza')
        ->and($above->address)->toBe('luty!I4')
        ->and($missing->getMessage())->toContain('nie pasuje');
});

test('a negative income cell moves to the nearest later month with a warning', function () {
    $plan = planSheet(['months' => janaIncomeMonths([3 => -293.68] + array_fill(1, 12, 3000.00))]);

    expect(operationsOf($plan, '2025-03-31', 2))->toBe([])
        ->and(operationsOf($plan, '2025-04-30', 2)[0]->amountMinorUnits)->toBe(300_000 - 29_368)
        ->and($plan->transfers)->toHaveCount(1)
        ->and($plan->transfers[0]->address)->toBe('marzec!I4')
        ->and((string) $plan->transfers[0])->toContain('kwiecień!I4')
        ->and(collect($plan->operations)->where('type', FinancialOperationType::Income)->min('amountMinorUnits'))->toBeGreaterThanOrEqual(1);
});

test('a negative income cell moves back when no later month can take it', function () {
    $plan = planSheet(['months' => janaIncomeMonths([11 => -2000.00, 12 => 500.00] + array_fill(1, 10, 3000.00))]);

    expect(operationsOf($plan, '2025-11-30', 2))->toBe([])
        ->and(operationsOf($plan, '2025-12-31', 2)[0]->amountMinorUnits)->toBe(50_000)
        ->and(operationsOf($plan, '2025-10-31', 2)[0]->amountMinorUnits)->toBe(100_000)
        ->and((string) $plan->transfers[0])->toContain('październik!I4');
});

test('refuses an income source whose yearly sum is not positive', function () {
    $failure = planFailure(['months' => janaIncomeMonths([1 => -100.00] + array_fill(2, 11, 0.0))]);

    expect($failure->getMessage())->toContain('roczna suma');
});

test('a block total above its list gives one remainder operation and a warning in three blocks', function () {
    $plan = planSheet(['months' => [1 => [
        'expenses' => ['Leczenie prywatne' => 230.00, 'Badania' => 66.00],
        'irregularTotal' => 424.37,
    ]]]);

    $irregular = operationsOf($plan, '2025-01-31', 10);
    $medical = operationsOf($plan, '2025-01-31', 20);
    $remainders = collect($plan->operations)->filter(fn ($op) => str_starts_with($op->description, 'reszta bez rozpisania'));

    expect($irregular)->toHaveCount(2)
        ->and($remainders->pluck('amountMinorUnits')->sort()->values()->all())->toBe([1_600, 3_000, 12_437])
        ->and($remainders->pluck('description')->all())->toContain('reszta bez rozpisania: nieregularne (arkusz 2025, styczeń)')
        ->and($medical)->toHaveCount(5)
        ->and($plan->remainders)->toHaveCount(3)
        ->and($plan->warnings())->not->toBeEmpty();
});

test('2022 splits Codzienne wydatki by the breakdown and files the rest to groceries', function () {
    $plan = planSheet(['layout' => '2022', 'year' => 2022, 'months' => [1 => [
        'expenses' => ['Codzienne wydatki' => 1200.00],
    ]]]);

    $january = operationsOf($plan, '2022-01-31');
    $byCategory = fn (int $category): int => array_sum(array_map(
        fn (PlannedOperation $op): int => $op->amountMinorUnits,
        array_filter($january, fn (PlannedOperation $op): bool => $op->category === $category && $op->type === FinancialOperationType::Expense),
    ));

    // Higiena i kosmetyki go to Chemia i kosmetyki (42) since the list of 2026-11-13 (ADR-0096, decision 6).
    expect($byCategory(42))->toBe(15_000)
        ->and($byCategory(29))->toBe(0)
        ->and($byCategory(32))->toBe(5_000)
        ->and($byCategory(8))->toBe(100_000)
        ->and(collect($january)->where('description', 'Jedzenie (arkusz 2022)')->first()->amountMinorUnits)->toBe(60_000)
        ->and(collect($january)->where('description', 'reszta bez rozpisania: codzienne wydatki (arkusz 2022, styczeń)')->first()->amountMinorUnits)->toBe(30_000)
        ->and($plan->expenseTotal())->toBe($plan->analysisExpense->amountMinorUnits);
});

test('a dash or empty irregular category goes to Pozostałe, kosmetyki to Chemia i kosmetyki, nameless items are named', function () {
    $plan = planSheet(['months' => [1 => [
        'expenses' => ['Kosmetyczka' => 40.00],
        'irregular' => [['-', 'Pierwsza', 10.00], ['', 'Druga', 20.00], ['kosmetyki', 'Krem', 15.00], ['Ubrania', null, 30.00], ['Dom', 'Zwrot', -5.00]],
    ]]]);

    $january = operationsOf($plan, '2025-01-31');
    $descriptions = collect($january)->pluck('category', 'description');

    expect($descriptions['Pierwsza'])->toBe(10)
        ->and($descriptions['Druga'])->toBe(10)
        ->and($descriptions['Krem'])->toBe(42)
        ->and($descriptions['Kosmetyczka (arkusz 2025)'])->toBe(42)
        ->and($descriptions['bez nazwy (arkusz 2025)'])->toBe(27)
        ->and(collect($january)->firstWhere('description', 'Zwrot')->amountMinorUnits)->toBe(-500)
        ->and($plan->categoryTotals()[42])->toBe(5_500)
        ->and($plan->categoryTotals())->not->toHaveKey(SheetMapping::NEW_CATEGORY);
});

test('refuses an unmapped expense label, income label and irregular category, naming the cell', function () {
    $expense = planFailure(['months' => [1 => ['expenses' => ['Nowa pozycja' => 10.00]]]]);
    $income = planFailure(['months' => [1 => ['income' => ['Praca: Sebastian' => 1.00, 'loteria' => 5.00, 'oszczędności' => 0.0]]]]);
    $category = planFailure(['months' => [1 => ['irregular' => [['Kosmos', 'Rakieta', 1.00]]]]]);

    expect($expense->address)->toBe('styczeń!D3')
        ->and($expense->getMessage())->toContain('nie ma wpisu w mapowaniu')
        ->and($income->address)->toBe('styczeń!I4')
        ->and($category->address)->toBe('styczeń!M4');
});

test('refuses when the totals differ from Analiza', function () {
    $income = planFailure(['analysis' => [1.00, 1.00]]);

    expect($income->address)->toBe('Analiza!B16')
        ->and($income->getMessage())->toContain('różni się od „Analizy”');
});

test('fund movements make no operation and count towards Analiza for income and expense', function () {
    $plan = planSheet(['layout' => '2020', 'year' => 2020]);
    $descriptions = collect($plan->operations)->pluck('description')->implode('|');

    expect($plan->fundMovements)->toHaveCount(12 * 4)
        ->and($plan->fundExpenseTotal())->toBe(12 * 17_000)
        ->and($plan->fundIncomeTotal())->toBe(12 * 15_000)
        ->and($plan->incomeTotal() + $plan->fundIncomeTotal())->toBe($plan->analysisIncome->amountMinorUnits)
        ->and($plan->expenseTotal() + $plan->fundExpenseTotal())->toBe($plan->analysisExpense->amountMinorUnits)
        ->and($descriptions)->not->toContain('Fundusz')->not->toContain('Rezerwa')->not->toContain('Nadpłata')->not->toContain('rozłożone')
        ->and($plan->openingBalanceMinorUnits())->toBe($plan->expenseTotal() - $plan->incomeTotal())
        ->and($plan->fundMovements[0]->label)->toBe('Fundusz Wydatków Celowych*');
});

test('refuses when operations and fund movements differ from Analiza by one grosz, naming both parts', function () {
    $plan = planSheet(['layout' => '2020', 'year' => 2020]);
    $expense = $plan->analysisExpense->amountMinorUnits + 1;

    $failure = planFailure(['layout' => '2020', 'year' => 2020, 'analysis' => [$plan->analysisIncome->amountMinorUnits / 100, $expense / 100]]);

    expect($failure->address)->toBe('Analiza!C16')
        ->and($failure->getMessage())->toContain('suma operacji (wydatki)')
        ->and($failure->getMessage())->toContain('ruchy funduszu 2040,00 zł')
        ->and($failure->getMessage())->toContain('różni się od „Analizy”');
});

test('a Lekarz - dentysta row becomes the items of its treatment list and a difference gives a remainder', function () {
    $plan = planSheet(['layout' => '2020', 'year' => 2020, 'months' => [1 => ['expenses' => [
        'Rata kredytu' => 1800.50, 'Lekarz - dentysta' => 250.00, 'Badania' => 50.00,
    ]]]]);

    $descriptions = collect(operationsOf($plan, '2020-01-31', 20))->pluck('amountMinorUnits', 'description');

    expect($descriptions['Wizyta'])->toBe(12_000)
        ->and($descriptions['Leki'])->toBe(8_000)
        ->and($descriptions['Morfologia'])->toBe(5_000)
        ->and($descriptions['reszta bez rozpisania: lekarz - dentysta (arkusz 2020, styczeń)'])->toBe(5_000)
        ->and($descriptions->keys()->implode('|'))->not->toContain('Lekarz - dentysta (arkusz 2020)')
        ->and($plan->remainders)->toHaveCount(1)
        ->and($plan->remainders[0]->address)->toBe('styczeń!D4');
});

test('the real mapping maps every label of the 2020-2021 fixture and uses FUND only for expenses and income', function () {
    $mapping = require __DIR__.'/../../database/sheet-load/mapping.php';
    $plan = planSheet(['layout' => '2020', 'year' => 2020]);

    foreach (['daily_breakdown', 'irregular_categories', 'remainders'] as $section) {
        expect(array_values($mapping[$section]))->not->toContain(SheetMapping::FUND);
    }

    expect($plan->operations)->not->toBeEmpty()
        ->and($mapping['expenses']['fundusz wydatków celowych'])->toBe(SheetMapping::FUND)
        ->and($mapping['expenses']['fundusz wydatków celowych (oszcz.)'])->toBe(SheetMapping::FUND)
        ->and($mapping['expenses']['fundusz wydatków nieregularnych'])->toBe(10)
        ->and($mapping['income']['fundusz wydatków nieregularnych'])->toBe(SheetMapping::FUND)
        ->and($mapping['income']['przychody rozłożone'])->toBe(SheetMapping::FUND)
        ->and($mapping['expenses']['lekarz - dentysta'])->toBe(20)
        ->and($mapping['expenses']['lekarz (dentysta)'])->toBe(20);
});

test('refuses a fund target on an irregular category', function () {
    $data = require __DIR__.'/../../database/sheet-load/mapping.php';
    $data['irregular_categories']['ubrania'] = SheetMapping::FUND;
    $failure = null;

    SheetWorkbookFactory::with(['layout' => '2020', 'year' => 2020], function (string $path) use ($data, &$failure): void {
        try {
            (new SheetLoadPlanner(new SheetMapping($data)))->plan((new BudgetSheetReader)->read($path));
        } catch (SheetLoadException $e) {
            $failure = $e;
        }
    });

    expect($failure)->toBeInstanceOf(SheetLoadException::class)
        ->and($failure->getMessage())->toContain('ruch funduszu');
});

test('a negative międzyokresowe cell moves into the premie cell of the same month', function () {
    $plan = planSheet(['layout' => '2020', 'year' => 2020, 'months' => [12 => ['income' => [
        'Praca: Sebastian' => 4000.00, 'work: Jana' => 3000.00, '500+' => 800.00, 'odsetki' => 5.00,
        'pozostałe (premie, 13)' => 100.00, 'międzyokresowe' => -18.87, 'przychody rozłożone' => 150.00, 'oszczędności' => 300.00,
    ]]]]);

    $premie = collect($plan->operations)->firstWhere('description', 'pozostałe (premie, 13) (arkusz 2020)');

    expect($premie->category)->toBe(1)
        ->and($premie->amountMinorUnits)->toBe(8_113)
        ->and($plan->transfers)->toHaveCount(1)
        ->and($plan->transfers[0]->address)->toBe('grudzień!H8')
        ->and((string) $plan->transfers[0])->toContain('grudzień!H7')
        ->and(collect($plan->operations)->where('description', 'międzyokresowe (arkusz 2020)')->all())->toBe([]);
});

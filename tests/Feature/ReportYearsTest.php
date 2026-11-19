<?php

use App\Actions\ManageFinancialOperation;
use App\Actions\MergeCategories;
use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\CapitalMovement;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\FinancialOperation;
use App\Models\OwnTransfer;
use App\Models\User;
use App\Support\PeriodReport;
use App\Support\ReportComparisonPeriod;
use App\Support\ReportYears;
use App\Support\ReportYearsRange;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: Account}
 */
function yearsUser(): array
{
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create([
        'opening_date' => '2019-01-01',
        'opening_balance_minor_units' => 1_000_000_000,
        'current_balance_minor_units' => 1_000_000_000,
    ]);

    return [$user, $account];
}

/**
 * @param  list<int>  $years
 * @return array<int, Budget>
 */
function yearsBudgets(User $user, array $years): array
{
    $budgets = [];
    foreach ($years as $year) {
        $budgets[$year] = Budget::factory()->for($user)->execution()->create(['year' => $year]);
    }

    return $budgets;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function yearsCategory(User $user, string $name, string $type = 'expense', array $attributes = []): Category
{
    return Category::factory()->for($user)->{$type}()->create(['name' => $name] + $attributes);
}

function yearsLine(Budget $budget, Category $category, bool $archived = false): BudgetLine
{
    $factory = BudgetLine::factory()->for($budget);

    return ($archived ? $factory->archived() : $factory)->create(['category_id' => $category->id]);
}

/**
 * Lines of the category in every given budget.
 *
 * @param  array<int, Budget>  $budgets
 * @return array<int, BudgetLine> year => line
 */
function yearsLines(array $budgets, Category $category): array
{
    return array_map(fn (Budget $budget): BudgetLine => yearsLine($budget, $category), $budgets);
}

function yearsOperation(User $user, Account $account, BudgetLine $line, string $date, int $amount): FinancialOperation
{
    return FinancialOperation::factory()->for($user)->create([
        'type' => $line->category->type->value === 'income' ? FinancialOperationType::Income : FinancialOperationType::Expense,
        'account_id' => $account->id,
        'budget_line_id' => $line->id,
        'budget_id' => $line->budget_id,
        'operation_date' => $date,
        'amount_minor_units' => $amount,
    ]);
}

function yearsBook(User $user, Account $account, BudgetLine $line, string $date, int $amount): void
{
    $payload = [
        'operation_date' => $date,
        'amount_minor_units' => $amount,
        'description' => 'Test',
        'account_id' => $account->id,
        'category_id' => $line->category_id,
    ];
    $action = app(ManageFinancialOperation::class);

    $line->category->type->value === 'income'
        ? $action->createIncome($user, $payload)
        : $action->createExpense($user, $payload);
}

/**
 * @return array<string, mixed>
 */
function yearsShape(User $user, string $today = '2026-11-14'): array
{
    return ReportYears::for(ReportYearsRange::resolve($user, CarbonImmutable::parse($today)))->toArray();
}

/**
 * @param  array<string, mixed>  $section
 * @return array<string, mixed>
 */
function yearsEntry(array $section, string $name): array
{
    foreach ($section['entries'] as $entry) {
        if ($entry['kind'] === 'group') {
            foreach ($entry['categories'] as $category) {
                if ($category['name'] === $name) {
                    return $category;
                }
            }
        } elseif ($entry['name'] === $name) {
            return $entry;
        }
    }

    throw new RuntimeException("No row {$name}");
}

/**
 * @param  array<string, mixed>  $section
 * @return list<string>
 */
function yearsNames(array $section): array
{
    return array_map(fn (array $entry): string => $entry['name'], $section['entries']);
}

test('full years equal the year report to the grosz and same day equals the year in progress', function () {
    [$user, $account] = yearsUser();
    $budgets = yearsBudgets($user, [2025, 2026]);
    $food = yearsLines($budgets, yearsCategory($user, 'Żywność'));
    $salary = yearsLines($budgets, yearsCategory($user, 'Pensja', 'income'));

    yearsBook($user, $account, $food[2025], '2025-03-10', 100_000);
    yearsBook($user, $account, $food[2025], '2025-12-20', 30_000);
    yearsBook($user, $account, $food[2026], '2026-03-10', 120_000);
    yearsBook($user, $account, $food[2026], '2026-04-01', -2_000);
    yearsBook($user, $account, $food[2026], '2026-11-20', 5_000);
    yearsBook($user, $account, $salary[2025], '2025-05-01', 200_000);
    yearsBook($user, $account, $salary[2026], '2026-05-01', 210_000);

    $shape = yearsShape($user);
    $report2025 = PeriodReport::forYear($budgets[2025])->toArray();
    $report2026 = PeriodReport::forYear($budgets[2026])->toArray();

    expect($shape['columns'])->toBe([2025, 2026])
        ->and($shape['current_year'])->toBe(2026)
        ->and($shape['cut'])->toBe(['month' => 11, 'day' => 14])
        ->and($shape['full']['final_year'])->toBe(2025)
        ->and($shape['same_day']['final_year'])->toBe(2026);

    $fullFood = yearsEntry($shape['full']['expense'], 'Żywność');
    expect($fullFood['cells'][0])->toBe($report2025['expense'][0]['amount_minor_units'])->toBe(130_000)
        ->and($fullFood['cells'][1])->toBe($report2026['expense'][0]['amount_minor_units'])->toBe(123_000)
        ->and($shape['full']['expense']['total']['cells'])->toBe([$report2025['expense_minor_units'], $report2026['expense_minor_units']])
        ->and($shape['full']['income']['total']['cells'])->toBe([$report2025['income_minor_units'], $report2026['income_minor_units']]);

    $sameDayFood = yearsEntry($shape['same_day']['expense'], 'Żywność');
    expect($sameDayFood['cells'])->toBe([100_000, 123_000])
        ->and($shape['same_day']['expense']['total']['cells'][1])->toBe($report2026['expense_minor_units'])
        ->and($shape['same_day']['income']['total']['cells'][1])->toBe($report2026['income_minor_units']);
});

test('an operation after day D is out of same day and in full years, one on day D or after is counted for the current year', function () {
    [$user, $account] = yearsUser();
    $budgets = yearsBudgets($user, [2020, 2026]);
    $line = yearsLines($budgets, yearsCategory($user, 'Dom'));

    yearsOperation($user, $account, $line[2020], '2020-11-14', 1_000);
    yearsOperation($user, $account, $line[2020], '2020-11-15', 2_000);
    yearsOperation($user, $account, $line[2026], '2026-11-15', 4_000);

    $shape = yearsShape($user);

    expect(yearsEntry($shape['same_day']['expense'], 'Dom')['cells'])->toBe([1_000, 4_000])
        ->and(yearsEntry($shape['full']['expense'], 'Dom')['cells'])->toBe([3_000, 4_000]);
});

test('an operation dated outside the year of its Budget is counted nowhere, as in the year report', function () {
    [$user, $account] = yearsUser();
    $budgets = yearsBudgets($user, [2025, 2026]);
    $line = yearsLines($budgets, yearsCategory($user, 'Dom'));

    yearsOperation($user, $account, $line[2025], '2025-06-01', 5_000);
    yearsOperation($user, $account, $line[2025], '2024-06-01', 9_000);
    yearsOperation($user, $account, $line[2025], '2026-01-05', 7_000);

    $shape = yearsShape($user);

    expect(PeriodReport::forYear($budgets[2025])->toArray()['expense_minor_units'])->toBe(5_000)
        ->and(yearsEntry($shape['full']['expense'], 'Dom')['cells'][0])->toBe(5_000)
        ->and(yearsEntry($shape['same_day']['expense'], 'Dom')['cells'][0])->toBe(5_000)
        ->and($shape['full']['expense']['total']['cells'][0])->toBe(5_000)
        ->and($shape['full']['expense']['total']['cells'][1])->toBe(0);
});

test('a missing line is a dash and a net zero is zero, also for groups', function () {
    [$user, $account] = yearsUser();
    $budgets = yearsBudgets($user, [2025, 2026]);
    $group = CategoryGroup::factory()->for($user)->expense()->create(['name' => 'Dom']);
    $onlyNow = yearsCategory($user, 'Tylko teraz', 'expense', ['category_group_id' => $group->id]);
    $refunded = yearsLines($budgets, yearsCategory($user, 'Zwrot'));
    $onlyNowLine = yearsLine($budgets[2026], $onlyNow);

    yearsOperation($user, $account, $onlyNowLine, '2026-02-01', 3_000);
    yearsOperation($user, $account, $refunded[2025], '2025-02-01', 500);
    yearsOperation($user, $account, $refunded[2026], '2026-02-01', 1_000);
    yearsOperation($user, $account, $refunded[2026], '2026-02-02', -1_000);

    $shape = yearsShape($user);
    $section = $shape['full']['expense'];
    $groupEntry = collect($section['entries'])->firstWhere('kind', 'group');

    expect(yearsEntry($section, 'Tylko teraz')['cells'])->toBe([null, 3_000])
        ->and(yearsEntry($section, 'Zwrot')['cells'])->toBe([500, 0])
        ->and($groupEntry['name'])->toBe('Dom')
        ->and($groupEntry['cells'])->toBe([null, 3_000])
        ->and($section['total']['cells'])->toBe([500, 3_000]);
});

test('rows are those with an operation in a counted range, archived ones keep their history and merged ones are one row', function () {
    Storage::fake('local');
    [$user, $account] = yearsUser();
    $budgets = yearsBudgets($user, [2024, 2025, 2026]);

    $cinema = yearsCategory($user, 'Kino');
    yearsOperation($user, $account, yearsLine($budgets[2024], $cinema), '2024-03-01', 4_000);
    $cinema->update(['archived_at' => now()]);

    $late = yearsLines($budgets, yearsCategory($user, 'Późna'));
    yearsOperation($user, $account, $late[2025], '2025-12-20', 8_000);

    $target = yearsCategory($user, 'Cel');
    $source = yearsCategory($user, 'Źródło');
    $targetLines = yearsLines($budgets, $target);
    $sourceLines = yearsLines($budgets, $source);
    yearsOperation($user, $account, $targetLines[2025], '2025-02-01', 1_000);
    yearsOperation($user, $account, $targetLines[2026], '2026-02-01', 2_000);
    yearsOperation($user, $account, $sourceLines[2024], '2024-02-01', 300);
    yearsOperation($user, $account, $sourceLines[2025], '2025-02-02', 400);
    yearsOperation($user, $account, $sourceLines[2026], '2026-02-02', 500);

    $before = yearsShape($user);
    expect(yearsEntry($before['full']['expense'], 'Cel')['cells'])->toBe([0, 1_000, 2_000])
        ->and(yearsEntry($before['full']['expense'], 'Źródło')['cells'])->toBe([300, 400, 500]);

    $kino = yearsEntry($before['full']['expense'], 'Kino');
    expect($kino['is_archived'])->toBeTrue()
        ->and($kino['cells'])->toBe([4_000, null, null])
        ->and($kino['change_tenths'])->toBeNull()
        ->and(yearsNames($before['full']['expense']))->toContain('Późna')
        ->and(yearsNames($before['same_day']['expense']))->not->toContain('Późna');

    app(MergeCategories::class)->handle($source, $target, CarbonImmutable::parse('2026-11-14'));

    $after = yearsShape($user);
    expect(yearsNames($after['full']['expense']))->not->toContain('Źródło')
        ->and(yearsEntry($after['full']['expense'], 'Cel')['cells'])->toBe([300, 1_400, 2_500])
        ->and($after['full']['expense']['total']['cells'])->toBe($before['full']['expense']['total']['cells']);
});

test('the change starts at the first year with an amount and is null when that year is the final one', function () {
    [$user, $account] = yearsUser();
    $budgets = yearsBudgets($user, [2020, 2021, 2022, 2023, 2024, 2025, 2026]);
    $furniture = yearsLines($budgets, yearsCategory($user, 'Meble'));
    $fresh = yearsLine($budgets[2026], yearsCategory($user, 'Nowa'));

    yearsOperation($user, $account, $furniture[2021], '2021-02-01', 1_000);
    yearsOperation($user, $account, $furniture[2021], '2021-02-02', -1_000);
    yearsOperation($user, $account, $furniture[2022], '2022-02-01', 10_000);
    yearsOperation($user, $account, $furniture[2025], '2025-02-01', 12_000);
    yearsOperation($user, $account, $furniture[2026], '2026-02-01', 20_000);
    yearsOperation($user, $account, $fresh, '2026-02-01', 500);

    $shape = yearsShape($user);
    $fullFurniture = yearsEntry($shape['full']['expense'], 'Meble');
    $sameDayFurniture = yearsEntry($shape['same_day']['expense'], 'Meble');

    expect($fullFurniture['cells'])->toBe([0, 0, 10_000, 0, 0, 12_000, 20_000])
        ->and($fullFurniture['change_first_year'])->toBe(2022)
        ->and($fullFurniture['change_tenths'])->toBe(200)
        ->and($sameDayFurniture['change_first_year'])->toBe(2022)
        ->and($sameDayFurniture['change_tenths'])->toBe(1_000)
        ->and(yearsEntry($shape['same_day']['expense'], 'Nowa')['change_tenths'])->toBeNull()
        ->and(yearsEntry($shape['same_day']['expense'], 'Nowa')['change_first_year'])->toBeNull();
});

test('the change is null when the final year has no amount above zero', function () {
    [$user, $account] = yearsUser();
    $budgets = yearsBudgets($user, [2024, 2025, 2026]);
    $stopped = yearsLines($budgets, yearsCategory($user, 'Bez wydatków'));
    $refunded = yearsLines($budgets, yearsCategory($user, 'Zwrot'));

    yearsOperation($user, $account, $stopped[2024], '2024-02-01', 5_000);
    yearsOperation($user, $account, $stopped[2025], '2025-02-01', 3_000);
    yearsOperation($user, $account, $refunded[2024], '2024-02-01', 4_000);
    yearsOperation($user, $account, $refunded[2026], '2026-02-01', -200);

    $shape = yearsShape($user);
    $sameDayStopped = yearsEntry($shape['same_day']['expense'], 'Bez wydatków');
    $sameDayRefunded = yearsEntry($shape['same_day']['expense'], 'Zwrot');

    expect($sameDayStopped['cells'])->toBe([5_000, 3_000, 0])
        ->and($sameDayStopped['change_tenths'])->toBeNull()
        ->and($sameDayRefunded['cells'])->toBe([4_000, 0, -200])
        ->and($sameDayRefunded['change_tenths'])->toBeNull()
        ->and(yearsEntry($shape['full']['expense'], 'Bez wydatków')['change_tenths'])->toBe(-400)
        ->and(yearsEntry($shape['full']['expense'], 'Zwrot')['change_tenths'])->toBeNull();
});

test('the archived mark follows the line of the last column and the list Category only without it', function () {
    [$user, $account] = yearsUser();
    $budgets = yearsBudgets($user, [2025, 2026]);

    $lineArchived = yearsCategory($user, 'Linia zarchiwizowana');
    yearsOperation($user, $account, yearsLine($budgets[2025], $lineArchived), '2025-02-01', 1_000);
    yearsLine($budgets[2026], $lineArchived, archived: true);

    $categoryArchived = yearsCategory($user, 'Kategoria zarchiwizowana', attributes: ['archived_at' => now()]);
    yearsOperation($user, $account, yearsLine($budgets[2025], $categoryArchived, archived: true), '2025-02-01', 1_000);
    yearsLine($budgets[2026], $categoryArchived);

    $full = yearsShape($user)['full']['expense'];

    expect(yearsEntry($full, 'Linia zarchiwizowana')['is_archived'])->toBeTrue()
        ->and(yearsEntry($full, 'Kategoria zarchiwizowana')['is_archived'])->toBeFalse();
});

test('statistics follow the hand-computed oracle in both modes', function () {
    [$user, $account] = yearsUser();
    $budgets = yearsBudgets($user, [2020, 2021, 2022, 2023, 2024, 2025, 2026]);
    $rent = yearsLines($budgets, yearsCategory($user, 'Czynsz'));
    $amounts = [2020 => 420_000, 2021 => 500_000, 2022 => 500_000, 2023 => 500_000, 2024 => 500_000, 2025 => 740_000, 2026 => 100_000];
    foreach ($amounts as $year => $amount) {
        yearsOperation($user, $account, $rent[$year], "{$year}-02-01", $amount);
    }

    $shape = yearsShape($user);
    $full = $shape['full']['expense'];
    $sameDay = $shape['same_day']['expense'];

    expect($shape['full']['final_year'])->toBe(2025)
        ->and($full['first_year'])->toBe(2020)
        ->and($full['total']['change_tenths'])->toBe(762)
        ->and($full['average_yearly_growth_tenths'])->toBe(120)
        ->and($full['total']['max_years'])->toBe([2025])
        ->and(yearsEntry($full, 'Czynsz')['max_years'])->toBe([2025])
        ->and($full['fastest_growing']['name'])->toBe('Czynsz')
        ->and($full['fastest_growing']['growth_tenths'])->toBe(120)
        ->and($full['fastest_growing']['first_year'])->toBe(2020)
        ->and($sameDay['total']['change_tenths'])->toBe(-762)
        ->and($sameDay['total']['max_years'])->toBe([2025]);
});

test('the average yearly growth over six years rounds to 113 tenths', function () {
    [$user, $account] = yearsUser();
    $budgets = yearsBudgets($user, [2020, 2021, 2022, 2023, 2024, 2025, 2026]);
    $rent = yearsLines($budgets, yearsCategory($user, 'Czynsz'));
    foreach ([2020 => 359_800, 2021 => 400_000, 2022 => 400_000, 2023 => 400_000, 2024 => 400_000, 2025 => 400_000, 2026 => 682_400] as $year => $amount) {
        yearsOperation($user, $account, $rent[$year], "{$year}-02-01", $amount);
    }

    $sameDay = yearsShape($user)['same_day']['expense'];

    expect($sameDay['average_yearly_growth_tenths'])->toBe(113)
        ->and($sameDay['fastest_growing']['growth_tenths'])->toBe(113);
});

test('the fastest growing needs four years, ties go to the earlier row and without a candidate it is null', function () {
    [$user, $account] = yearsUser();
    $budgets = yearsBudgets($user, [2020, 2021, 2022, 2023, 2024, 2025, 2026]);
    $short = yearsLines(array_slice($budgets, 4, null, true), yearsCategory($user, 'Krótka'));
    $first = yearsLines($budgets, yearsCategory($user, 'Alfa', 'expense', ['sort_order' => 1]));
    $second = yearsLines($budgets, yearsCategory($user, 'Beta', 'expense', ['sort_order' => 2]));

    foreach ([2024 => 100, 2025 => 1_000, 2026 => 100_000] as $year => $amount) {
        yearsOperation($user, $account, $short[$year], "{$year}-02-01", $amount);
    }
    foreach ([$first, $second] as $lines) {
        foreach ([2020 => 1_000, 2021 => 1_000, 2022 => 1_000, 2023 => 1_000, 2024 => 1_000, 2025 => 1_000, 2026 => 2_000] as $year => $amount) {
            yearsOperation($user, $account, $lines[$year], "{$year}-02-01", $amount);
        }
    }

    $section = yearsShape($user)['same_day']['expense'];
    $firstOnList = array_values(array_intersect(yearsNames($section), ['Alfa', 'Beta']))[0];

    expect($section['fastest_growing']['name'])->toBe($firstOnList)
        ->and($section['fastest_growing']['name'])->not->toBe('Krótka');

    $lonely = yearsBudgets(User::factory()->create(), [2026]);
    expect(yearsShape($lonely[2026]->user)['same_day']['expense']['fastest_growing'])->toBeNull();
});

test('with one Budget in execution the change and the statistics are null', function () {
    [$user, $account] = yearsUser();
    $budgets = yearsBudgets($user, [2026]);
    $line = yearsLines($budgets, yearsCategory($user, 'Dom'));
    yearsOperation($user, $account, $line[2026], '2026-02-01', 1_000);

    $section = yearsShape($user)['same_day']['expense'];

    expect($section['entries'][0]['change_tenths'])->toBeNull()
        ->and($section['total']['change_tenths'])->toBeNull()
        ->and($section['first_year'])->toBe(2026)
        ->and($section['average_yearly_growth_tenths'])->toBeNull()
        ->and($section['fastest_growing'])->toBeNull();
});

test('the highest years list every tie and nothing when the maximum is not positive', function () {
    [$user, $account] = yearsUser();
    $budgets = yearsBudgets($user, [2024, 2025, 2026]);
    $rent = yearsLines($budgets, yearsCategory($user, 'Czynsz'));
    $returned = yearsLines($budgets, yearsCategory($user, 'Zwroty'));

    yearsOperation($user, $account, $rent[2024], '2024-02-01', 5_000);
    yearsOperation($user, $account, $rent[2025], '2025-02-01', 9_900);
    yearsOperation($user, $account, $rent[2026], '2026-02-01', 9_900);
    yearsOperation($user, $account, $returned[2026], '2026-02-01', -500);

    $shape = yearsShape($user);

    expect(yearsEntry($shape['same_day']['expense'], 'Czynsz')['max_years'])->toBe([2025, 2026])
        ->and(yearsEntry($shape['full']['expense'], 'Czynsz')['max_years'])->toBe([2025])
        ->and(yearsEntry($shape['same_day']['expense'], 'Zwroty')['max_years'])->toBe([]);
});

test('own transfers, capital movements, other users and drafts are not in the report', function () {
    [$user, $account] = yearsUser();
    $budgets = yearsBudgets($user, [2025, 2026]);
    $line = yearsLines($budgets, yearsCategory($user, 'Dom'));
    yearsOperation($user, $account, $line[2026], '2026-02-01', 1_000);
    Budget::factory()->for($user)->create(['year' => 2027]);

    OwnTransfer::factory()->for($user)->create([
        'source_account_id' => $account->id,
        'destination_account_id' => Account::factory()->for($user)->create()->id,
        'transfer_date' => '2026-03-05',
        'amount_minor_units' => 50_000,
    ]);
    CapitalMovement::factory()->for($user)->create([
        'account_id' => $account->id,
        'movement_date' => '2026-03-05',
        'amount_minor_units' => 60_000,
    ]);

    [$stranger, $strangerAccount] = yearsUser();
    $strangerLines = yearsLines(yearsBudgets($stranger, [2025, 2026]), yearsCategory($stranger, 'Obcy'));
    yearsOperation($stranger, $strangerAccount, $strangerLines[2026], '2026-02-01', 88_000);

    $shape = yearsShape($user);

    expect($shape['columns'])->toBe([2025, 2026])
        ->and(yearsNames($shape['full']['expense']))->toBe(['Dom'])
        ->and($shape['same_day']['expense']['total']['cells'])->toBe([0, 1_000])
        ->and($shape['same_day']['income']['entries'])->toBe([]);
});

test('the default range is full years in January and the same day cut from February', function () {
    [$user, $account] = yearsUser();
    $budgets = yearsBudgets($user, [2025, 2026, 2027]);
    $line = yearsLines($budgets, yearsCategory($user, 'Dom'));
    yearsOperation($user, $account, $line[2026], '2026-01-01', 1_000);
    yearsOperation($user, $account, $line[2027], '2027-01-01', 3_000);

    $january = yearsShape($user, '2027-01-02');
    $november = yearsShape($user, '2026-11-14');

    expect($january['same_day'])->not->toBeNull()
        ->and($january['cut'])->toBe(['month' => 1, 'day' => 2])
        ->and($january['default_range'])->toBe('full')
        ->and($november['default_range'])->toBe('same_day');
});

test('without a current year there is no same day mode and all years are full', function () {
    [$user, $account] = yearsUser();
    $budgets = yearsBudgets($user, [2025, 2026]);
    Budget::factory()->for($user)->create(['year' => 2027]);
    $line = yearsLines($budgets, yearsCategory($user, 'Dom'));
    yearsOperation($user, $account, $line[2025], '2025-02-01', 1_000);
    yearsOperation($user, $account, $line[2026], '2026-02-01', 3_000);

    $shape = yearsShape($user, '2027-01-10');

    expect($shape['current_year'])->toBeNull()
        ->and($shape['cut'])->toBeNull()
        ->and($shape['same_day'])->toBeNull()
        ->and($shape['full']['final_year'])->toBe(2026)
        ->and(yearsEntry($shape['full']['expense'], 'Dom')['change_tenths'])->toBe(2_000);
});

test('without Budgets in execution the report is empty', function () {
    $user = User::factory()->create();
    Budget::factory()->for($user)->create(['year' => 2026]);

    $shape = yearsShape($user);

    expect($shape['columns'])->toBe([])
        ->and($shape['current_year'])->toBeNull()
        ->and($shape['same_day'])->toBeNull()
        ->and($shape['full']['final_year'])->toBeNull()
        ->and($shape['full']['expense']['entries'])->toBe([])
        ->and($shape['full']['expense']['total']['cells'])->toBe([]);
});

/**
 * @return int the number of queries of the report for a fresh dataset
 */
function yearsQueryCount(int $budgetCount, int $categoryCount): int
{
    [$user, $account] = yearsUser();
    $budgets = yearsBudgets($user, range(2026 - $budgetCount + 1, 2026));

    for ($index = 0; $index < $categoryCount; $index++) {
        $lines = yearsLines($budgets, yearsCategory($user, "Kategoria {$index}"));
        foreach ($lines as $year => $line) {
            yearsOperation($user, $account, $line, "{$year}-02-01", 1_000 + $index);
        }
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    yearsShape($user);
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

test('the number of queries does not grow with Budgets or Categories', function () {
    $small = yearsQueryCount(2, 2);
    $large = yearsQueryCount(7, 24);

    expect($large)->toBe($small);
});

/**
 * Every Category of both modes must carry the flag of the real year report of
 * the target year; returns the flags by Category name.
 *
 * @param  array<int, Budget>  $budgets
 * @return array<string, bool>
 */
function yearsOpensPanel(User $user, array $budgets, string $today): array
{
    $shape = yearsShape($user, $today);
    $target = $budgets[array_key_last($budgets)];
    $report = PeriodReport::forYear($target, ReportComparisonPeriod::resolve($target, null, CarbonImmutable::parse($today, 'UTC')))->toArray();
    $rowIds = [];
    foreach ([...$report['expense'], ...$report['income']] as $entry) {
        foreach ($entry['kind'] === 'group' ? $entry['categories'] : [$entry] as $category) {
            $rowIds[$category['id']] = true;
        }
    }

    $flags = [];
    foreach (['same_day', 'full'] as $mode) {
        foreach ([...($shape[$mode]['expense']['entries'] ?? []), ...($shape[$mode]['income']['entries'] ?? [])] as $entry) {
            foreach ($entry['kind'] === 'group' ? $entry['categories'] : [$entry] as $category) {
                expect($category['opens_panel'])->toBe(array_key_exists($category['id'], $rowIds), "{$category['name']} in {$mode}");
                $flags[$category['name']] = $category['opens_panel'];
            }
        }
    }

    return $flags;
}

test('opens_panel equals having a row in the year report of the target year, in both modes', function () {
    [$user, $account] = yearsUser();
    $budgets = yearsBudgets($user, [2020, 2021, 2022, 2025, 2026]);

    $cases = [
        'W roku docelowym' => [2026 => '2026-03-01'],
        'Tylko rok wcześniej przed D' => [2025 => '2025-11-10'],
        'Tylko rok wcześniej po D' => [2025 => '2025-11-20'],
        'Po dziś w roku docelowym' => [2026 => '2026-12-20'],
    ];

    foreach ($cases as $name => $operations) {
        $lines = yearsLines($budgets, yearsCategory($user, $name));
        foreach ($operations as $year => $date) {
            yearsOperation($user, $account, $lines[$year], $date, 1_000);
        }
    }

    $archived = yearsCategory($user, 'Dawna', 'expense', ['archived_at' => now()]);
    foreach ([2020, 2021, 2022] as $year) {
        yearsOperation($user, $account, yearsLine($budgets[$year], $archived, true), "{$year}-05-01", 1_000);
    }

    expect(yearsOpensPanel($user, $budgets, '2026-11-14'))->toEqual([
        'W roku docelowym' => true,
        'Tylko rok wcześniej przed D' => true,
        'Tylko rok wcześniej po D' => false,
        'Po dziś w roku docelowym' => true,
        'Dawna' => false,
    ]);
});

test('a column before the target year that is not the year before it does not count for the flag', function () {
    [$user, $account] = yearsUser();
    $budgets = yearsBudgets($user, [2024, 2026]);
    $draft = Budget::factory()->for($user)->create(['year' => 2025]);
    $category = yearsCategory($user, 'Przed przerwą');
    $lines = yearsLines($budgets, $category);
    yearsLine($draft, $category);
    yearsOperation($user, $account, $lines[2024], '2024-03-01', 1_000);
    $kept = yearsLines($budgets, yearsCategory($user, 'Bieżąca'));
    yearsOperation($user, $account, $kept[2026], '2026-03-01', 1_000);

    expect(yearsOpensPanel($user, $budgets, '2026-11-14'))->toEqual(['Przed przerwą' => false, 'Bieżąca' => true]);
});

test('without a current year the comparison year counts in full for the flag', function () {
    [$user, $account] = yearsUser();
    $budgets = yearsBudgets($user, [2024, 2025]);
    $late = yearsLines($budgets, yearsCategory($user, 'Późna'));
    yearsOperation($user, $account, $late[2024], '2024-12-24', 1_000);
    $old = yearsLines($budgets, yearsCategory($user, 'Dawna'));
    yearsOperation($user, $account, $old[2025], '2025-01-02', 1_000);

    expect(yearsOpensPanel($user, $budgets, '2027-02-01'))->toEqual(['Późna' => true, 'Dawna' => true]);
});

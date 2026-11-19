<?php

use App\Actions\ArchiveCategory;
use App\Actions\ManageCapitalMovement;
use App\Actions\ManageFinancialOperation;
use App\Actions\ManageOwnTransfer;
use App\Enums\CapitalMovementDirection;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\TermDeposit;
use App\Models\User;
use App\Support\AppDay;
use App\Support\BudgetMonthResult;
use App\Support\PeriodReport;
use App\Support\ReportComparisonPeriod;
use App\Support\ReportYears;
use App\Support\ReportYearsRange;
use App\Support\SheetLoad\SheetMapping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\SheetWorkbookFactory;

uses(RefreshDatabase::class);

/**
 * Risk #3 of the test plan: the Execution counter of a Line, the Budget page totals, the Period Report and the Month
 * Result are four readings of the same operations. Every figure below is a literal worked out by hand from the list of
 * operations in the first test; no assertion compares one reading with another.
 */

/**
 * Books through the action, so the Line's Execution counter moves, exactly as the application does.
 */
function reportInvariantBook(User $user, Account $account, Category $category, string $date, int $amount): void
{
    $payload = [
        'operation_date' => $date,
        'amount_minor_units' => $amount,
        'description' => 'Test',
        'account_id' => $account->id,
        'category_id' => $category->id,
    ];
    $action = app(ManageFinancialOperation::class);

    $category->type->value === 'income'
        ? $action->createIncome($user, $payload)
        : $action->createExpense($user, $payload);
}

/**
 * @param  array<string, mixed>  $report
 * @return array<string, int>
 */
function reportInvariantAmountsByName(array $report): array
{
    $amounts = [];

    foreach ([...$report['income'], ...$report['expense']] as $entry) {
        $amounts[$entry['name']] = $entry['amount_minor_units'];
    }

    return $amounts;
}

/**
 * @param  array{income: int, expense: int, result: int}  $expected
 */
function reportInvariantExpectMonth(Budget $budget, int $month, array $expected): void
{
    $report = PeriodReport::forMonth($budget, $month)->toArray();
    $result = BudgetMonthResult::forViewedMonth($budget, $month);

    expect($report['income_minor_units'])->toBe($expected['income'], "report income, month {$month}")
        ->and($report['expense_minor_units'])->toBe($expected['expense'], "report expense, month {$month}")
        ->and($report['result_minor_units'])->toBe($expected['result'], "report result, month {$month}")
        ->and($result['income_execution_minor_units'])->toBe($expected['income'], "month result income, month {$month}")
        ->and($result['expense_execution_minor_units'])->toBe($expected['expense'], "month result expense, month {$month}")
        ->and($result['month_result_minor_units'])->toBe($expected['result'], "month result, month {$month}");
}

/**
 * @return array{income: int, expense: int, result: int}
 */
function reportInvariantYearOfMonthResults(Budget $budget): array
{
    $income = 0;
    $expense = 0;
    $monthResult = 0;

    foreach (range(1, 12) as $month) {
        $result = BudgetMonthResult::forViewedMonth($budget, $month);
        $income += $result['income_execution_minor_units'];
        $expense += $result['expense_execution_minor_units'];
        $monthResult += $result['month_result_minor_units'];
    }

    return ['income' => $income, 'expense' => $expense, 'result' => $monthResult];
}

test('every reading of the same operations gives the hand-counted figure, boundaries included', function () {
    $this->travelTo('2026-06-15');

    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create([
        'opening_date' => '2020-01-01',
        'opening_balance_minor_units' => 1_000_000_000,
        'current_balance_minor_units' => 1_000_000_000,
    ]);
    $savings = Account::factory()->for($user)->create(['opening_date' => '2020-01-01']);
    $termDeposit = TermDeposit::factory()->for($user)->create(['opening_date' => '2020-01-01']);
    $budget2025 = Budget::factory()->for($user)->execution()->create(['year' => 2025]);
    $budget2026 = Budget::factory()->for($user)->execution()->create(['year' => 2026]);

    $salary = Category::factory()->for($user)->income()->create(['name' => 'Wynagrodzenie']);
    $food = Category::factory()->for($user)->expense()->create(['name' => 'Jedzenie']);
    $gym = Category::factory()->for($user)->expense()->create(['name' => 'Siłownia']);

    $lines = [];
    foreach ([2025 => $budget2025, 2026 => $budget2026] as $year => $budget) {
        foreach ([$salary, $food, $gym] as $category) {
            $lines[$year][$category->name] = BudgetLine::factory()->for($budget)->create(['category_id' => $category->id]);
        }
    }

    // Amounts are in groszy: 8 000 zł = 800 000.
    reportInvariantBook($user, $account, $salary, '2026-03-31', 800_000);
    reportInvariantBook($user, $account, $salary, '2026-04-01', 800_000);

    reportInvariantBook($user, $account, $food, '2026-01-01', 120_000);
    reportInvariantBook($user, $account, $food, '2026-03-31', 90_000);
    reportInvariantBook($user, $account, $food, '2026-04-01', 70_000);
    reportInvariantBook($user, $account, $food, '2026-04-10', -15_000);
    reportInvariantBook($user, $account, $food, '2025-12-31', 100_000);

    foreach (['2026-01-15', '2026-02-15', '2026-03-15', '2026-04-15'] as $date) {
        reportInvariantBook($user, $account, $gym, $date, 15_000);
    }
    app(ArchiveCategory::class)->handle($gym);

    // Neither of these is an income or an expense of the household.
    app(ManageOwnTransfer::class)->create($user, [
        'source_account_id' => $account->id,
        'destination_account_id' => $savings->id,
        'transfer_date' => '2026-03-31',
        'amount_minor_units' => 50_000,
        'description' => 'Test',
    ]);
    app(ManageCapitalMovement::class)->create($user, [
        'account_id' => $account->id,
        'term_deposit_id' => $termDeposit->id,
        'movement_date' => '2026-04-01',
        'amount_minor_units' => 60_000,
        'direction' => CapitalMovementDirection::ToTermDeposit,
        'description' => 'Test',
    ]);

    $stranger = User::factory()->create();
    $strangerAccount = Account::factory()->for($stranger)->create([
        'opening_date' => '2020-01-01',
        'opening_balance_minor_units' => 1_000_000_000,
        'current_balance_minor_units' => 1_000_000_000,
    ]);
    $strangerBudget = Budget::factory()->for($stranger)->execution()->create(['year' => 2026]);
    $strangerCategory = Category::factory()->for($stranger)->expense()->create(['name' => 'Obcy wydatek']);
    BudgetLine::factory()->for($strangerBudget)->create(['category_id' => $strangerCategory->id]);
    reportInvariantBook($stranger, $strangerAccount, $strangerCategory, '2026-03-31', 99_900);

    // Execution counters.
    // Wynagrodzenie 2026: 8 000 + 8 000 = 16 000 zł.
    // Jedzenie 2026: 1 200 + 900 + 700 - 150 (refund) = 2 650 zł. The 31.12.2025 operation belongs to Budget 2025.
    // Siłownia 2026: 4 x 150 = 600 zł. Jedzenie 2025: 1 000 zł.
    expect($lines[2026]['Wynagrodzenie']->refresh()->execution_minor_units)->toBe(1_600_000)
        ->and($lines[2026]['Jedzenie']->refresh()->execution_minor_units)->toBe(265_000)
        ->and($lines[2026]['Siłownia']->refresh()->execution_minor_units)->toBe(60_000)
        ->and($lines[2025]['Jedzenie']->refresh()->execution_minor_units)->toBe(100_000)
        // Expenses of every Line of 2026: 2 650 + 600 = 3 250 zł.
        ->and((int) BudgetLine::query()->where('budget_id', $budget2026->id)->whereIn('category_id', [$food->id, $gym->id])->sum('execution_minor_units'))->toBe(325_000)
        ->and((int) BudgetLine::query()->where('budget_id', $budget2026->id)->where('category_id', $salary->id)->sum('execution_minor_units'))->toBe(1_600_000);

    // Budget page of 2026. With the archived Siłownia: expenses 2 650 + 600 = 3 250 zł, income 16 000 zł.
    // Without it (the default view hides archived Lines, intended): expenses 2 650 zł.
    $page = fn (array $query) => $this->actingAs($user)
        ->withSession(['budget_year' => 2026])
        ->get(route('budget', $query))
        ->assertOk();

    $page(['show_archived' => 1])->assertInertia(fn (Assert $page) => $page
        ->where('expenseTotals.execution_minor_units', 325_000)
        ->where('incomeTotals.execution_minor_units', 1_600_000));
    $page([])->assertInertia(fn (Assert $page) => $page
        ->where('expenseTotals.execution_minor_units', 265_000)
        ->where('incomeTotals.execution_minor_units', 1_600_000));

    // Period Report of 2026: expenses 3 250 zł, income 16 000 zł, result 16 000 - 3 250 = 12 750 zł.
    $year = PeriodReport::forYear($budget2026)->toArray();

    expect($year['expense_minor_units'])->toBe(325_000)
        ->and($year['income_minor_units'])->toBe(1_600_000)
        ->and($year['result_minor_units'])->toBe(1_275_000)
        ->and(reportInvariantAmountsByName($year))->toBe(['Wynagrodzenie' => 1_600_000, 'Jedzenie' => 265_000, 'Siłownia' => 60_000]);

    // Month by month, expenses are Jedzenie plus Siłownia of the month.
    // January: 1 200 + 150 = 1 350. February: 150. March: 900 + 150 = 1 050, income 8 000, result 8 000 - 1 050 = 6 950.
    // April: 700 - 150 + 150 = 700, income 8 000, result 8 000 - 700 = 7 300. May to December: nothing.
    // The own transfer (31.03), the capital movement (01.04) and the stranger's 999 zł (31.03) are in no month.
    $expectedMonths = [
        1 => ['income' => 0, 'expense' => 135_000, 'result' => -135_000],
        2 => ['income' => 0, 'expense' => 15_000, 'result' => -15_000],
        3 => ['income' => 800_000, 'expense' => 105_000, 'result' => 695_000],
        4 => ['income' => 800_000, 'expense' => 70_000, 'result' => 730_000],
    ];
    foreach (range(5, 12) as $month) {
        $expectedMonths[$month] = ['income' => 0, 'expense' => 0, 'result' => 0];
    }

    foreach ($expectedMonths as $month => $expected) {
        reportInvariantExpectMonth($budget2026, $month, $expected);
    }

    // The twelve Month Results add up to the year: expenses 1 350 + 150 + 1 050 + 700 = 3 250 zł, income 16 000 zł,
    // result -1 350 - 150 + 6 950 + 7 300 = 12 750 zł.
    expect(reportInvariantYearOfMonthResults($budget2026))->toBe(['income' => 1_600_000, 'expense' => 325_000, 'result' => 1_275_000]);

    // Budget 2025: Jedzenie 1 000 zł on 31.12, so the year and December 2025 are 1 000 zł. January 2026 stays at its own
    // 1 200 zł of 01.01 plus Siłownia 150 zł = 1 350 zł, without that operation.
    $year2025 = PeriodReport::forYear($budget2025)->toArray();

    expect($year2025['expense_minor_units'])->toBe(100_000)
        ->and($year2025['income_minor_units'])->toBe(0)
        ->and(PeriodReport::forMonth($budget2025, 12)->toArray()['expense_minor_units'])->toBe(100_000)
        ->and(BudgetMonthResult::forViewedMonth($budget2025, 12)['expense_execution_minor_units'])->toBe(100_000)
        ->and(PeriodReport::forMonth($budget2026, 1)->toArray()['expense_minor_units'])->toBe(135_000);
});

test('on 1 January every year-over-year and Years reading gives the hand-counted figure', function () {
    $this->travelTo('2026-12-31 23:00:00');

    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create([
        'opening_date' => '2020-01-01',
        'opening_balance_minor_units' => 1_000_000_000,
        'current_balance_minor_units' => 1_000_000_000,
    ]);
    $budget2026 = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
    $budget2027 = Budget::factory()->for($user)->execution()->create(['year' => 2027]);
    $food = Category::factory()->for($user)->expense()->create(['name' => 'Jedzenie']);
    BudgetLine::factory()->for($budget2026)->create(['category_id' => $food->id]);
    BudgetLine::factory()->for($budget2027)->create(['category_id' => $food->id]);

    // Amounts are in groszy: 10 zł = 1 000. It is 1 January 2027 in Poland (23:00 UTC on 31 December).
    reportInvariantBook($user, $account, $food, '2026-01-01', 1_000);
    reportInvariantBook($user, $account, $food, '2026-01-02', 20_000);
    reportInvariantBook($user, $account, $food, '2026-01-31', 500_000);
    reportInvariantBook($user, $account, $food, '2026-03-15', 3_000_000);
    reportInvariantBook($user, $account, $food, '2027-01-01', 7_000);

    // Year 2026: 10 + 200 + 5 000 + 30 000 = 35 210 zł = 3 521 000 groszy.
    // Up to 1 January of 2026 and of 2027 (the Same-day Cut of the App Day): 10 zł and 70 zł.
    $today = AppDay::today();
    $yearComparison = PeriodReport::forYear($budget2027, ReportComparisonPeriod::resolve($budget2027, null, $today))->toArray();
    $januaryComparison = PeriodReport::forMonth($budget2027, 1, ReportComparisonPeriod::resolve($budget2027, 1, $today))->toArray();
    $years = ReportYears::for(ReportYearsRange::resolve($user, $today))->toArray();

    expect($today->toDateString())->toBe('2027-01-01')
        // The Year 2027 report compares with 1 January 2026 only: 10 zł, not 10 + 200 zł.
        ->and($yearComparison['expense_minor_units'])->toBe(7_000)
        ->and($yearComparison['comparison']['expense_minor_units'])->toBe(1_000)
        ->and($yearComparison['comparison']['expense_difference_minor_units'])->toBe(6_000)
        // January 2027 compares with 1 January 2026 as well: 10 zł, not the 5 210 zł of the whole January.
        ->and($januaryComparison['expense_minor_units'])->toBe(7_000)
        ->and($januaryComparison['comparison']['expense_minor_units'])->toBe(1_000)
        // Years: "Razem" of the Same-day Cut and of Full Years, columns 2026 and 2027.
        ->and($years['columns'])->toBe([2026, 2027])
        ->and($years['same_day']['expense']['total']['cells'])->toBe([1_000, 7_000])
        ->and($years['full']['expense']['total']['cells'][0])->toBe(3_521_000)
        // The Year 2026 report is the same 35 210 zł.
        ->and(PeriodReport::forYear($budget2026)->toArray()['expense_minor_units'])->toBe(3_521_000);
});

test('a year loaded from the sheet keeps the hand-counted amounts in every reading', function () {
    $this->travelTo('2026-11-18');

    $user = User::factory()->create();

    foreach (SheetMapping::fromFile()->expectedCategories() as $id => $category) {
        Category::factory()->create(['id' => $id, 'user_id' => $user->id, 'name' => $category['name'], 'type' => $category['type']]);
    }

    if (DB::getDriverName() === 'pgsql') {
        DB::statement("select setval(pg_get_serial_sequence('categories', 'id'), (select max(id) from categories))");
    }

    $budget2026 = Budget::factory()->for($user)->execution()->create(['year' => 2026]);

    // Every month keeps one expense label and nothing else: 1 000 zł, in December 1 200 zł. The sheet of 2025 has a
    // mapping override on February's "pozostałe" (the tax refund, 5 790,12 zł), so that cell has to exist and hold the
    // same amount: it becomes the only income operation of the year.
    $months = [];
    foreach (range(1, 12) as $month) {
        $months[$month] = [
            'expenses' => ['Codzienne wydatki' => $month === 12 ? 1200.00 : 1000.00],
            'income' => ['pozostałe' => $month === 2 ? 5790.12 : 0.0, 'oszczędności' => 0.0],
            'irregular' => [],
            'medical' => [],
            'exams' => [],
        ];
    }

    $exit = null;

    SheetWorkbookFactory::with(['year' => 2025, 'months' => $months], function (string $path) use ($user, &$exit): void {
        $exit = Artisan::call('budget:load-sheet', ['user' => (string) $user->id, 'path' => $path, '--force' => true]);
    });

    expect($exit)->toBe(0, Artisan::output());

    $budget = Budget::query()->where('user_id', $user->id)->where('year', 2025)->firstOrFail();
    // The sheet label "codzienne wydatki" maps to Category 8 ("Artykuły spożywcze") and 6 is the income of the tax refund
    // (database/sheet-load/mapping.php).
    $line = BudgetLine::query()->where('budget_id', $budget->id)->where('category_id', 8)->firstOrFail();

    // 11 x 1 000 + 1 200 = 12 200 zł.
    expect(BudgetLine::query()->where('budget_id', $budget->id)->count())->toBe(2)
        ->and($line->execution_minor_units)->toBe(1_220_000);

    $year = PeriodReport::forYear($budget)->toArray();

    // Income of the year: the tax refund 5 790,12 zł = 579 012 groszy; result 5 790,12 - 12 200 = -6 409,88 zł.
    expect(reportInvariantAmountsByName($year)[$line->category->name])->toBe(1_220_000)
        ->and($year['expense_minor_units'])->toBe(1_220_000)
        ->and($year['income_minor_units'])->toBe(579_012)
        ->and($year['result_minor_units'])->toBe(-640_988)
        // Result of the twelve Month Results: 5 790,12 - 12 200 = -6 409,88 zł.
        ->and(reportInvariantYearOfMonthResults($budget))->toBe(['income' => 579_012, 'expense' => 1_220_000, 'result' => -640_988]);

    // February: income 5 790,12 zł, expense 1 000 zł, result 4 790,12 zł.
    // December is the 1 200 zł operation dated 31.12; January 2026 (an empty Budget in execution) has none of it.
    reportInvariantExpectMonth($budget, 2, ['income' => 579_012, 'expense' => 100_000, 'result' => 479_012]);

    expect(BudgetMonthResult::forViewedMonth($budget, 12)['expense_execution_minor_units'])->toBe(120_000)
        ->and(PeriodReport::forMonth($budget, 12)->toArray()['expense_minor_units'])->toBe(120_000)
        ->and(PeriodReport::forMonth($budget2026, 1)->toArray()['expense_minor_units'])->toBe(0);
});

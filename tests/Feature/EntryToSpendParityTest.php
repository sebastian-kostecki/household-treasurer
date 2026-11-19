<?php

use App\Actions\ManageFinancialOperation;
use App\Enums\Rhythm;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\CategoryRhythm;
use App\Models\FinancialOperation;
use App\Models\MonthPlan;
use App\Models\MonthPlanItem;
use App\Models\User;
use App\Support\EntryToSpendInputs;
use App\Support\MonthToSpend;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Scenarios shared with resources/js/lib/entry-to-spend-warning.parity.test.ts. The inputs
 * and the readings are derived by hand from the glossary (To Spend, Irregular Rate, Collected).
 *
 * @return array<string, array{array<string, mixed>}>
 */
function entryParityDataset(): array
{
    /** @var array{scenarios: list<array<string, mixed>>} $file */
    $file = json_decode(
        (string) file_get_contents(__DIR__.'/../Fixtures/amount-invariants/entry-to-spend-scenarios.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    $rows = [];

    foreach ($file['scenarios'] as $scenario) {
        $rows[$scenario['key']] = [$scenario];
    }

    return $rows;
}

dataset('entry to spend scenarios', fn () => entryParityDataset());

/**
 * @param  'income'|'running'|'fixed'|'irregular'  $kind
 */
function entryParityLine(Budget $budget, string $name, string $kind, int $planned, int $opening = 0, int $irregularFromMonth = 1): BudgetLine
{
    $factory = BudgetLine::factory()->for($budget);
    $factory = match ($kind) {
        'income' => $factory->income(),
        'fixed' => $factory->fixedCharge(),
        default => $factory->expense(),
    };
    $line = $factory->create(['name' => $name, 'planned_amount_minor_units' => $planned]);

    if ($kind === 'irregular') {
        $line->category->rhythms()->delete();
        CategoryRhythm::factory()->starting($budget->year, $irregularFromMonth)->irregular($opening)->create(['category_id' => $line->category_id]);
    }

    return $line;
}

/**
 * @param  array<string, array{line: BudgetLine, amount: int}>  $items  keyed by line name, in the approved plan
 */
function entryParityApprove(Budget $budget, int $month, array $items, int $setAside = 0): void
{
    $plan = MonthPlan::factory()->for($budget)->create([
        'month' => $month,
        'approved_set_aside_minor_units' => $setAside,
        'current_set_aside_minor_units' => $setAside,
    ]);

    foreach ($items as $item) {
        MonthPlanItem::factory()->create([
            'month_plan_id' => $plan->id,
            'budget_line_id' => $item['line']->id,
            'approved_amount_minor_units' => $item['amount'],
            'current_amount_minor_units' => $item['amount'],
        ]);
    }
}

function entryParityBook(User $user, Account $account, BudgetLine $line, string $date, int $amount): FinancialOperation
{
    $payload = [
        'operation_date' => $date,
        'amount_minor_units' => $amount,
        'description' => 'Parity',
        'account_id' => $account->id,
        'category_id' => $line->category_id,
    ];
    $action = app(ManageFinancialOperation::class);

    return $line->category->type->value === 'income'
        ? $action->createIncome($user, $payload)
        : $action->createExpense($user, $payload);
}

/**
 * Seeds the state of the scenario before the entry. The fixture describes the result, not the way of getting there.
 *
 * @return array<string, BudgetLine> lines keyed by the name of their Category
 */
function entryParitySeed(string $key, User $user, Account $account, Budget $budget): array
{
    switch ($key) {
        case 'fixed-charge-refund-netted':
            $salary = entryParityLine($budget, 'Pensja', 'income', 9_600_000);
            $power = entryParityLine($budget, 'Prąd', 'fixed', 180_000);
            $food = entryParityLine($budget, 'Jedzenie', 'running', 1_800_000);
            entryParityApprove($budget, 3, [
                'Pensja' => ['line' => $salary, 'amount' => 800_000],
                'Prąd' => ['line' => $power, 'amount' => 15_000],
                'Jedzenie' => ['line' => $food, 'amount' => 150_000],
            ], 100_000);
            entryParityBook($user, $account, $salary, '2026-03-01', 800_000);
            entryParityBook($user, $account, $power, '2026-03-05', 18_000);
            entryParityBook($user, $account, $power, '2026-03-10', -20_000);
            entryParityBook($user, $account, $food, '2026-03-07', 90_000);

            return ['Pensja' => $salary, 'Prąd' => $power, 'Jedzenie' => $food];

        case 'fixed-charge-refund-first':
            $salary = entryParityLine($budget, 'Pensja', 'income', 7_200_000);
            $power = entryParityLine($budget, 'Prąd', 'fixed', 180_000);
            entryParityApprove($budget, 3, [
                'Pensja' => ['line' => $salary, 'amount' => 600_000],
                'Prąd' => ['line' => $power, 'amount' => 15_000],
            ]);

            return ['Pensja' => $salary, 'Prąd' => $power];

        case 'fixed-charge-plan-zero-proposal':
            $salary = entryParityLine($budget, 'Pensja', 'income', 6_000_000);
            $internet = entryParityLine($budget, 'Internet', 'fixed', 0);

            return ['Pensja' => $salary, 'Internet' => $internet];

        case 'irregular-later-month':
            $salary = entryParityLine($budget, 'Pensja', 'income', 6_000_000);
            $insurance = entryParityLine($budget, 'Ubezpieczenie', 'irregular', 120_000, 60_000, 9);

            foreach ([9, 10, 11] as $month) {
                entryParityApprove($budget, $month, [
                    'Pensja' => ['line' => $salary, 'amount' => 500_000],
                    'Ubezpieczenie' => ['line' => $insurance, 'amount' => 10_000],
                ]);
            }

            entryParityBook($user, $account, $insurance, '2026-11-05', 90_000);

            return ['Pensja' => $salary, 'Ubezpieczenie' => $insurance];

        case 'running-edit-with-draw':
            $salary = entryParityLine($budget, 'Pensja', 'income', 7_200_000);
            $food = entryParityLine($budget, 'Jedzenie', 'running', 1_800_000);
            entryParityApprove($budget, 3, [
                'Pensja' => ['line' => $salary, 'amount' => 600_000],
                'Jedzenie' => ['line' => $food, 'amount' => 150_000],
            ], -50_000);
            entryParityBook($user, $account, $food, '2026-03-07', 90_000);

            return ['Pensja' => $salary, 'Jedzenie' => $food];

        case 'fixed-charge-backdated-proposal':
            $salary = entryParityLine($budget, 'Pensja', 'income', 6_000_000);
            $power = entryParityLine($budget, 'Prąd', 'fixed', 180_000);
            entryParityApprove($budget, 2, [
                'Pensja' => ['line' => $salary, 'amount' => 500_000],
                'Prąd' => ['line' => $power, 'amount' => 15_000],
            ]);

            return ['Pensja' => $salary, 'Prąd' => $power];

        case 'january-joining-category':
            $salary = entryParityLine($budget, 'Pensja', 'income', 6_000_000);
            $hairdresser = entryParityLine($budget, 'Fryzjer', 'running', 480_000);
            $hairdresser->category->rhythms()->delete();
            $hairdresser->category->rhythms()->create(['start_year' => 2026, 'start_month' => 1, 'rhythm' => Rhythm::Running]);
            entryParityApprove($budget, 12, [
                'Pensja' => ['line' => $salary, 'amount' => 500_000],
                'Fryzjer' => ['line' => $hairdresser, 'amount' => 40_000],
            ]);

            $next = Budget::factory()->for($user)->execution()->create(['year' => 2027]);
            entryParityLine($next, 'Pensja', 'income', 6_000_000);

            return ['Pensja' => $salary, 'Fryzjer' => $hairdresser];
    }

    throw new LogicException("No seeder for the scenario {$key}.");
}

/**
 * Replaces the id of every Category by its key from the fixture and puts the rows in the order of the fixture.
 *
 * @param  array<string, mixed>  $payload  the output of EntryToSpendInputs::forUser
 * @param  array<int, int>  $keyByCategoryId
 * @return array<string, mixed>
 */
function entryParityCanonical(array $payload, array $keyByCategoryId): array
{
    foreach ($payload['budgets'] as &$budget) {
        foreach ($budget['categories'] as &$category) {
            $category['id'] = $keyByCategoryId[$category['id']];
        }
        unset($category);
        usort($budget['categories'], fn (array $a, array $b): int => $a['id'] <=> $b['id']);

        foreach ($budget['months'] as &$month) {
            foreach ($month['items'] as &$item) {
                $item['category_id'] = $keyByCategoryId[$item['category_id']];
            }
            unset($item);
            usort($month['items'], fn (array $a, array $b): int => $a['category_id'] <=> $b['category_id']);
        }
        unset($month);
    }
    unset($budget);

    foreach ($payload['irregular_periods'] as &$period) {
        $period['category_id'] = $keyByCategoryId[$period['category_id']];
    }
    unset($period);
    usort($payload['irregular_periods'], fn (array $a, array $b): int => [$a['category_id'], $a['start_year'], $a['start_month']] <=> [$b['category_id'], $b['start_year'], $b['start_month']]);

    return $payload;
}

/**
 * @param  list<array{year: int, month: int, before: int, after: int}>  $months
 * @return list<int|null>
 */
function entryParityServerReadings(Budget $budget, array $months, CarbonImmutable $today): array
{
    return array_map(
        fn (array $month): ?int => MonthToSpend::forMonth(Budget::query()->where('user_id', $budget->user_id)->where('year', $month['year'])->sole(), $month['month'], $today)->toSpendMinorUnits(),
        $months,
    );
}

test('server inputs and To Spend match the hand-computed scenario before and after the entry', function (array $scenario) {
    $today = CarbonImmutable::parse($scenario['today'])->startOfDay();
    $this->travelTo($today);

    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['opening_date' => '2026-01-01']);
    $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
    $lines = entryParitySeed($scenario['key'], $user, $account, $budget);

    $keyByName = array_flip($scenario['categories']);
    $keyByCategoryId = [];

    foreach ($lines as $name => $line) {
        $keyByCategoryId[$line->category_id] = (int) $keyByName[$name];
    }

    expect($keyByCategoryId)->toHaveCount(count($scenario['categories']));

    expect(entryParityCanonical(EntryToSpendInputs::forUser($user, $today), $keyByCategoryId))
        ->toBe($scenario['inputs']);

    expect(entryParityServerReadings($budget, $scenario['server_months'], $today))
        ->toBe(array_column($scenario['server_months'], 'before'));

    $lineByKey = [];

    foreach ($lines as $name => $line) {
        $lineByKey[(int) $keyByName[$name]] = $line;
    }

    $removed = [];

    foreach ($scenario['changes'] as $change) {
        $line = $lineByKey[$change['categoryId']];

        if ($change['direction'] === 'remove') {
            $removed[$change['categoryId']] = FinancialOperation::query()
                ->where('budget_line_id', $line->id)
                ->whereDate('operation_date', $change['operationDate'])
                ->where('amount_minor_units', $change['amountMinorUnits'])
                ->sole();

            continue;
        }

        if (isset($removed[$change['categoryId']])) {
            $payload = [
                'operation_date' => $change['operationDate'],
                'amount_minor_units' => $change['amountMinorUnits'],
                'description' => 'Parity',
                'account_id' => $account->id,
                'category_id' => $line->category_id,
            ];
            $action = app(ManageFinancialOperation::class);

            $line->category->type->value === 'income'
                ? $action->updateIncome($removed[$change['categoryId']], $payload)
                : $action->updateExpense($removed[$change['categoryId']], $payload);

            continue;
        }

        entryParityBook($user, $account, $line, $change['operationDate'], $change['amountMinorUnits']);
    }

    expect(entryParityServerReadings($budget, $scenario['server_months'], $today))
        ->toBe(array_column($scenario['server_months'], 'after'));
})->with('entry to spend scenarios');

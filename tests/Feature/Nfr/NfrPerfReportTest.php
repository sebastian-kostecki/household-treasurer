<?php

namespace Tests\Feature\Nfr;

use App\Enums\BudgetPhase;
use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * NFR-PERF-01 for the Period Report (S-28, S-29): a year, a month and a trend report
 * open in two seconds at 10,000 operations, also compared with the year before
 * (a second Budget with 10,000 operations on the same list Categories).
 *
 * Run in Sail on PostgreSQL:
 * ./vendor/bin/sail artisan test --group=nfr --configuration=phpunit.nfr.xml
 */
#[Group('nfr')]
class NfrPerfReportTest extends TestCase
{
    use MeasuresRequestTime;
    use RefreshDatabase;

    private const int FINANCIAL_OPERATIONS_COUNT = 10_000;

    private const int EXPENSE_GROUPS_COUNT = 6;

    private const int CATEGORIES_PER_GROUP = 6;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped(
                'NFR-PERF-01 runs on PostgreSQL only. Use: ./vendor/bin/sail artisan test --group=nfr --configuration=phpunit.nfr.xml',
            );
        }

        $this->withoutVite();
    }

    public function test_year_and_month_report_open_within_two_seconds_at_ten_thousand_operations(): void
    {
        $this->travelTo('2026-12-15');

        $user = $this->seedReportDataset();

        $this->assertRequestWithinSeconds(
            fn () => $this->actingAs($user)
                ->withSession(['budget_year' => 2026])
                ->get(route('reports'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->where('period.kind', 'year')
                    ->where('period.is_in_progress', true)
                    ->where('report.comparison.year', 2025)
                    ->has('report.expense')),
            'Year report',
        );

        $this->assertRequestWithinSeconds(
            fn () => $this->actingAs($user)
                ->withSession(['budget_year' => 2026])
                ->get(route('reports', ['month' => 6]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->where('period.kind', 'month')
                    ->where('report.comparison.year', 2025)
                    ->has('report.expense')),
            'Month report',
        );

        $this->assertRequestWithinSeconds(
            fn () => $this->actingAs($user)
                ->withSession(['budget_year' => 2026])
                ->get(route('reports', ['view' => 'trend']))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->where('period.kind', 'trend')
                    ->where('trend.in_progress_month', 12)
                    ->has('trend.months', 12)
                    ->has('trend.expense')),
            'Trend report',
        );
    }

    private function seedReportDataset(): User
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 10_000_000,
            'current_balance_minor_units' => 10_000_000,
        ]);
        $budget = Budget::factory()->for($user)->create([
            'year' => 2026,
            'phase' => BudgetPhase::Execution,
        ]);

        /** @var list<BudgetLine> $lines */
        $lines = [];

        for ($groupIndex = 0; $groupIndex < self::EXPENSE_GROUPS_COUNT; $groupIndex++) {
            $group = CategoryGroup::factory()->for($user)->expense()->create([
                'name' => "Group {$groupIndex}",
                'sort_order' => $groupIndex,
            ]);

            for ($index = 0; $index < self::CATEGORIES_PER_GROUP; $index++) {
                $archived = $groupIndex === 0 && $index === 0;
                $lines[] = $this->budgetLine($user, $budget, "Expense {$groupIndex}-{$index}", $group, $archived);
            }
        }

        $incomeLine = $this->budgetLine($user, $budget, 'Salary', null, false);

        $this->insertOperations($user, $account, $budget, $lines, $incomeLine);

        $previousBudget = Budget::factory()->for($user)->create([
            'year' => 2025,
            'phase' => BudgetPhase::Execution,
        ]);
        $previousLines = array_map(
            fn (BudgetLine $line): BudgetLine => BudgetLine::factory()->for($previousBudget)->create(['category_id' => $line->category_id]),
            $lines,
        );
        $previousIncomeLine = BudgetLine::factory()->for($previousBudget)->create(['category_id' => $incomeLine->category_id]);

        $this->insertOperations($user, $account, $previousBudget, $previousLines, $previousIncomeLine);

        return $user;
    }

    private function budgetLine(User $user, Budget $budget, string $name, ?CategoryGroup $group, bool $archived): BudgetLine
    {
        $type = $name === 'Salary' ? 'income' : 'expense';
        $category = Category::factory()->for($user)->{$type}()->create([
            'name' => $name,
            'category_group_id' => $group?->id,
        ]);
        $factory = BudgetLine::factory()->for($budget);

        return ($archived ? $factory->archived() : $factory)->create(['category_id' => $category->id]);
    }

    /**
     * @param  list<BudgetLine>  $expenseLines
     */
    private function insertOperations(User $user, Account $account, Budget $budget, array $expenseLines, BudgetLine $incomeLine): void
    {
        $now = now();
        $rows = [];

        for ($index = 0; $index < self::FINANCIAL_OPERATIONS_COUNT; $index++) {
            $isIncome = $index % 20 === 0;
            $line = $isIncome ? $incomeLine : $expenseLines[$index % count($expenseLines)];
            $isRefund = ! $isIncome && $index % 50 === 1;
            $month = 1 + ($index % 12);
            $day = 1 + ($index % 28);

            $rows[] = [
                'user_id' => $user->id,
                'type' => ($isIncome ? FinancialOperationType::Income : FinancialOperationType::Expense)->value,
                'account_id' => $account->id,
                'budget_line_id' => $line->id,
                'budget_id' => $budget->id,
                'operation_date' => sprintf('%d-%02d-%02d', $budget->year, $month, $day),
                'amount_minor_units' => ($isRefund ? -1 : 1) * (1000 + ($index % 100)),
                'description' => "Seeded operation {$index}",
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) === 500) {
                DB::table('financial_operations')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('financial_operations')->insert($rows);
        }
    }
}

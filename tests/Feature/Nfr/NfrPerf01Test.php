<?php

namespace Tests\Feature\Nfr;

use App\Enums\BudgetPhase;
use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * NFR-PERF-01 acceptance test — see context/foundation/prd-mvp.md §10.
 *
 * Run in Sail on PostgreSQL:
 * ./vendor/bin/sail artisan test --group=nfr --configuration=phpunit.nfr.xml
 */
#[Group('nfr')]
class NfrPerf01Test extends TestCase
{
    use RefreshDatabase;

    private const int FINANCIAL_OPERATIONS_COUNT = 10_000;

    private const int OWN_TRANSFERS_COUNT = 1_000;

    private const float MAX_SECONDS = 2.0;

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

    public function test_nfr_perf_01_key_pages_and_manual_operation_store_within_two_seconds(): void
    {
        $this->travelTo('2026-06-01');

        $fixture = $this->seedPerformanceDataset();

        $this->assertRequestWithinSeconds(
            fn () => $this->actingAs($fixture['user'])
                ->get(route('accounts.index'))
                ->assertOk(),
            'Account list',
        );

        $this->assertRequestWithinSeconds(
            fn () => $this->actingAs($fixture['user'])
                ->get(route('budget'))
                ->assertOk(),
            'Budget summary',
        );

        $this->assertRequestWithinSeconds(
            fn () => $this->actingAs($fixture['user'])
                ->get(route('operations'))
                ->assertOk(),
            'Operations list page 1',
        );

        $this->assertRequestWithinSeconds(
            fn () => $this->actingAs($fixture['user'])
                ->post(route('expenses.store'), [
                    'operation_date' => '2026-03-15',
                    'amount_minor_units' => 25000,
                    'description' => 'Performance test expense',
                    'account_id' => $fixture['account']->id,
                    'category_id' => $fixture['expenseCategory']->id,
                ])
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('operations')),
            'Manual financial operation store',
        );
    }

    /**
     * @return array{
     *     user: User,
     *     account: Account,
     *     expenseCategory: Category,
     * }
     */
    private function seedPerformanceDataset(): array
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

        $incomeCategory = Category::factory()->for($budget)->income()->create();
        $expenseCategory = Category::factory()->for($budget)->expense()->create([
            'planned_amount_minor_units' => 5_000_000,
            'execution_minor_units' => 0,
        ]);

        $sourceAccount = Account::factory()->for($user)->bank()->create([
            'opening_date' => '2026-01-01',
        ]);
        $destinationAccount = Account::factory()->for($user)->cash()->create([
            'opening_date' => '2026-01-01',
        ]);

        $this->insertFinancialOperations(
            $user,
            $account,
            $incomeCategory,
            $budget,
            self::FINANCIAL_OPERATIONS_COUNT,
        );

        $this->insertOwnTransfers(
            $user,
            $sourceAccount,
            $destinationAccount,
            self::OWN_TRANSFERS_COUNT,
        );

        return [
            'user' => $user,
            'account' => $account,
            'expenseCategory' => $expenseCategory,
        ];
    }

    private function insertFinancialOperations(
        User $user,
        Account $account,
        Category $category,
        Budget $budget,
        int $count,
    ): void {
        $now = now();
        $rows = [];

        for ($index = 0; $index < $count; $index++) {
            $rows[] = [
                'user_id' => $user->id,
                'type' => FinancialOperationType::Income->value,
                'account_id' => $account->id,
                'category_id' => $category->id,
                'budget_id' => $budget->id,
                'operation_date' => '2026-03-01',
                'amount_minor_units' => 1000 + ($index % 100),
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

    private function insertOwnTransfers(
        User $user,
        Account $sourceAccount,
        Account $destinationAccount,
        int $count,
    ): void {
        $now = now();
        $rows = [];

        for ($index = 0; $index < $count; $index++) {
            $rows[] = [
                'user_id' => $user->id,
                'source_account_id' => $sourceAccount->id,
                'destination_account_id' => $destinationAccount->id,
                'transfer_date' => '2026-03-01',
                'amount_minor_units' => 500 + ($index % 50),
                'description' => "Seeded transfer {$index}",
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) === 500) {
                DB::table('own_transfers')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('own_transfers')->insert($rows);
        }
    }

    private function assertRequestWithinSeconds(callable $request, string $label): void
    {
        $startedAt = microtime(true);

        $request();

        $elapsedSeconds = microtime(true) - $startedAt;

        $this->assertLessThanOrEqual(
            self::MAX_SECONDS,
            $elapsedSeconds,
            "{$label} took {$elapsedSeconds}s; expected ≤ ".self::MAX_SECONDS.'s.',
        );
    }
}

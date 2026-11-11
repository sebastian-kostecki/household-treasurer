<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Budget;
use App\Models\FinancialOperation;
use App\Models\User;
use App\Support\PeriodReport;
use App\Support\ReportComparisonPeriod;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoHouseholdSeeder;
use Database\Seeders\PreviousYearMockSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedPreviousYearCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Budget $source;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-11 12:00:00');
        (new DemoHouseholdSeeder)->run('mock@example.net');
        $this->user = User::query()->where('email', 'mock@example.net')->firstOrFail();
        $this->source = $this->user->budgets()->where('year', 2026)->firstOrFail();
    }

    private function seedMock(): void
    {
        $this->artisan('demo:seed-previous-year', ['user' => $this->user->email])->assertSuccessful();
    }

    private function target(): Budget
    {
        return $this->user->budgets()->where('year', 2025)->firstOrFail();
    }

    /**
     * @return array<int, int>
     */
    private function accountBalances(): array
    {
        return Account::query()->orderBy('id')->pluck('current_balance_minor_units', 'id')->all();
    }

    /**
     * @return array<int, int>
     */
    private function targetSums(): array
    {
        return $this->target()->budgetLines()->orderBy('category_id')->pluck('execution_minor_units', 'category_id')->all();
    }

    public function test_it_builds_the_previous_year_in_execution(): void
    {
        $before = $this->accountBalances();
        $this->seedMock();

        $target = $this->target();
        $this->assertTrue($target->isExecution());
        $this->assertSame($this->source->budgetLines()->count(), $target->budgetLines()->count());

        foreach ($target->budgetLines as $line) {
            $this->assertSame(
                (int) $line->financialOperations()->sum('amount_minor_units'),
                $line->execution_minor_units,
            );
        }

        $operations = $target->financialOperations()->get();
        $this->assertNotEmpty($operations);

        foreach ($operations as $operation) {
            $this->assertSame(2025, $operation->operation_date->year);
            $this->assertSame(PreviousYearMockSeeder::MARKER, $operation->raw_statement_description);
            $this->assertNull($operation->import_identity_account_id);
            $this->assertNull($operation->import_identity_date);
            $this->assertNull($operation->import_identity_amount_minor_units);
            $this->assertNull($operation->import_identity_digest);
        }

        $this->assertSame($before, $this->accountBalances());
    }

    public function test_amounts_stay_within_the_percent_range_with_one_percent_per_category(): void
    {
        $this->seedMock();

        $sourceByCategory = $this->source->financialOperations()->with('budgetLine')->get()
            ->groupBy(fn (FinancialOperation $operation) => $operation->budgetLine->category_id);

        foreach ($sourceByCategory as $categoryId => $operations) {
            $percent = PreviousYearMockSeeder::percentFor((int) $categoryId);
            $this->assertGreaterThanOrEqual(85, $percent);
            $this->assertLessThanOrEqual(115, $percent);

            foreach ($operations as $operation) {
                $mock = $this->target()->financialOperations()
                    ->where('description', $operation->description)
                    ->where('operation_date', $operation->operation_date->subYearNoOverflow()->toDateString())
                    ->where('amount_minor_units', PreviousYearMockSeeder::scale($operation->amount_minor_units, $percent))
                    ->exists();
                $this->assertTrue($mock);
            }
        }
    }

    public function test_scale_rounds_half_away_from_zero_and_keeps_the_sign(): void
    {
        $this->assertSame(1, PreviousYearMockSeeder::scale(1, 50));
        $this->assertSame(-1, PreviousYearMockSeeder::scale(-1, 50));
        $this->assertSame(0, PreviousYearMockSeeder::scale(1, 49));
        $this->assertSame(-1_150, PreviousYearMockSeeder::scale(-1_000, 115));
    }

    public function test_the_year_tail_has_operations_when_the_source_ends_before_december(): void
    {
        $this->seedMock();

        $lastSource = $this->source->financialOperations()->max('operation_date');
        $this->assertLessThan('2026-12-01', $lastSource);

        $tail = $this->target()->financialOperations()->where('operation_date', '>', '2025-12-01')->count();
        $this->assertGreaterThan(0, $tail);
    }

    public function test_it_leaves_transfers_and_capital_movements_untouched(): void
    {
        $transfers = $this->user->ownTransfers()->count();
        $movements = $this->user->capitalMovements()->count();
        $this->seedMock();

        $this->assertSame($transfers, $this->user->ownTransfers()->count());
        $this->assertSame($movements, $this->user->capitalMovements()->count());
    }

    public function test_the_source_period_report_has_a_comparison(): void
    {
        $this->seedMock();

        $today = CarbonImmutable::today('UTC');
        $comparison = ReportComparisonPeriod::resolve($this->source->fresh(), null, $today);
        $report = PeriodReport::forYear($this->source->fresh(), $comparison)->toArray();

        $this->assertNotNull($report['comparison']);
    }

    public function test_it_refuses_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('demo:seed-previous-year', ['user' => $this->user->email])->assertFailed();

        $this->assertFalse($this->user->budgets()->where('year', 2025)->exists());
    }

    public function test_it_refuses_when_the_target_budget_exists(): void
    {
        $this->seedMock();

        $this->artisan('demo:seed-previous-year', ['user' => $this->user->email])->assertFailed();
    }

    public function test_it_refuses_without_a_budget_in_execution(): void
    {
        $user = User::factory()->create();

        $this->artisan('demo:seed-previous-year', ['user' => $user->email])->assertFailed();
    }

    public function test_remove_deletes_the_mock_and_keeps_the_source(): void
    {
        $this->seedMock();
        $sourceOperations = $this->source->financialOperations()->count();

        $this->artisan('demo:seed-previous-year', ['user' => $this->user->email, '--remove' => true, '--force' => true])
            ->assertSuccessful();

        $this->assertFalse($this->user->budgets()->where('year', 2025)->exists());
        $this->assertSame(0, FinancialOperation::query()->where('raw_statement_description', PreviousYearMockSeeder::MARKER)->count());
        $this->assertSame($sourceOperations, $this->source->financialOperations()->count());
    }

    public function test_remove_refuses_a_budget_with_an_unmarked_operation(): void
    {
        $this->seedMock();
        $target = $this->target();
        $operation = $target->financialOperations()->firstOrFail();
        $operation->update(['raw_statement_description' => null]);

        $this->artisan('demo:seed-previous-year', ['user' => $this->user->email, '--remove' => true, '--force' => true])
            ->assertFailed();

        $this->assertTrue($this->user->budgets()->where('year', 2025)->exists());
    }

    public function test_running_again_after_remove_gives_the_same_sums(): void
    {
        $this->seedMock();
        $first = $this->targetSums();

        $this->artisan('demo:seed-previous-year', ['user' => $this->user->email, '--remove' => true, '--force' => true])
            ->assertSuccessful();
        $this->seedMock();

        $this->assertSame($first, $this->targetSums());
    }
}

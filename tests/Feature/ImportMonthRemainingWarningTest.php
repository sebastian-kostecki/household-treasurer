<?php

namespace Tests\Feature;

use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\FinancialOperation;
use App\Models\User;
use App\Support\CsvImport\ImportMonthRemainingWarningAnnotator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ImportMonthRemainingWarningTest extends TestCase
{
    use RefreshDatabase;

    public function test_paced_expense_row_with_negative_after_state_sets_warning_flags(): void
    {
        CarbonImmutable::setTestNow('2026-06-15');

        [$user, $paced] = $this->createMonthRemainingImportFixture();

        $annotator = new ImportMonthRemainingWarningAnnotator($user, CarbonImmutable::today('UTC'));

        $rows = $annotator->annotate([
            [
                'index' => 0,
                'operation_date' => '2026-03-25',
                'amount_minor_units' => -50_000,
                'valid' => true,
                'default_type' => 'expense',
                'default_category_id' => $paced->id,
            ],
        ]);

        $this->assertTrue($rows[0]['show_category_month_remaining_warning']);
        $this->assertTrue($rows[0]['show_pool_month_remaining_warning']);
        $this->assertSame('marzec 2026', $rows[0]['month_remaining_period_label']);
    }

    public function test_later_paced_expense_row_includes_earlier_valid_paced_rows_in_after_state(): void
    {
        CarbonImmutable::setTestNow('2026-06-15');

        [$user, $paced] = $this->createMonthRemainingImportFixture();

        $annotator = new ImportMonthRemainingWarningAnnotator($user, CarbonImmutable::today('UTC'));

        $rows = $annotator->annotate([
            [
                'index' => 0,
                'operation_date' => '2026-03-10',
                'amount_minor_units' => -5_000,
                'valid' => true,
                'default_type' => 'expense',
                'default_category_id' => $paced->id,
            ],
            [
                'index' => 1,
                'operation_date' => '2026-03-20',
                'amount_minor_units' => -50_000,
                'valid' => true,
                'default_type' => 'expense',
                'default_category_id' => $paced->id,
            ],
        ]);

        $this->assertFalse($rows[0]['show_category_month_remaining_warning']);
        $this->assertFalse($rows[0]['show_pool_month_remaining_warning']);
        $this->assertTrue($rows[1]['show_category_month_remaining_warning']);
        $this->assertTrue($rows[1]['show_pool_month_remaining_warning']);
    }

    public function test_one_off_expense_rows_do_not_set_month_remaining_warning_flags(): void
    {
        CarbonImmutable::setTestNow('2026-06-15');

        [$user, $paced, $oneOff] = $this->createMonthRemainingImportFixture();

        $annotator = new ImportMonthRemainingWarningAnnotator($user, CarbonImmutable::today('UTC'));

        $rows = $annotator->annotate([
            [
                'index' => 0,
                'operation_date' => '2026-03-25',
                'amount_minor_units' => -50_000,
                'valid' => true,
                'default_type' => 'expense',
                'default_category_id' => $oneOff->id,
            ],
        ]);

        $this->assertFalse($rows[0]['show_category_month_remaining_warning']);
        $this->assertFalse($rows[0]['show_pool_month_remaining_warning']);
        $this->assertNull($rows[0]['month_remaining_period_label']);
    }

    public function test_income_own_transfer_and_capital_movement_rows_do_not_set_warning_flags(): void
    {
        CarbonImmutable::setTestNow('2026-06-15');

        [$user] = $this->createMonthRemainingImportFixture();

        $annotator = new ImportMonthRemainingWarningAnnotator($user, CarbonImmutable::today('UTC'));

        $rows = $annotator->annotate([
            [
                'index' => 0,
                'operation_date' => '2026-03-25',
                'amount_minor_units' => 50_000,
                'valid' => true,
                'default_type' => 'income',
                'default_category_id' => null,
            ],
            [
                'index' => 1,
                'operation_date' => '2026-03-26',
                'amount_minor_units' => -50_000,
                'valid' => true,
                'default_type' => 'own_transfer',
                'default_category_id' => null,
            ],
            [
                'index' => 2,
                'operation_date' => '2026-03-27',
                'amount_minor_units' => -50_000,
                'valid' => true,
                'default_type' => 'capital_movement',
                'default_category_id' => null,
            ],
        ]);

        $this->assertFalse($rows[0]['show_category_month_remaining_warning']);
        $this->assertFalse($rows[1]['show_category_month_remaining_warning']);
        $this->assertFalse($rows[2]['show_category_month_remaining_warning']);
    }

    public function test_import_preview_exposes_month_remaining_warning_flags_on_paced_expense_rows(): void
    {
        CarbonImmutable::setTestNow('2026-06-15');

        [$user, $account] = $this->createMonthRemainingImportFixture(withAccount: true);

        $this->previewCsv($user, $account, <<<'CSV'
date,description,amount
2026-03-10,Small expense,-50.00
2026-03-20,Large expense,-100.00
CSV);

        $this->actingAs($user)
            ->get(route('operations.import.preview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.show_category_month_remaining_warning', false)
                ->where('rows.0.show_pool_month_remaining_warning', false)
                ->where('rows.1.show_category_month_remaining_warning', true)
                ->where('rows.1.show_pool_month_remaining_warning', true)
                ->where('rows.1.month_remaining_period_label', 'marzec 2026')
                ->has('monthRemainingContext.budgets', 1),
            );
    }

    public function test_import_preview_does_not_flag_income_rows_for_month_remaining(): void
    {
        CarbonImmutable::setTestNow('2026-06-15');

        [$user, $account] = $this->createMonthRemainingImportFixture(withAccount: true);

        $this->previewCsv($user, $account, <<<'CSV'
date,description,amount
2026-03-20,Salary,2500.00
CSV);

        $this->actingAs($user)
            ->get(route('operations.import.preview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.show_category_month_remaining_warning', false)
                ->where('rows.0.show_pool_month_remaining_warning', false),
            );
    }

    /**
     * @return array{0: User, 1?: Account, 2?: Category, 3?: Category}
     */
    private function createMonthRemainingImportFixture(bool $withAccount = false): array
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 1_000_000,
            'current_balance_minor_units' => 1_000_000,
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $paced = Category::factory()->for($budget)->expense()->create([
            'planned_amount_minor_units' => 120_000,
            'execution_minor_units' => 35_000,
        ]);
        $oneOff = Category::factory()->for($budget)->oneOff()->create([
            'planned_amount_minor_units' => 50_000,
            'execution_minor_units' => 10_000,
        ]);

        FinancialOperation::factory()->for($user)->create([
            'type' => FinancialOperationType::Expense,
            'account_id' => $account->id,
            'category_id' => $paced->id,
            'budget_id' => $budget->id,
            'operation_date' => '2026-01-15',
            'amount_minor_units' => 25_000,
        ]);

        if ($withAccount) {
            return [$user, $account, $paced, $oneOff];
        }

        return [$user, $paced, $oneOff];
    }

    private function previewCsv(User $user, Account $account, string $csv): void
    {
        $this->actingAs($user)
            ->post(route('operations.import.preview.store'), [
                'file' => UploadedFile::fake()->createWithContent('operations.csv', $csv),
                'account_id' => $account->id,
                'import_format' => 'other',
                'has_header' => true,
                'date_column' => 0,
                'description_column' => 1,
                'amount_layout' => 'signed',
                'amount_column' => 2,
            ])
            ->assertRedirect(route('operations.import.preview'));
    }
}

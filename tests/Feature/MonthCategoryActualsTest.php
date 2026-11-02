<?php

namespace Tests\Feature;

use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\CapitalMovement;
use App\Models\Category;
use App\Models\FinancialOperation;
use App\Models\OwnTransfer;
use App\Models\TermDeposit;
use App\Models\User;
use App\Support\MonthCategoryActuals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthCategoryActualsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sums_net_of_refunds_and_flags_positive_amounts(): void
    {
        [$user, $budget, $account] = $this->setUpBudget();
        $food = Category::factory()->for($budget)->expense()->create();

        $this->operation($user, $budget, $account, $food, '2026-11-05', 10_000);
        $this->operation($user, $budget, $account, $food, '2026-11-20', -2_500);

        $actuals = MonthCategoryActuals::forMonth($budget, 11);

        $this->assertSame(7_500, $actuals->actual($food->id));
        $this->assertTrue($actuals->hasPositiveAmount($food->id));
        $this->assertSame([$food->id], $actuals->categoryIds());
    }

    public function test_refund_only_category_has_no_positive_amount(): void
    {
        [$user, $budget, $account] = $this->setUpBudget();
        $rent = Category::factory()->for($budget)->bill()->create();

        $this->operation($user, $budget, $account, $rent, '2026-11-10', -10_000);

        $actuals = MonthCategoryActuals::forMonth($budget, 11);

        $this->assertSame(-10_000, $actuals->actual($rent->id));
        $this->assertFalse($actuals->hasPositiveAmount($rent->id));
    }

    public function test_unknown_category_defaults_to_zero(): void
    {
        [, $budget] = $this->setUpBudget();

        $actuals = MonthCategoryActuals::forMonth($budget, 11);

        $this->assertSame(0, $actuals->actual(999));
        $this->assertFalse($actuals->hasPositiveAmount(999));
        $this->assertSame([], $actuals->categoryIds());
    }

    public function test_includes_month_boundaries_and_skips_other_months(): void
    {
        [$user, $budget, $account] = $this->setUpBudget();
        $food = Category::factory()->for($budget)->expense()->create();

        $this->operation($user, $budget, $account, $food, '2026-10-31', 1_000);
        $this->operation($user, $budget, $account, $food, '2026-11-01', 2_000);
        $this->operation($user, $budget, $account, $food, '2026-11-30', 4_000);
        $this->operation($user, $budget, $account, $food, '2026-12-01', 8_000);

        $this->assertSame(6_000, MonthCategoryActuals::forMonth($budget, 11)->actual($food->id));
    }

    public function test_includes_income_categories(): void
    {
        [$user, $budget, $account] = $this->setUpBudget();
        $salary = Category::factory()->for($budget)->income()->create();

        $this->operation($user, $budget, $account, $salary, '2026-11-02', 780_000, FinancialOperationType::Income);

        $actuals = MonthCategoryActuals::forMonth($budget, 11);

        $this->assertSame(780_000, $actuals->actual($salary->id));
        $this->assertTrue($actuals->hasPositiveAmount($salary->id));
    }

    public function test_own_transfers_and_capital_movements_have_no_effect(): void
    {
        [$user, $budget, $account] = $this->setUpBudget();
        $termDeposit = TermDeposit::factory()->for($user)->create();

        OwnTransfer::factory()->for($user)->create([
            'source_account_id' => $account->id,
            'destination_account_id' => Account::factory()->for($user)->create()->id,
            'transfer_date' => '2026-11-10',
        ]);
        CapitalMovement::factory()->for($user)->create([
            'account_id' => $account->id,
            'term_deposit_id' => $termDeposit->id,
            'movement_date' => '2026-11-10',
        ]);

        $this->assertSame([], MonthCategoryActuals::forMonth($budget, 11)->categoryIds());
    }

    public function test_ignores_other_budgets(): void
    {
        [$user, $budget, $account] = $this->setUpBudget();
        $otherBudget = Budget::factory()->for($user)->execution()->create(['year' => 2027]);
        $otherCategory = Category::factory()->for($otherBudget)->expense()->create();

        $this->operation($user, $otherBudget, $account, $otherCategory, '2026-11-10', 5_000);

        $this->assertSame([], MonthCategoryActuals::forMonth($budget, 11)->categoryIds());
    }

    /**
     * @return array{0: User, 1: Budget, 2: Account}
     */
    private function setUpBudget(): array
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_date' => '2026-01-01']);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);

        return [$user, $budget, $account];
    }

    private function operation(
        User $user,
        Budget $budget,
        Account $account,
        Category $category,
        string $date,
        int $amount,
        FinancialOperationType $type = FinancialOperationType::Expense,
    ): void {
        FinancialOperation::factory()->for($user)->for($budget)->create([
            'type' => $type,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'operation_date' => $date,
            'amount_minor_units' => $amount,
        ]);
    }
}

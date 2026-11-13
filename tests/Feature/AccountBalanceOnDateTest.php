<?php

namespace Tests\Feature;

use App\Actions\ManageCapitalMovement;
use App\Actions\ManageOwnTransfer;
use App\Enums\CapitalMovementDirection;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\FinancialOperation;
use App\Models\TermDeposit;
use App\Models\User;
use App\Support\AccountBalanceOnDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AccountBalanceOnDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_balance_on_a_date_counts_only_records_dated_on_or_before_it(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 100000,
            'current_balance_minor_units' => 100000,
        ]);
        $other = Account::factory()->for($user)->create(['opening_date' => '2026-01-01']);
        $termDeposit = TermDeposit::factory()->for($user)->create(['opening_date' => '2026-01-01']);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $incomeLine = BudgetLine::factory()->for($budget)->income()->create();
        $expenseLine = BudgetLine::factory()->for($budget)->expense()->create();

        $this->operation($user, $account, $budget, $incomeLine, 'income', '2026-02-10', 50000);
        $this->operation($user, $account, $budget, $expenseLine, 'expense', '2026-02-20', 12000);
        $this->operation($user, $account, $budget, $expenseLine, 'expense', '2026-02-25', -3000); // refund
        $this->operation($user, $other, $budget, $incomeLine, 'income', '2026-02-12', 999999); // other Account
        $this->operation($user, $account, $budget, $incomeLine, 'income', '2026-03-05', 70000); // later

        app(ManageOwnTransfer::class)->create($user, [
            'source_account_id' => $account->id,
            'destination_account_id' => $other->id,
            'transfer_date' => '2026-02-15',
            'amount_minor_units' => 20000,
            'description' => 'Out',
        ]);
        app(ManageOwnTransfer::class)->create($user, [
            'source_account_id' => $other->id,
            'destination_account_id' => $account->id,
            'transfer_date' => '2026-02-18',
            'amount_minor_units' => 5000,
            'description' => 'In',
        ]);
        app(ManageCapitalMovement::class)->create($user, [
            'account_id' => $account->id,
            'term_deposit_id' => $termDeposit->id,
            'movement_date' => '2026-02-22',
            'amount_minor_units' => 30000,
            'direction' => CapitalMovementDirection::ToTermDeposit,
            'description' => 'Park',
        ]);
        app(ManageCapitalMovement::class)->create($user, [
            'account_id' => $account->id,
            'term_deposit_id' => $termDeposit->id,
            'movement_date' => '2026-02-28',
            'amount_minor_units' => 10000,
            'direction' => CapitalMovementDirection::FromTermDeposit,
            'description' => 'Return',
        ]);

        // Opening, nothing booked yet.
        $this->assertSame(100000, $this->balanceOn($account, '2026-01-01'));
        $this->assertSame(100000, $this->balanceOn($account, '2026-02-09'));
        // The day of a record counts (end of day D).
        $this->assertSame(150000, $this->balanceOn($account, '2026-02-10'));
        $this->assertSame(130000, $this->balanceOn($account, '2026-02-15'));
        $this->assertSame(135000, $this->balanceOn($account, '2026-02-18'));
        $this->assertSame(123000, $this->balanceOn($account, '2026-02-20'));
        $this->assertSame(93000, $this->balanceOn($account, '2026-02-22'));
        $this->assertSame(96000, $this->balanceOn($account, '2026-02-25'));
        $this->assertSame(106000, $this->balanceOn($account, '2026-02-28'));
        $this->assertSame(176000, $this->balanceOn($account, '2026-03-05'));
    }

    public function test_balance_has_no_value_before_the_opening_date(): void
    {
        $account = Account::factory()->create([
            'opening_date' => '2026-01-10',
            'opening_balance_minor_units' => 5000,
            'current_balance_minor_units' => 5000,
        ]);

        $this->assertNull($this->balanceOn($account, '2026-01-09'));
        $this->assertSame(5000, $this->balanceOn($account, '2026-01-10'));
    }

    public function test_balance_on_a_date_after_the_last_record_equals_the_stored_current_balance(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 250000,
            'current_balance_minor_units' => 250000,
        ]);
        $other = Account::factory()->for($user)->create(['opening_date' => '2026-01-01']);
        $termDeposit = TermDeposit::factory()->for($user)->create(['opening_date' => '2026-01-01']);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $incomeLine = BudgetLine::factory()->for($budget)->income()->create();
        $expenseLine = BudgetLine::factory()->for($budget)->expense()->create();

        $this->actingAs($user)->post(route('incomes.store'), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 310000,
            'description' => 'Salary',
            'account_id' => $account->id,
            'category_id' => $incomeLine->category_id,
        ])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('expenses.store'), [
            'operation_date' => '2026-04-02',
            'amount_minor_units' => 45050,
            'description' => 'Shop',
            'account_id' => $account->id,
            'category_id' => $expenseLine->category_id,
        ])->assertSessionHasNoErrors();
        app(ManageOwnTransfer::class)->create($user, [
            'source_account_id' => $account->id,
            'destination_account_id' => $other->id,
            'transfer_date' => '2026-04-10',
            'amount_minor_units' => 80000,
            'description' => 'Cash',
        ]);
        app(ManageCapitalMovement::class)->create($user, [
            'account_id' => $account->id,
            'term_deposit_id' => $termDeposit->id,
            'movement_date' => '2026-05-01',
            'amount_minor_units' => 100000,
            'direction' => CapitalMovementDirection::ToTermDeposit,
            'description' => 'Park',
        ]);

        $account->refresh();
        $other->refresh();

        $this->assertSame(250000 + 310000 - 45050 - 80000 - 100000, $account->current_balance_minor_units);
        $this->assertSame($account->current_balance_minor_units, $this->balanceOn($account, '2026-05-01'));
        $this->assertSame($account->current_balance_minor_units, $this->balanceOn($account, '2026-12-31'));
        $this->assertSame($other->current_balance_minor_units, $this->balanceOn($other, '2026-06-01'));
    }

    private function balanceOn(Account $account, string $date): ?int
    {
        return AccountBalanceOnDate::for($account, Carbon::parse($date));
    }

    private function operation(User $user, Account $account, Budget $budget, BudgetLine $line, string $type, string $date, int $amount): void
    {
        FinancialOperation::factory()->create([
            'user_id' => $user->id,
            'type' => $type,
            'account_id' => $account->id,
            'budget_id' => $budget->id,
            'budget_line_id' => $line->id,
            'operation_date' => $date,
            'amount_minor_units' => $amount,
        ]);
    }
}

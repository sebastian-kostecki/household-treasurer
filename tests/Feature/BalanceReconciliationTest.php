<?php

namespace Tests\Feature;

use App\Actions\ResetUserData;
use App\Models\Account;
use App\Models\BalanceReconciliation;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\FinancialOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BalanceReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_reconcile(): void
    {
        $account = Account::factory()->create();

        $this->post(route('accounts.reconciliations.store', $account), [
            'reconciled_on' => '2026-03-01',
            'bank_balance_minor_units' => 1000,
        ])->assertRedirect(route('login'));

        $this->get(route('accounts.reconciliations.preview', $account))->assertRedirect(route('login'));
    }

    public function test_user_writes_down_the_bank_balance_without_changing_any_balance(): void
    {
        $this->travelTo('2026-06-01');
        [$user, $account] = $this->accountWithIncome();

        $this->actingAs($user)
            ->post(route('accounts.reconciliations.store', $account), [
                'reconciled_on' => '2026-03-31',
                'bank_balance_minor_units' => 130000,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('balance_reconciliations', [
            'user_id' => $user->id,
            'account_id' => $account->id,
            'bank_balance_minor_units' => 130000,
        ]);
        $this->assertSame(1, BalanceReconciliation::query()->count());
        $this->assertSame(0, FinancialOperation::query()->where('description', 'Reconciliation')->count());
        $this->assertSame(130000, $account->refresh()->current_balance_minor_units);
    }

    public function test_saving_the_same_day_again_replaces_the_bank_balance(): void
    {
        $this->travelTo('2026-06-01');
        [$user, $account] = $this->accountWithIncome();

        foreach ([100000, 130000] as $bankBalance) {
            $this->actingAs($user)->post(route('accounts.reconciliations.store', $account), [
                'reconciled_on' => '2026-03-31',
                'bank_balance_minor_units' => $bankBalance,
            ])->assertSessionHasNoErrors();
        }

        $this->assertSame(1, BalanceReconciliation::query()->count());
        $this->assertSame(130000, BalanceReconciliation::query()->firstOrFail()->bank_balance_minor_units);
    }

    public function test_the_bank_balance_may_be_negative_or_zero(): void
    {
        $this->travelTo('2026-06-01');
        [$user, $account] = $this->accountWithIncome();

        $this->actingAs($user)->post(route('accounts.reconciliations.store', $account), [
            'reconciled_on' => '2026-03-31',
            'bank_balance_minor_units' => -2500,
        ])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('accounts.reconciliations.store', $account), [
            'reconciled_on' => '2026-04-30',
            'bank_balance_minor_units' => 0,
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, BalanceReconciliation::query()->count());
    }

    public function test_the_day_cannot_be_in_the_future_or_before_the_opening_date(): void
    {
        $this->travelTo('2026-06-01');
        [$user, $account] = $this->accountWithIncome();

        $this->actingAs($user)
            ->post(route('accounts.reconciliations.store', $account), [
                'reconciled_on' => '2026-06-02',
                'bank_balance_minor_units' => 1,
            ])
            ->assertSessionHasErrors('reconciled_on');

        $this->actingAs($user)
            ->post(route('accounts.reconciliations.store', $account), [
                'reconciled_on' => '2025-12-31',
                'bank_balance_minor_units' => 1,
            ])
            ->assertSessionHasErrors('reconciled_on');

        $this->actingAs($user)
            ->post(route('accounts.reconciliations.store', $account), [
                'reconciled_on' => '2026-06-01',
                'bank_balance_minor_units' => 'abc',
            ])
            ->assertSessionHasErrors('bank_balance_minor_units');

        $this->assertSame(0, BalanceReconciliation::query()->count());
    }

    public function test_a_sheet_account_an_archived_account_and_another_users_account_have_no_reconciliation(): void
    {
        $this->travelTo('2026-06-01');
        $user = User::factory()->create();
        $sheet = Account::factory()->for($user)->sheetAccount(2025)->create();
        $archived = Account::factory()->for($user)->archived()->create(['opening_date' => '2026-01-01']);
        $foreign = Account::factory()->create(['opening_date' => '2026-01-01']);

        foreach ([$sheet, $archived, $foreign] as $account) {
            $this->actingAs($user)
                ->post(route('accounts.reconciliations.store', $account), [
                    'reconciled_on' => '2026-03-01',
                    'bank_balance_minor_units' => 1,
                ])
                ->assertForbidden();
            $this->actingAs($user)
                ->get(route('accounts.reconciliations.preview', ['account' => $account, 'reconciled_on' => '2026-03-01']))
                ->assertForbidden();
        }

        $this->assertSame(0, BalanceReconciliation::query()->count());
    }

    public function test_the_preview_lists_the_operations_after_the_previous_reconciliation(): void
    {
        $this->travelTo('2026-06-01');
        [$user, $account, $budget, $incomeLine] = $this->accountWithIncome();

        $this->operation($user, $account, $budget, $incomeLine, '2026-04-10', 20000, 'April pay');
        $this->operation($user, $account, $budget, $incomeLine, '2026-05-10', 7000, 'May pay');
        $this->operation($user, $account, $budget, $incomeLine, '2026-05-25', 9000, 'After the day');
        BalanceReconciliation::factory()->for($user)->for($account)->create([
            'reconciled_on' => '2026-03-31',
            'bank_balance_minor_units' => 130000,
        ]);

        $this->actingAs($user)
            ->getJson(route('accounts.reconciliations.preview', ['account' => $account, 'reconciled_on' => '2026-05-15']))
            ->assertOk()
            ->assertJsonPath('reconciled_on', '2026-05-15')
            ->assertJsonPath('balance_minor_units', 157000)
            ->assertJsonPath('previous_reconciled_on', '2026-03-31')
            ->assertJsonPath('existing_bank_balance_minor_units', null)
            ->assertJsonPath('operations_count', 2)
            ->assertJsonPath('operations.0.description', 'April pay')
            ->assertJsonPath('operations.0.amount_minor_units', 20000)
            ->assertJsonPath('operations.1.description', 'May pay');
    }

    public function test_the_accounts_page_reads_whether_the_last_reconciliation_still_agrees(): void
    {
        $this->travelTo('2026-06-01');
        [$user, $account, $budget, $incomeLine] = $this->accountWithIncome();

        BalanceReconciliation::factory()->for($user)->for($account)->create([
            'reconciled_on' => '2026-03-31',
            'bank_balance_minor_units' => 130000,
        ]);
        BalanceReconciliation::factory()->for($user)->for($account)->create([
            'reconciled_on' => '2026-02-28',
            'bank_balance_minor_units' => 1,
        ]);

        $this->actingAs($user)->get(route('accounts.index'))->assertInertia(fn (Assert $page) => $page
            ->where('accounts.0.can_reconcile', true)
            ->where('accounts.0.last_reconciliation', [
                'reconciled_on' => '2026-03-31',
                'bank_balance_minor_units' => 130000,
                'balance_minor_units' => 130000,
                'difference_minor_units' => 0,
                'agrees' => true,
            ]),
        );

        // A late operation dated on or before the day turns it into „nie zgadza się”; nothing blocks it.
        $this->operation($user, $account, $budget, $incomeLine, '2026-03-20', 4000, 'Late entry');

        $this->actingAs($user)->get(route('accounts.index'))->assertInertia(fn (Assert $page) => $page
            ->where('accounts.0.last_reconciliation.balance_minor_units', 134000)
            ->where('accounts.0.last_reconciliation.difference_minor_units', -4000)
            ->where('accounts.0.last_reconciliation.agrees', false),
        );
    }

    public function test_an_account_without_reconciliation_has_none_and_reset_removes_them(): void
    {
        $this->travelTo('2026-06-01');
        [$user, $account] = $this->accountWithIncome();

        $this->actingAs($user)->get(route('accounts.index'))->assertInertia(fn (Assert $page) => $page
            ->where('accounts.0.last_reconciliation', null),
        );

        BalanceReconciliation::factory()->for($user)->for($account)->create(['reconciled_on' => '2026-03-31']);
        app(ResetUserData::class)->handle($user);

        $this->assertSame(0, BalanceReconciliation::query()->count());
    }

    /**
     * An Account opened 2026-01-01 with 100,000 that received an income of 30,000 on 2026-03-15.
     *
     * @return array{0: User, 1: Account, 2: Budget, 3: BudgetLine}
     */
    private function accountWithIncome(): array
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 100000,
            'current_balance_minor_units' => 100000,
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $line = BudgetLine::factory()->for($budget)->income()->create();

        $this->actingAs($user)->post(route('incomes.store'), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 30000,
            'description' => 'Salary',
            'account_id' => $account->id,
            'category_id' => $line->category_id,
        ])->assertSessionHasNoErrors();

        return [$user, $account->refresh(), $budget, $line];
    }

    private function operation(User $user, Account $account, Budget $budget, BudgetLine $line, string $date, int $amount, string $description): void
    {
        FinancialOperation::factory()->income()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'budget_id' => $budget->id,
            'budget_line_id' => $line->id,
            'operation_date' => $date,
            'amount_minor_units' => $amount,
            'description' => $description,
        ]);
    }
}

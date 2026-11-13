<?php

namespace Tests\Feature;

use App\Enums\CapitalMovementDirection;
use App\Enums\CategoryType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\CapitalMovement;
use App\Models\FinancialOperation;
use App\Models\OwnTransfer;
use App\Models\TermDeposit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OperationsReturnToListTest extends TestCase
{
    use RefreshDatabase;

    public function test_income_store_update_and_delete_return_to_the_list_they_were_made_from(): void
    {
        [$user, $account, $line] = $this->userWithIncomeLine();
        $list = ['category_id' => 7, 'section' => 'own_transfers', 'own_transfers_page' => 2];
        $expected = route('operations', $list);

        $this->actingAs($user)
            ->post(route('incomes.store', ['list' => $list]), $this->incomePayload($account, $line))
            ->assertSessionHasNoErrors()
            ->assertRedirect($expected);

        $operation = FinancialOperation::query()->firstOrFail();

        $this->actingAs($user)
            ->patch(route('incomes.update', [$operation, 'list' => $list]), $this->incomePayload($account, $line))
            ->assertSessionHasNoErrors()
            ->assertRedirect($expected);

        $this->actingAs($user)
            ->delete(route('incomes.destroy', [$operation, 'list' => $list]))
            ->assertRedirect($expected);
    }

    public function test_the_list_state_does_not_leak_into_the_form_input(): void
    {
        [$user, $accountA, $line] = $this->userWithIncomeLine();
        $accountB = Account::factory()->for($user)->create(['opening_date' => '2026-01-01']);
        $expenseLine = BudgetLine::factory()->for($line->budget)->expense()->create();
        $operation = FinancialOperation::factory()->expense()->create([
            'user_id' => $user->id,
            'account_id' => $accountA->id,
            'budget_id' => $expenseLine->budget_id,
            'budget_line_id' => $expenseLine->id,
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 1000,
        ]);

        $this->actingAs($user)
            ->patch(route('expenses.update', [$operation, 'list' => ['account_id' => $accountA->id]]), [
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 1000,
                'description' => 'Moved',
                'account_id' => $accountB->id,
                'category_id' => $expenseLine->category_id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('operations', ['account_id' => $accountA->id]));

        $this->assertSame($accountB->id, $operation->refresh()->account_id);
    }

    public function test_unknown_keys_and_array_values_under_list_are_dropped(): void
    {
        [$user, $account, $line] = $this->userWithIncomeLine();

        $this->actingAs($user)
            ->post(route('incomes.store', ['list' => [
                'q' => 'czynsz',
                'evil' => 'x',
                'year' => ['2026'],
                'operations_page' => 3,
            ]]), $this->incomePayload($account, $line))
            ->assertRedirect(route('operations', ['q' => 'czynsz', 'operations_page' => 3]));
    }

    public function test_a_scalar_list_value_is_ignored(): void
    {
        [$user, $account, $line] = $this->userWithIncomeLine();

        $this->actingAs($user)
            ->post(route('incomes.store', ['list' => 'section=own_transfers']), $this->incomePayload($account, $line))
            ->assertRedirect(route('operations'));
    }

    public function test_own_transfer_and_capital_movement_delete_carry_the_list(): void
    {
        $user = User::factory()->create();
        $list = ['section' => 'capital_movements', 'capital_movements_page' => 2];
        $account = Account::factory()->for($user)->create(['opening_date' => '2026-01-01']);
        $other = Account::factory()->for($user)->create(['opening_date' => '2026-01-01']);
        $termDeposit = TermDeposit::factory()->for($user)->create(['opening_date' => '2026-01-01']);
        $transfer = OwnTransfer::factory()->for($user)->create([
            'source_account_id' => $account->id,
            'destination_account_id' => $other->id,
            'transfer_date' => '2026-03-01',
        ]);
        $movement = CapitalMovement::factory()->for($user)->create([
            'account_id' => $account->id,
            'term_deposit_id' => $termDeposit->id,
            'movement_date' => '2026-03-01',
        ]);

        $this->actingAs($user)
            ->delete(route('own-transfers.destroy', [$transfer, 'list' => $list]))
            ->assertRedirect(route('operations', $list));

        $this->actingAs($user)
            ->delete(route('capital-movements.destroy', [$movement, 'list' => $list]))
            ->assertRedirect(route('operations', $list));
    }

    public function test_a_conversion_branch_carries_the_list(): void
    {
        $this->travelTo('2026-06-01');
        [$user, $account, $line] = $this->userWithIncomeLine();
        $termDeposit = TermDeposit::factory()->for($user)->create(['opening_date' => '2026-01-01']);
        $operation = FinancialOperation::factory()->income()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'budget_id' => $line->budget_id,
            'budget_line_id' => $line->id,
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 20000,
        ]);
        $list = ['q' => 'lokata', 'operations_page' => 2];

        $this->actingAs($user)
            ->patch(route('incomes.update', [$operation, 'list' => $list]), [
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 20000,
                'description' => 'Lokata return',
                'account_id' => $account->id,
                'term_deposit_id' => $termDeposit->id,
                'direction' => CapitalMovementDirection::FromTermDeposit->value,
                'convert_to_capital_movement' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('operations', $list));
    }

    public function test_a_page_past_the_end_falls_back_to_the_last_page_of_the_active_section(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('operations', ['operations_page' => 9, 'q' => 'x', 'own_transfers_page' => 4]))
            ->assertRedirect(route('operations', [
                'q' => 'x',
                'operations_page' => 1,
                'own_transfers_page' => 4,
                'section' => 'financial_operations',
            ]));

        $this->actingAs($user)
            ->get(route('operations', ['section' => 'own_transfers', 'own_transfers_page' => 9]))
            ->assertRedirect(route('operations', ['own_transfers_page' => 1, 'section' => 'own_transfers']));

        $this->actingAs($user)
            ->get(route('operations', ['section' => 'own_transfers']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Operations'));
    }

    public function test_category_writes_keep_show_archived(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        $line = BudgetLine::factory()->for($budget)->expense()->create();
        $expected = route('budget', ['show_archived' => 1]);

        $this->actingAs($user)
            ->post(route('categories.store', [$budget, 'show_archived' => 1]), $this->categoryFields([
                'name' => 'Nowa',
                'type' => CategoryType::Expense->value,
                'planned_amount_minor_units' => 100,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect($expected);

        $this->actingAs($user)
            ->patch(route('categories.update', [$budget, $line->category_id, 'show_archived' => 1]), $this->categoryFields([
                'name' => 'Zmieniona',
                'type' => CategoryType::Expense->value,
                'planned_amount_minor_units' => 100,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect($expected);

        $this->actingAs($user)
            ->delete(route('categories.destroy', [$budget, $line->category_id, 'show_archived' => 1]))
            ->assertRedirect($expected);

        $this->actingAs($user)
            ->post(route('categories.store', $budget), $this->categoryFields([
                'name' => 'Bez flagi',
                'type' => CategoryType::Expense->value,
                'planned_amount_minor_units' => 100,
            ]))
            ->assertRedirect(route('budget'));
    }

    /**
     * @return array{0: User, 1: Account, 2: BudgetLine}
     */
    private function userWithIncomeLine(): array
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 100000,
            'current_balance_minor_units' => 100000,
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $line = BudgetLine::factory()->for($budget)->income()->create();

        return [$user, $account, $line];
    }

    /**
     * @return array<string, mixed>
     */
    private function incomePayload(Account $account, BudgetLine $line): array
    {
        return [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 10000,
            'description' => 'Salary',
            'account_id' => $account->id,
            'category_id' => $line->category_id,
        ];
    }
}

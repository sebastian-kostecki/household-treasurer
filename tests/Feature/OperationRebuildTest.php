<?php

namespace Tests\Feature;

use App\Enums\CapitalMovementDirection;
use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\CapitalMovement;
use App\Models\FinancialOperation;
use App\Models\OwnTransfer;
use App\Models\TermDeposit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationRebuildTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_rebuild_income_into_capital_movement(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 100000,
            'current_balance_minor_units' => 100000,
        ]);
        $termDeposit = TermDeposit::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 50000,
            'current_balance_minor_units' => 50000,
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $incomeCategory = BudgetLine::factory()->for($budget)->income()->create([
            'execution_minor_units' => 0,
        ]);

        $this->actingAs($user)->post(route('incomes.store'), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 20000,
            'description' => 'Misclassified lokata return',
            'account_id' => $account->id,
            'category_id' => $incomeCategory->id,
        ])->assertSessionHasNoErrors();

        $operation = FinancialOperation::query()->firstOrFail();
        $account->refresh();
        $incomeCategory->refresh();

        $this->assertSame(120000, $account->current_balance_minor_units);
        $this->assertSame(20000, $incomeCategory->execution_minor_units);

        $this->actingAs($user)->patch(route('incomes.update', $operation), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 20000,
            'description' => 'Lokata return',
            'account_id' => $account->id,
            'term_deposit_id' => $termDeposit->id,
            'direction' => CapitalMovementDirection::FromTermDeposit->value,
            'convert_to_capital_movement' => true,
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('operations'));

        $this->assertDatabaseMissing('financial_operations', ['id' => $operation->id]);
        $this->assertDatabaseHas('capital_movements', [
            'user_id' => $user->id,
            'account_id' => $account->id,
            'term_deposit_id' => $termDeposit->id,
            'amount_minor_units' => 20000,
            'direction' => CapitalMovementDirection::FromTermDeposit->value,
            'description' => 'Lokata return',
        ]);

        $account->refresh();
        $termDeposit->refresh();
        $incomeCategory->refresh();

        $this->assertSame(120000, $account->current_balance_minor_units);
        $this->assertSame(30000, $termDeposit->current_balance_minor_units);
        $this->assertSame(0, $incomeCategory->execution_minor_units);
    }

    public function test_user_can_rebuild_expense_into_capital_movement(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 100000,
            'current_balance_minor_units' => 100000,
        ]);
        $termDeposit = TermDeposit::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 50000,
            'current_balance_minor_units' => 50000,
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $expenseCategory = BudgetLine::factory()->for($budget)->expense()->create([
            'execution_minor_units' => 0,
        ]);

        $this->actingAs($user)->post(route('expenses.store'), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 15000,
            'description' => 'Misclassified lokata funding',
            'account_id' => $account->id,
            'category_id' => $expenseCategory->id,
        ])->assertSessionHasNoErrors();

        $operation = FinancialOperation::query()->firstOrFail();

        $this->actingAs($user)->patch(route('expenses.update', $operation), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 15000,
            'description' => 'Funding lokata',
            'account_id' => $account->id,
            'term_deposit_id' => $termDeposit->id,
            'direction' => CapitalMovementDirection::ToTermDeposit->value,
            'convert_to_capital_movement' => true,
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('operations'));

        $this->assertDatabaseMissing('financial_operations', ['id' => $operation->id]);
        $this->assertDatabaseHas('capital_movements', [
            'amount_minor_units' => 15000,
            'direction' => CapitalMovementDirection::ToTermDeposit->value,
        ]);

        $account->refresh();
        $termDeposit->refresh();
        $expenseCategory->refresh();

        $this->assertSame(85000, $account->current_balance_minor_units);
        $this->assertSame(65000, $termDeposit->current_balance_minor_units);
        $this->assertSame(0, $expenseCategory->execution_minor_units);
    }

    public function test_user_can_rebuild_own_transfer_into_capital_movement(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $source = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 100000,
            'current_balance_minor_units' => 100000,
        ]);
        $destination = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 0,
            'current_balance_minor_units' => 0,
        ]);
        $termDeposit = TermDeposit::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 50000,
            'current_balance_minor_units' => 50000,
        ]);

        $this->actingAs($user)->post(route('own-transfers.store'), [
            'transfer_date' => '2026-03-15',
            'amount_minor_units' => 25000,
            'description' => 'Misclassified lokata funding',
            'source_account_id' => $source->id,
            'destination_account_id' => $destination->id,
        ])->assertSessionHasNoErrors();

        $transfer = OwnTransfer::query()->firstOrFail();

        $this->actingAs($user)->patch(route('own-transfers.update', $transfer), [
            'transfer_date' => '2026-03-15',
            'amount_minor_units' => 25000,
            'description' => 'Funding lokata',
            'account_id' => $source->id,
            'term_deposit_id' => $termDeposit->id,
            'direction' => CapitalMovementDirection::ToTermDeposit->value,
            'convert_to_capital_movement' => true,
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('operations'));

        $this->assertDatabaseMissing('own_transfers', ['id' => $transfer->id]);
        $this->assertDatabaseHas('capital_movements', [
            'account_id' => $source->id,
            'term_deposit_id' => $termDeposit->id,
            'amount_minor_units' => 25000,
            'direction' => CapitalMovementDirection::ToTermDeposit->value,
        ]);

        $source->refresh();
        $destination->refresh();
        $termDeposit->refresh();

        $this->assertSame(75000, $source->current_balance_minor_units);
        $this->assertSame(0, $destination->current_balance_minor_units);
        $this->assertSame(75000, $termDeposit->current_balance_minor_units);
    }

    public function test_user_can_rebuild_capital_movement_into_income(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 100000,
            'current_balance_minor_units' => 100000,
        ]);
        $termDeposit = TermDeposit::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 50000,
            'current_balance_minor_units' => 50000,
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $incomeCategory = BudgetLine::factory()->for($budget)->income()->create([
            'execution_minor_units' => 0,
        ]);
        $movement = CapitalMovement::factory()->for($user)->create([
            'account_id' => $account->id,
            'term_deposit_id' => $termDeposit->id,
            'movement_date' => '2026-03-15',
            'amount_minor_units' => 20000,
            'direction' => CapitalMovementDirection::FromTermDeposit,
            'description' => 'Misclassified as lokata return',
        ]);

        $account->update(['current_balance_minor_units' => 120000]);
        $termDeposit->update(['current_balance_minor_units' => 30000]);

        $this->actingAs($user)->patch(route('capital-movements.update', $movement), [
            'movement_date' => '2026-03-15',
            'amount_minor_units' => 20000,
            'description' => 'Actually salary',
            'account_id' => $account->id,
            'category_id' => $incomeCategory->id,
            'convert_to_income' => true,
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('operations'));

        $this->assertDatabaseMissing('capital_movements', ['id' => $movement->id]);
        $this->assertDatabaseHas('financial_operations', [
            'user_id' => $user->id,
            'type' => FinancialOperationType::Income->value,
            'account_id' => $account->id,
            'budget_line_id' => $incomeCategory->id,
            'amount_minor_units' => 20000,
            'description' => 'Actually salary',
        ]);

        $account->refresh();
        $termDeposit->refresh();
        $incomeCategory->refresh();

        $this->assertSame(120000, $account->current_balance_minor_units);
        $this->assertSame(50000, $termDeposit->current_balance_minor_units);
        $this->assertSame(20000, $incomeCategory->execution_minor_units);
    }

    public function test_user_can_rebuild_capital_movement_into_expense(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 100000,
            'current_balance_minor_units' => 100000,
        ]);
        $termDeposit = TermDeposit::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 50000,
            'current_balance_minor_units' => 50000,
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $expenseCategory = BudgetLine::factory()->for($budget)->expense()->create([
            'execution_minor_units' => 0,
        ]);
        $movement = CapitalMovement::factory()->for($user)->create([
            'account_id' => $account->id,
            'term_deposit_id' => $termDeposit->id,
            'movement_date' => '2026-03-15',
            'amount_minor_units' => 15000,
            'direction' => CapitalMovementDirection::ToTermDeposit,
            'description' => 'Misclassified lokata funding',
        ]);

        $account->update(['current_balance_minor_units' => 85000]);
        $termDeposit->update(['current_balance_minor_units' => 65000]);

        $this->actingAs($user)->patch(route('capital-movements.update', $movement), [
            'movement_date' => '2026-03-15',
            'amount_minor_units' => 15000,
            'description' => 'Actually groceries',
            'account_id' => $account->id,
            'category_id' => $expenseCategory->id,
            'convert_to_expense' => true,
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('operations'));

        $this->assertDatabaseMissing('capital_movements', ['id' => $movement->id]);
        $this->assertDatabaseHas('financial_operations', [
            'type' => FinancialOperationType::Expense->value,
            'amount_minor_units' => 15000,
        ]);

        $account->refresh();
        $termDeposit->refresh();
        $expenseCategory->refresh();

        $this->assertSame(85000, $account->current_balance_minor_units);
        $this->assertSame(50000, $termDeposit->current_balance_minor_units);
        $this->assertSame(15000, $expenseCategory->execution_minor_units);
    }

    public function test_user_can_rebuild_capital_movement_into_own_transfer(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $source = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 100000,
            'current_balance_minor_units' => 100000,
        ]);
        $destination = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 0,
            'current_balance_minor_units' => 0,
        ]);
        $termDeposit = TermDeposit::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 50000,
            'current_balance_minor_units' => 50000,
        ]);
        $movement = CapitalMovement::factory()->for($user)->create([
            'account_id' => $source->id,
            'term_deposit_id' => $termDeposit->id,
            'movement_date' => '2026-03-15',
            'amount_minor_units' => 25000,
            'direction' => CapitalMovementDirection::ToTermDeposit,
            'description' => 'Misclassified transfer',
        ]);

        $source->update(['current_balance_minor_units' => 75000]);
        $termDeposit->update(['current_balance_minor_units' => 75000]);

        $this->actingAs($user)->patch(route('capital-movements.update', $movement), [
            'movement_date' => '2026-03-15',
            'amount_minor_units' => 25000,
            'description' => 'ATM withdrawal',
            'account_id' => $source->id,
            'other_account_id' => $destination->id,
            'convert_to_own_transfer' => true,
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('operations'));

        $this->assertDatabaseMissing('capital_movements', ['id' => $movement->id]);
        $this->assertDatabaseHas('own_transfers', [
            'source_account_id' => $source->id,
            'destination_account_id' => $destination->id,
            'amount_minor_units' => 25000,
            'description' => 'ATM withdrawal',
        ]);

        $source->refresh();
        $destination->refresh();
        $termDeposit->refresh();

        $this->assertSame(75000, $source->current_balance_minor_units);
        $this->assertSame(25000, $destination->current_balance_minor_units);
        $this->assertSame(50000, $termDeposit->current_balance_minor_units);
    }

    public function test_rebuilding_import_born_income_preserves_import_identity_fields(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);
        $termDeposit = TermDeposit::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $incomeCategory = BudgetLine::factory()->for($budget)->income()->create();

        $operation = FinancialOperation::factory()
            ->for($user)
            ->for($account)
            ->for($budget)
            ->for($incomeCategory)
            ->income()
            ->create([
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 20000,
                'description' => 'Imported lokata return',
                'raw_statement_description' => 'LOKATA ZWROT',
                'import_identity_account_id' => $account->id,
                'import_identity_date' => '2026-03-15',
                'import_identity_amount_minor_units' => 20000,
                'import_identity_digest' => 'digest-lokata-return',
            ]);

        $this->actingAs($user)->patch(route('incomes.update', $operation), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 20000,
            'description' => 'Lokata return',
            'account_id' => $account->id,
            'term_deposit_id' => $termDeposit->id,
            'direction' => CapitalMovementDirection::FromTermDeposit->value,
            'convert_to_capital_movement' => true,
        ])->assertSessionHasNoErrors();

        $movement = CapitalMovement::query()->firstOrFail();

        $this->assertSame('LOKATA ZWROT', $movement->raw_statement_description);
        $this->assertSame($account->id, $movement->import_identity_account_id);
        $this->assertSame('2026-03-15', $movement->import_identity_date->toDateString());
        $this->assertSame(20000, $movement->import_identity_amount_minor_units);
        $this->assertSame('digest-lokata-return', $movement->import_identity_digest);
    }

    public function test_rebuilding_capital_movement_into_income_preserves_import_identity_fields(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);
        $termDeposit = TermDeposit::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $incomeCategory = BudgetLine::factory()->for($budget)->income()->create();

        $movement = CapitalMovement::factory()->for($user)->create([
            'account_id' => $account->id,
            'term_deposit_id' => $termDeposit->id,
            'movement_date' => '2026-03-15',
            'amount_minor_units' => 20000,
            'direction' => CapitalMovementDirection::FromTermDeposit,
            'description' => 'Imported lokata return',
            'raw_statement_description' => 'LOKATA ZWROT',
            'import_identity_account_id' => $account->id,
            'import_identity_date' => '2026-03-15',
            'import_identity_amount_minor_units' => 20000,
            'import_identity_digest' => 'digest-lokata-return',
        ]);

        $this->actingAs($user)->patch(route('capital-movements.update', $movement), [
            'movement_date' => '2026-03-15',
            'amount_minor_units' => 20000,
            'description' => 'Actually salary',
            'account_id' => $account->id,
            'category_id' => $incomeCategory->id,
            'convert_to_income' => true,
        ])->assertSessionHasNoErrors();

        $operation = FinancialOperation::query()->firstOrFail();

        $this->assertSame('LOKATA ZWROT', $operation->raw_statement_description);
        $this->assertSame($account->id, $operation->import_identity_account_id);
        $this->assertSame('2026-03-15', $operation->import_identity_date->toDateString());
        $this->assertSame(20000, $operation->import_identity_amount_minor_units);
        $this->assertSame('digest-lokata-return', $operation->import_identity_digest);
    }

    public function test_capital_movement_cannot_be_rebuilt_into_income_when_direction_is_to_term_deposit(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);
        $termDeposit = TermDeposit::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $incomeCategory = BudgetLine::factory()->for($budget)->income()->create();
        $movement = CapitalMovement::factory()->for($user)->create([
            'account_id' => $account->id,
            'term_deposit_id' => $termDeposit->id,
            'direction' => CapitalMovementDirection::ToTermDeposit,
        ]);

        $this->actingAs($user)
            ->from(route('operations'))
            ->patch(route('capital-movements.update', $movement), [
                'movement_date' => '2026-03-15',
                'amount_minor_units' => 10000,
                'description' => 'Hack',
                'account_id' => $account->id,
                'category_id' => $incomeCategory->id,
                'convert_to_income' => true,
            ])
            ->assertSessionHasErrors('convert_to_income');

        $this->assertDatabaseHas('capital_movements', ['id' => $movement->id]);
    }

    public function test_capital_movement_cannot_be_rebuilt_into_expense_when_direction_is_from_term_deposit(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);
        $termDeposit = TermDeposit::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $expenseCategory = BudgetLine::factory()->for($budget)->expense()->create();
        $movement = CapitalMovement::factory()->for($user)->create([
            'account_id' => $account->id,
            'term_deposit_id' => $termDeposit->id,
            'direction' => CapitalMovementDirection::FromTermDeposit,
        ]);

        $this->actingAs($user)
            ->from(route('operations'))
            ->patch(route('capital-movements.update', $movement), [
                'movement_date' => '2026-03-15',
                'amount_minor_units' => 10000,
                'description' => 'Hack',
                'account_id' => $account->id,
                'category_id' => $expenseCategory->id,
                'convert_to_expense' => true,
            ])
            ->assertSessionHasErrors('convert_to_expense');

        $this->assertDatabaseHas('capital_movements', ['id' => $movement->id]);
    }

    public function test_rebuild_rejects_interest_split_fields_on_edit(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);
        $termDeposit = TermDeposit::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $incomeCategory = BudgetLine::factory()->for($budget)->income()->create();
        $operation = FinancialOperation::factory()
            ->for($user)
            ->for($account)
            ->for($budget)
            ->for($incomeCategory)
            ->income()
            ->create();

        $this->actingAs($user)
            ->from(route('operations'))
            ->patch(route('incomes.update', $operation), [
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 20000,
                'description' => 'Split attempt',
                'account_id' => $account->id,
                'term_deposit_id' => $termDeposit->id,
                'direction' => CapitalMovementDirection::FromTermDeposit->value,
                'convert_to_capital_movement' => true,
                'interest_amount_minor_units' => 5000,
                'interest_category_id' => $incomeCategory->id,
            ])
            ->assertSessionHasErrors('interest_amount_minor_units');
    }

    public function test_other_user_cannot_rebuild_operations(): void
    {
        $this->travelTo('2026-06-01');

        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $account = Account::factory()->for($owner)->create([
            'opening_date' => '2026-01-01',
        ]);
        $termDeposit = TermDeposit::factory()->for($owner)->create([
            'opening_date' => '2026-01-01',
        ]);
        $budget = Budget::factory()->for($owner)->execution()->create(['year' => 2026]);
        $incomeCategory = BudgetLine::factory()->for($budget)->income()->create();
        $expenseCategory = BudgetLine::factory()->for($budget)->expense()->create();

        $this->actingAs($owner)->post(route('incomes.store'), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 20000,
            'description' => 'Income',
            'account_id' => $account->id,
            'category_id' => $incomeCategory->id,
        ])->assertSessionHasNoErrors();

        $income = FinancialOperation::query()->firstOrFail();
        $movement = CapitalMovement::factory()->for($owner)->create([
            'account_id' => $account->id,
            'term_deposit_id' => $termDeposit->id,
            'direction' => CapitalMovementDirection::FromTermDeposit,
        ]);

        $this->actingAs($otherUser)
            ->patch(route('incomes.update', $income), [
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 20000,
                'description' => 'Hack',
                'account_id' => $account->id,
                'term_deposit_id' => $termDeposit->id,
                'direction' => CapitalMovementDirection::FromTermDeposit->value,
                'convert_to_capital_movement' => true,
            ])
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->patch(route('capital-movements.update', $movement), [
                'movement_date' => '2026-03-15',
                'amount_minor_units' => 20000,
                'description' => 'Hack',
                'account_id' => $account->id,
                'category_id' => $incomeCategory->id,
                'convert_to_income' => true,
            ])
            ->assertForbidden();
    }

    public function test_create_income_cannot_store_capital_movement_conversion(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);
        $termDeposit = TermDeposit::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $incomeCategory = BudgetLine::factory()->for($budget)->income()->create();

        $this->actingAs($user)
            ->from(route('operations'))
            ->post(route('incomes.store'), [
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 20000,
                'description' => 'Attempt',
                'account_id' => $account->id,
                'category_id' => $incomeCategory->id,
                'term_deposit_id' => $termDeposit->id,
                'direction' => CapitalMovementDirection::FromTermDeposit->value,
                'convert_to_capital_movement' => true,
            ])
            ->assertSessionHasErrors('convert_to_capital_movement');
    }
}

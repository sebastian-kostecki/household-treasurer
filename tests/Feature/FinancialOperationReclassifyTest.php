<?php

namespace Tests\Feature;

use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\FinancialOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialOperationReclassifyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_reclassify_income_into_expense_refund(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 100000,
            'current_balance_minor_units' => 100000,
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $incomeCategory = BudgetLine::factory()->for($budget)->income()->create([
            'execution_minor_units' => 0,
        ]);
        $expenseCategory = BudgetLine::factory()->for($budget)->expense()->create([
            'planned_amount_minor_units' => 200000,
            'execution_minor_units' => 20000,
        ]);

        $this->actingAs($user)->post(route('incomes.store'), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 15000,
            'description' => 'Shop return parked as income',
            'account_id' => $account->id,
            'category_id' => $incomeCategory->category_id,
        ])->assertSessionHasNoErrors();

        $operation = FinancialOperation::query()->firstOrFail();
        $account->refresh();
        $incomeCategory->refresh();
        $expenseCategory->refresh();

        $this->assertSame(115000, $account->current_balance_minor_units);
        $this->assertSame(15000, $incomeCategory->execution_minor_units);
        $this->assertSame(20000, $expenseCategory->execution_minor_units);

        $this->actingAs($user)->patch(route('incomes.update', $operation), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 15000,
            'description' => 'Shop return refund',
            'account_id' => $account->id,
            'category_id' => $expenseCategory->category_id,
            'convert_to_expense_refund' => true,
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('operations'));

        $operation->refresh();
        $account->refresh();
        $incomeCategory->refresh();
        $expenseCategory->refresh();

        $this->assertSame(FinancialOperationType::Expense, $operation->type);
        $this->assertSame(-15000, $operation->amount_minor_units);
        $this->assertSame($expenseCategory->id, $operation->budget_line_id);
        $this->assertSame(115000, $account->current_balance_minor_units);
        $this->assertSame(0, $incomeCategory->execution_minor_units);
        $this->assertSame(5000, $expenseCategory->execution_minor_units);
    }

    public function test_user_can_reclassify_expense_refund_back_to_income(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
            'opening_balance_minor_units' => 100000,
            'current_balance_minor_units' => 100000,
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $incomeCategory = BudgetLine::factory()->for($budget)->income()->create([
            'execution_minor_units' => 0,
        ]);
        $expenseCategory = BudgetLine::factory()->for($budget)->expense()->create([
            'planned_amount_minor_units' => 200000,
            'execution_minor_units' => 5000,
        ]);

        $this->actingAs($user)->post(route('expenses.store'), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => -15000,
            'description' => 'Refund',
            'account_id' => $account->id,
            'category_id' => $expenseCategory->category_id,
        ])->assertSessionHasNoErrors();

        $operation = FinancialOperation::query()->firstOrFail();

        $this->actingAs($user)->patch(route('expenses.update', $operation), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 15000,
            'description' => 'Actually salary',
            'account_id' => $account->id,
            'category_id' => $incomeCategory->category_id,
            'convert_to_income' => true,
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('operations'));

        $operation->refresh();
        $account->refresh();
        $incomeCategory->refresh();
        $expenseCategory->refresh();

        $this->assertSame(FinancialOperationType::Income, $operation->type);
        $this->assertSame(15000, $operation->amount_minor_units);
        $this->assertSame($incomeCategory->id, $operation->budget_line_id);
        $this->assertSame(115000, $account->current_balance_minor_units);
        $this->assertSame(15000, $incomeCategory->execution_minor_units);
        $this->assertSame(5000, $expenseCategory->execution_minor_units);
    }

    public function test_reclassifying_import_born_income_preserves_import_identity_fields(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $incomeCategory = BudgetLine::factory()->for($budget)->income()->create();
        $expenseCategory = BudgetLine::factory()->for($budget)->expense()->create();

        $operation = FinancialOperation::factory()
            ->for($user)
            ->for($account)
            ->for($budget)
            ->for($incomeCategory)
            ->income()
            ->create([
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 15000,
                'description' => 'Imported shop return',
                'raw_statement_description' => 'C&A RETURN',
                'import_identity_account_id' => $account->id,
                'import_identity_date' => '2026-03-15',
                'import_identity_amount_minor_units' => 15000,
                'import_identity_digest' => 'digest-shop-return',
            ]);

        $this->actingAs($user)->patch(route('incomes.update', $operation), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 15000,
            'description' => 'Shop return refund',
            'account_id' => $account->id,
            'category_id' => $expenseCategory->category_id,
            'convert_to_expense_refund' => true,
        ])->assertSessionHasNoErrors();

        $operation->refresh();

        $this->assertSame('C&A RETURN', $operation->raw_statement_description);
        $this->assertSame($account->id, $operation->import_identity_account_id);
        $this->assertSame('2026-03-15', $operation->import_identity_date->toDateString());
        $this->assertSame(15000, $operation->import_identity_amount_minor_units);
        $this->assertSame('digest-shop-return', $operation->import_identity_digest);
    }

    public function test_reclassify_without_matching_destination_category_is_rejected(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);
        $budget2026 = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $budget2025 = Budget::factory()->for($user)->execution()->create(['year' => 2025]);
        $incomeCategory = BudgetLine::factory()->for($budget2026)->income()->create([
            'execution_minor_units' => 0,
        ]);
        $expenseCategory2025 = BudgetLine::factory()->for($budget2025)->expense()->create();

        $this->actingAs($user)->post(route('incomes.store'), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 15000,
            'description' => 'Shop return',
            'account_id' => $account->id,
            'category_id' => $incomeCategory->category_id,
        ])->assertSessionHasNoErrors();

        $operation = FinancialOperation::query()->firstOrFail();

        $this->actingAs($user)
            ->from(route('operations'))
            ->patch(route('incomes.update', $operation), [
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 15000,
                'description' => 'Shop return',
                'account_id' => $account->id,
                'category_id' => $expenseCategory2025->category_id,
                'convert_to_expense_refund' => true,
            ])
            ->assertSessionHasErrors('category_id');

        $operation->refresh();
        $incomeCategory->refresh();

        $this->assertSame(FinancialOperationType::Income, $operation->type);
        $this->assertSame(15000, $operation->amount_minor_units);
        $this->assertSame($incomeCategory->id, $operation->budget_line_id);
        $this->assertSame(15000, $incomeCategory->execution_minor_units);
    }

    public function test_income_cannot_be_reclassified_into_positive_expense(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $incomeCategory = BudgetLine::factory()->for($budget)->income()->create();
        $expenseCategory = BudgetLine::factory()->for($budget)->expense()->create();

        $this->actingAs($user)->post(route('incomes.store'), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 15000,
            'description' => 'Shop return',
            'account_id' => $account->id,
            'category_id' => $incomeCategory->category_id,
        ])->assertSessionHasNoErrors();

        $operation = FinancialOperation::query()->firstOrFail();

        $this->actingAs($user)
            ->from(route('operations'))
            ->patch(route('incomes.update', $operation), [
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 15000,
                'description' => 'Shop return',
                'account_id' => $account->id,
                'category_id' => $expenseCategory->category_id,
            ])
            ->assertSessionHasErrors('category_id');
    }

    public function test_positive_expense_cannot_be_reclassified_into_income(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $incomeCategory = BudgetLine::factory()->for($budget)->income()->create();
        $expenseCategory = BudgetLine::factory()->for($budget)->expense()->create();

        $this->actingAs($user)->post(route('expenses.store'), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 15000,
            'description' => 'Groceries',
            'account_id' => $account->id,
            'category_id' => $expenseCategory->category_id,
        ])->assertSessionHasNoErrors();

        $operation = FinancialOperation::query()->firstOrFail();

        $this->actingAs($user)
            ->from(route('operations'))
            ->patch(route('expenses.update', $operation), [
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 15000,
                'description' => 'Groceries',
                'account_id' => $account->id,
                'category_id' => $incomeCategory->category_id,
                'convert_to_income' => true,
            ])
            ->assertSessionHasErrors('convert_to_income');
    }

    public function test_other_user_cannot_reclassify_financial_operation(): void
    {
        $this->travelTo('2026-06-01');

        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $account = Account::factory()->for($owner)->create([
            'opening_date' => '2026-01-01',
        ]);
        $budget = Budget::factory()->for($owner)->execution()->create(['year' => 2026]);
        $incomeCategory = BudgetLine::factory()->for($budget)->income()->create();
        $expenseCategory = BudgetLine::factory()->for($budget)->expense()->create();

        $this->actingAs($owner)->post(route('incomes.store'), [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 15000,
            'description' => 'Shop return',
            'account_id' => $account->id,
            'category_id' => $incomeCategory->category_id,
        ])->assertSessionHasNoErrors();

        $this->actingAs($owner)->post(route('expenses.store'), [
            'operation_date' => '2026-03-16',
            'amount_minor_units' => -10000,
            'description' => 'Refund',
            'account_id' => $account->id,
            'category_id' => $expenseCategory->category_id,
        ])->assertSessionHasNoErrors();

        $income = FinancialOperation::query()->where('type', FinancialOperationType::Income)->firstOrFail();
        $refund = FinancialOperation::query()->where('type', FinancialOperationType::Expense)->firstOrFail();

        $this->actingAs($otherUser)
            ->patch(route('incomes.update', $income), [
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 15000,
                'description' => 'Hack',
                'account_id' => $account->id,
                'category_id' => $expenseCategory->category_id,
                'convert_to_expense_refund' => true,
            ])
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->patch(route('expenses.update', $refund), [
                'operation_date' => '2026-03-16',
                'amount_minor_units' => 10000,
                'description' => 'Hack',
                'account_id' => $account->id,
                'category_id' => $incomeCategory->category_id,
                'convert_to_income' => true,
            ])
            ->assertForbidden();
    }

    public function test_create_income_cannot_store_expense_refund_conversion(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $expenseCategory = BudgetLine::factory()->for($budget)->expense()->create();

        $this->actingAs($user)
            ->from(route('operations'))
            ->post(route('incomes.store'), [
                'operation_date' => '2026-03-15',
                'amount_minor_units' => 15000,
                'description' => 'Refund attempt',
                'account_id' => $account->id,
                'category_id' => $expenseCategory->category_id,
                'convert_to_expense_refund' => true,
            ])
            ->assertSessionHasErrors('convert_to_expense_refund');
    }
}

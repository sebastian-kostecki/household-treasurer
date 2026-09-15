<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\BudgetPhase;
use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\FinancialOperation;
use App\Models\OwnTransfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OperationsIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_operations_page_exposes_account_identity_on_financial_operations(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'name' => 'mBank konto',
            'type' => AccountType::Bank,
            'bank' => Bank::Mbank,
            'opening_date' => '2026-01-01',
        ]);
        $budget = Budget::factory()->for($user)->create([
            'year' => 2026,
            'phase' => BudgetPhase::Execution,
        ]);
        $category = Category::factory()->for($budget)->income()->create();

        FinancialOperation::factory()->create([
            'user_id' => $user->id,
            'type' => FinancialOperationType::Income,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'budget_id' => $budget->id,
            'description' => 'Salary',
        ]);

        $this->actingAs($user)
            ->get(route('operations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Operations')
                ->has('operations.data', 1)
                ->where('operations.data.0.account_name', 'mBank konto')
                ->where('operations.data.0.account_type', AccountType::Bank->value)
                ->where('operations.data.0.account_bank', Bank::Mbank->value)
                ->where('operations.data.0.account_mark_url', url('/marks/mbank.jpg')),
            );
    }

    public function test_operations_page_exposes_account_identity_on_own_transfers(): void
    {
        $user = User::factory()->create();
        $source = Account::factory()->for($user)->create([
            'name' => 'Gotówka',
            'type' => AccountType::Cash,
            'bank' => null,
            'opening_date' => '2026-01-01',
        ]);
        $destination = Account::factory()->for($user)->create([
            'name' => 'BNP konto',
            'type' => AccountType::Bank,
            'bank' => Bank::BnpParibas,
            'opening_date' => '2026-01-01',
        ]);

        OwnTransfer::factory()->for($user)->create([
            'source_account_id' => $source->id,
            'destination_account_id' => $destination->id,
            'transfer_date' => '2026-03-01',
            'amount_minor_units' => 12000,
            'description' => 'ATM withdrawal',
        ]);

        $this->actingAs($user)
            ->get(route('operations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Operations')
                ->has('ownTransfers.data', 1)
                ->where('ownTransfers.data.0.source_account_name', 'Gotówka')
                ->where('ownTransfers.data.0.source_account_type', AccountType::Cash->value)
                ->where('ownTransfers.data.0.source_account_bank', null)
                ->where('ownTransfers.data.0.source_account_mark_url', null)
                ->where('ownTransfers.data.0.destination_account_name', 'BNP konto')
                ->where('ownTransfers.data.0.destination_account_type', AccountType::Bank->value)
                ->where('ownTransfers.data.0.destination_account_bank', Bank::BnpParibas->value)
                ->where('ownTransfers.data.0.destination_account_mark_url', url('/marks/bnp-paribas.jpg')),
            );
    }

    public function test_operations_page_exposes_mark_url_for_named_retail_banks(): void
    {
        $marks = [
            [Bank::Alior, '/marks/alior.svg'],
            [Bank::Ing, '/marks/ing.svg'],
            [Bank::Pekao, '/marks/pekao.svg'],
            [Bank::PkoBp, '/marks/pko-bp.svg'],
            [Bank::Revolut, '/marks/revolut.svg'],
            [Bank::Santander, '/marks/santander.svg'],
        ];

        foreach ($marks as [$bank, $path]) {
            $user = User::factory()->create();
            $account = Account::factory()->for($user)->create([
                'name' => $bank->value,
                'type' => AccountType::Bank,
                'bank' => $bank,
                'opening_date' => '2026-01-01',
            ]);
            $budget = Budget::factory()->for($user)->create([
                'year' => 2026,
                'phase' => BudgetPhase::Execution,
            ]);
            $category = Category::factory()->for($budget)->income()->create();

            FinancialOperation::factory()->create([
                'user_id' => $user->id,
                'type' => FinancialOperationType::Income,
                'account_id' => $account->id,
                'category_id' => $category->id,
                'budget_id' => $budget->id,
                'description' => 'Income',
            ]);

            $this->actingAs($user)
                ->get(route('operations'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Operations')
                    ->has('operations.data', 1)
                    ->where('operations.data.0.account_bank', $bank->value)
                    ->where('operations.data.0.account_mark_url', url($path)),
                );
        }
    }

    public function test_operations_page_exposes_mark_url_for_lokaty_facto_bank_account(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'name' => 'Lokaty Facto',
            'type' => AccountType::Bank,
            'bank' => Bank::LokatyFacto,
            'opening_date' => '2026-01-01',
        ]);
        $budget = Budget::factory()->for($user)->create([
            'year' => 2026,
            'phase' => BudgetPhase::Execution,
        ]);
        $category = Category::factory()->for($budget)->income()->create();

        FinancialOperation::factory()->create([
            'user_id' => $user->id,
            'type' => FinancialOperationType::Income,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'budget_id' => $budget->id,
            'description' => 'Interest',
        ]);

        $this->actingAs($user)
            ->get(route('operations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Operations')
                ->has('operations.data', 1)
                ->where('operations.data.0.account_name', 'Lokaty Facto')
                ->where('operations.data.0.account_bank', Bank::LokatyFacto->value)
                ->where('operations.data.0.account_mark_url', url('/marks/lokaty-facto.svg')),
            );
    }

    public function test_operations_page_exposes_null_mark_url_for_cash_and_other_bank_accounts(): void
    {
        $user = User::factory()->create();
        $cashAccount = Account::factory()->for($user)->cash()->create([
            'name' => 'Cash wallet',
            'opening_date' => '2026-01-01',
        ]);
        $otherBankAccount = Account::factory()->for($user)->bank()->create([
            'name' => 'Other bank',
            'bank' => Bank::Other,
            'opening_date' => '2026-01-01',
        ]);
        $budget = Budget::factory()->for($user)->create([
            'year' => 2026,
            'phase' => BudgetPhase::Execution,
        ]);
        $category = Category::factory()->for($budget)->income()->create();

        FinancialOperation::factory()->create([
            'user_id' => $user->id,
            'type' => FinancialOperationType::Income,
            'account_id' => $cashAccount->id,
            'category_id' => $category->id,
            'budget_id' => $budget->id,
            'operation_date' => '2026-03-01',
            'description' => 'Cash income',
        ]);
        FinancialOperation::factory()->create([
            'user_id' => $user->id,
            'type' => FinancialOperationType::Income,
            'account_id' => $otherBankAccount->id,
            'category_id' => $category->id,
            'budget_id' => $budget->id,
            'operation_date' => '2026-03-02',
            'description' => 'Bank income',
        ]);

        $this->actingAs($user)
            ->get(route('operations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.data', 2)
                ->where('operations.data.0.account_name', 'Other bank')
                ->where('operations.data.0.account_type', AccountType::Bank->value)
                ->where('operations.data.0.account_bank', Bank::Other->value)
                ->where('operations.data.0.account_mark_url', null)
                ->where('operations.data.1.account_name', 'Cash wallet')
                ->where('operations.data.1.account_type', AccountType::Cash->value)
                ->where('operations.data.1.account_bank', null)
                ->where('operations.data.1.account_mark_url', null),
            );
    }
}

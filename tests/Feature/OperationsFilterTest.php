<?php

namespace Tests\Feature;

use App\Enums\BudgetPhase;
use App\Enums\CategoryType;
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

class OperationsFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_matches_description_case_insensitively(): void
    {
        $user = User::factory()->create();
        $this->addOperation($user, [
            'description' => 'Biedronka groceries',
            'operation_date' => '2026-03-01',
        ]);
        $this->addOperation($user, [
            'description' => 'Salary',
            'operation_date' => '2026-03-02',
        ]);

        $this->actingAs($user)
            ->get(route('operations', ['q' => 'biedronka']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Operations')
                ->has('operations.data', 1)
                ->where('operations.data.0.description', 'Biedronka groceries')
                ->where('filters.q', 'biedronka')
                ->where('hasFinancialOperations', true),
            );
    }

    public function test_search_matches_counterparty_or_raw_statement_description(): void
    {
        $user = User::factory()->create();
        $byCounterparty = $this->addOperation($user, [
            'description' => 'Edited note',
            'counterparty' => 'Acme Corp',
            'raw_statement_description' => null,
            'operation_date' => '2026-03-01',
        ]);
        $byRaw = $this->addOperation($user, [
            'description' => 'Edited note',
            'counterparty' => null,
            'raw_statement_description' => 'ORIGINAL BANK TEXT',
            'operation_date' => '2026-03-02',
        ]);
        $this->addOperation($user, [
            'description' => 'Edited note',
            'counterparty' => null,
            'raw_statement_description' => null,
            'operation_date' => '2026-03-03',
        ]);

        $this->actingAs($user)
            ->get(route('operations', ['q' => 'Acme']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.data', 1)
                ->where('operations.data.0.id', $byCounterparty->id),
            );

        $this->actingAs($user)
            ->get(route('operations', ['q' => 'BANK TEXT']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.data', 1)
                ->where('operations.data.0.id', $byRaw->id),
            );
    }

    public function test_search_does_not_match_category_or_account_names(): void
    {
        $user = User::factory()->create();
        $this->addOperation($user, [
            'description' => 'Milk',
            'category_name' => 'Groceries',
            'account_name' => 'mBank checking',
            'operation_date' => '2026-03-01',
        ]);

        $this->actingAs($user)
            ->get(route('operations', ['q' => 'Groceries']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.data', 0),
            );

        $this->actingAs($user)
            ->get(route('operations', ['q' => 'checking']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.data', 0),
            );
    }

    public function test_category_filter_matches_name_and_type_across_years(): void
    {
        $user = User::factory()->create();
        $older = $this->addOperation($user, [
            'year' => 2025,
            'category_name' => 'Groceries',
            'category_type' => CategoryType::Expense,
            'description' => '2025 shop',
            'operation_date' => '2025-06-01',
        ]);
        $newer = $this->addOperation($user, [
            'year' => 2026,
            'category_name' => 'Groceries',
            'category_type' => CategoryType::Expense,
            'description' => '2026 shop',
            'operation_date' => '2026-06-01',
        ]);
        $this->addOperation($user, [
            'year' => 2026,
            'category_name' => 'Salary',
            'category_type' => CategoryType::Income,
            'description' => 'Pay',
            'operation_date' => '2026-06-02',
        ]);

        $this->actingAs($user)
            ->get(route('operations', [
                'category_name' => 'Groceries',
                'category_type' => 'expense',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.data', 2)
                ->where('operations.data.0.id', $newer->id)
                ->where('operations.data.1.id', $older->id),
            );
    }

    public function test_category_rename_splits_the_name_and_type_query(): void
    {
        $user = User::factory()->create();
        $kept = $this->addOperation($user, [
            'year' => 2025,
            'category_name' => 'Groceries',
            'category_type' => CategoryType::Expense,
            'operation_date' => '2025-06-01',
        ]);
        $this->addOperation($user, [
            'year' => 2026,
            'category_name' => 'Food',
            'category_type' => CategoryType::Expense,
            'operation_date' => '2026-06-01',
        ]);

        $this->actingAs($user)
            ->get(route('operations', [
                'category_name' => 'Groceries',
                'category_type' => 'expense',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.data', 1)
                ->where('operations.data.0.id', $kept->id),
            );
    }

    public function test_category_filter_does_not_mix_income_and_expense_with_the_same_name(): void
    {
        $user = User::factory()->create();
        $expense = $this->addOperation($user, [
            'category_name' => 'Other',
            'category_type' => CategoryType::Expense,
            'operation_date' => '2026-03-01',
        ]);
        $this->addOperation($user, [
            'category_name' => 'Other',
            'category_type' => CategoryType::Income,
            'operation_date' => '2026-03-02',
        ]);

        $this->actingAs($user)
            ->get(route('operations', [
                'category_name' => 'Other',
                'category_type' => 'expense',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.data', 1)
                ->where('operations.data.0.id', $expense->id),
            );
    }

    public function test_year_filter_limits_financial_operations_to_that_budget_year(): void
    {
        $user = User::factory()->create();
        $inYear = $this->addOperation($user, [
            'year' => 2026,
            'operation_date' => '2026-03-01',
        ]);
        $this->addOperation($user, [
            'year' => 2025,
            'operation_date' => '2025-03-01',
        ]);

        $this->actingAs($user)
            ->get(route('operations', ['year' => 2026]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.data', 1)
                ->where('operations.data.0.id', $inYear->id)
                ->where('filters.year', 2026),
            );
    }

    public function test_account_filter_includes_operations_on_an_archived_account(): void
    {
        $user = User::factory()->create();
        $archived = Account::factory()->for($user)->archived()->create([
            'name' => 'Old bank',
            'opening_date' => '2025-01-01',
        ]);
        $active = Account::factory()->for($user)->create([
            'name' => 'Cash',
            'opening_date' => '2025-01-01',
        ]);
        $kept = $this->addOperation($user, [
            'account' => $archived,
            'operation_date' => '2026-03-01',
        ]);
        $this->addOperation($user, [
            'account' => $active,
            'operation_date' => '2026-03-02',
        ]);

        $this->actingAs($user)
            ->get(route('operations', ['account_id' => $archived->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.data', 1)
                ->where('operations.data.0.id', $kept->id),
            );
    }

    public function test_date_range_filter_is_inclusive(): void
    {
        $user = User::factory()->create();
        $inside = $this->addOperation($user, ['operation_date' => '2026-03-15']);
        $this->addOperation($user, ['operation_date' => '2026-02-01']);
        $this->addOperation($user, ['operation_date' => '2026-04-01']);

        $this->actingAs($user)
            ->get(route('operations', [
                'date_from' => '2026-03-01',
                'date_to' => '2026-03-31',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.data', 1)
                ->where('operations.data.0.id', $inside->id),
            );
    }

    public function test_search_and_filters_combine_as_intersection(): void
    {
        $user = User::factory()->create();
        $match = $this->addOperation($user, [
            'category_name' => 'Groceries',
            'category_type' => CategoryType::Expense,
            'description' => 'Biedronka',
            'operation_date' => '2026-03-01',
        ]);
        $this->addOperation($user, [
            'category_name' => 'Groceries',
            'category_type' => CategoryType::Expense,
            'description' => 'Lidl',
            'operation_date' => '2026-03-02',
        ]);
        $this->addOperation($user, [
            'category_name' => 'Salary',
            'category_type' => CategoryType::Income,
            'description' => 'Biedronka',
            'operation_date' => '2026-03-03',
        ]);

        $this->actingAs($user)
            ->get(route('operations', [
                'q' => 'Biedronka',
                'category_name' => 'Groceries',
                'category_type' => 'expense',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.data', 1)
                ->where('operations.data.0.id', $match->id),
            );
    }

    public function test_operations_search_does_not_include_another_users_rows(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->addOperation($owner, [
            'description' => 'Shared phrase',
            'operation_date' => '2026-03-01',
        ]);
        $this->addOperation($owner, [
            'description' => 'Unrelated owner row',
            'operation_date' => '2026-03-02',
        ]);
        $this->addOperation($other, [
            'description' => 'Shared phrase',
            'operation_date' => '2026-03-01',
        ]);

        $this->actingAs($owner)
            ->get(route('operations', ['q' => 'Shared']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.data', 1)
                ->where('operations.data.0.description', 'Shared phrase'),
            );
    }

    public function test_account_filter_rejects_another_users_account(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->addOperation($owner, ['operation_date' => '2026-03-01']);
        $foreignAccount = Account::factory()->for($other)->create([
            'opening_date' => '2025-01-01',
        ]);

        $this->actingAs($owner)
            ->from(route('operations'))
            ->get(route('operations', ['account_id' => $foreignAccount->id]))
            ->assertRedirect(route('operations'))
            ->assertSessionHasErrors('account_id');
    }

    public function test_category_picker_uses_newest_year_chrome_and_omits_unused_drafts(): void
    {
        $user = User::factory()->create();
        $this->addOperation($user, [
            'year' => 2025,
            'category_name' => 'Groceries',
            'category_type' => CategoryType::Expense,
            'category_icon' => 'tag',
            'category_color' => '#111111',
            'operation_date' => '2025-06-01',
        ]);
        $this->addOperation($user, [
            'year' => 2026,
            'category_name' => 'Groceries',
            'category_type' => CategoryType::Expense,
            'category_icon' => 'cart',
            'category_color' => '#222222',
            'operation_date' => '2026-06-01',
        ]);
        $draft = Budget::factory()->for($user)->create([
            'year' => 2027,
            'phase' => BudgetPhase::Draft,
        ]);
        Category::factory()->for($draft)->expense()->create([
            'name' => 'Groceries',
            'icon' => 'star',
            'color' => '#333333',
        ]);

        $this->actingAs($user)
            ->get(route('operations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('filterCategories', 1)
                ->where('filterCategories.0.name', 'Groceries')
                ->where('filterCategories.0.type', 'expense')
                ->where('filterCategories.0.icon', 'cart')
                ->where('filterCategories.0.color', '#222222')
                ->where('filterYears', [2026, 2025]),
            );

        $this->actingAs($user)
            ->get(route('operations', ['year' => 2025]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('filterCategories', 1)
                ->where('filterCategories.0.icon', 'tag')
                ->where('filterCategories.0.color', '#111111'),
            );
    }

    public function test_category_picker_includes_archived_categories_that_have_operations(): void
    {
        $user = User::factory()->create();
        $this->addOperation($user, [
            'category_name' => 'Old bucket',
            'category_archived' => true,
            'operation_date' => '2026-03-01',
        ]);

        $this->actingAs($user)
            ->get(route('operations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('filterCategories', 1)
                ->where('filterCategories.0.name', 'Old bucket'),
            );
    }

    public function test_account_picker_includes_archived_accounts(): void
    {
        $user = User::factory()->create();
        $archived = Account::factory()->for($user)->archived()->create([
            'name' => 'Old bank',
            'opening_date' => '2025-01-01',
        ]);
        Account::factory()->for($user)->create([
            'name' => 'Cash',
            'opening_date' => '2025-01-01',
        ]);
        $this->addOperation($user, [
            'account' => $archived,
            'operation_date' => '2026-03-01',
        ]);

        $this->actingAs($user)
            ->get(route('operations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('filterAccounts', 2)
                ->where('filterAccounts.0.name', 'Cash')
                ->where('filterAccounts.0.is_archived', false)
                ->where('filterAccounts.1.name', 'Old bank')
                ->where('filterAccounts.1.is_archived', true),
            );
    }

    public function test_own_transfers_are_omitted_while_financial_operation_filters_are_active(): void
    {
        $user = User::factory()->create();
        $this->addOperation($user, [
            'description' => 'Keep me',
            'operation_date' => '2026-03-01',
        ]);
        $source = Account::factory()->for($user)->bank()->create([
            'opening_date' => '2026-01-01',
        ]);
        $destination = Account::factory()->for($user)->cash()->create([
            'opening_date' => '2026-01-01',
        ]);
        OwnTransfer::factory()->create([
            'user_id' => $user->id,
            'source_account_id' => $source->id,
            'destination_account_id' => $destination->id,
        ]);

        $this->actingAs($user)
            ->get(route('operations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('ownTransfers.data', 1)
                ->where('filtersActive', false),
            );

        $this->actingAs($user)
            ->get(route('operations', ['q' => 'Keep']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('ownTransfers.data', 0)
                ->where('ownTransfers.total', 0)
                ->where('filtersActive', true)
                ->has('operations.data', 1),
            );
    }

    public function test_filtered_empty_slice_is_distinct_from_having_no_financial_operations(): void
    {
        $user = User::factory()->create();
        $this->addOperation($user, [
            'description' => 'Salary',
            'operation_date' => '2026-03-01',
        ]);

        $this->actingAs($user)
            ->get(route('operations', ['q' => 'nomatch']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.data', 0)
                ->where('hasFinancialOperations', true)
                ->where('filtersActive', true),
            );

        $emptyUser = User::factory()->create();

        $this->actingAs($emptyUser)
            ->get(route('operations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.data', 0)
                ->where('hasFinancialOperations', false)
                ->where('filtersActive', false),
            );
    }

    public function test_search_treats_like_wildcards_as_literal_text(): void
    {
        $user = User::factory()->create();
        $this->addOperation($user, [
            'description' => 'plain grocery',
            'operation_date' => '2026-03-01',
        ]);

        $this->actingAs($user)
            ->get(route('operations', ['q' => '%']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.data', 0),
            );
    }

    public function test_guests_are_redirected_from_the_filtered_operations_list(): void
    {
        $this->get(route('operations', ['q' => 'salary']))
            ->assertRedirect(route('login'));
    }

    public function test_category_name_without_type_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->addOperation($user, ['operation_date' => '2026-03-01']);

        $this->actingAs($user)
            ->from(route('operations'))
            ->get(route('operations', ['category_name' => 'Groceries']))
            ->assertRedirect(route('operations'))
            ->assertSessionHasErrors('category_type');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function addOperation(User $user, array $attributes): FinancialOperation
    {
        $year = $attributes['year'] ?? 2026;
        $categoryType = $attributes['category_type'] ?? CategoryType::Income;
        $categoryName = $attributes['category_name'] ?? 'Default';
        $account = $attributes['account'] ?? Account::factory()->for($user)->create([
            'name' => $attributes['account_name'] ?? 'Wallet',
            'opening_date' => '2025-01-01',
        ]);

        $budget = Budget::query()
            ->where('user_id', $user->id)
            ->where('year', $year)
            ->first() ?? Budget::factory()->for($user)->execution()->create([
                'year' => $year,
            ]);

        $category = Category::factory()->for($budget)->create([
            'name' => $categoryName,
            'type' => $categoryType,
            'icon' => $attributes['category_icon'] ?? 'tag',
            'color' => $attributes['category_color'] ?? '#868e96',
            'archived_at' => ($attributes['category_archived'] ?? false) ? now() : null,
        ]);

        $operationType = $categoryType === CategoryType::Expense
            ? FinancialOperationType::Expense
            : FinancialOperationType::Income;

        return FinancialOperation::factory()->create([
            'user_id' => $user->id,
            'type' => $operationType,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'budget_id' => $budget->id,
            'operation_date' => $attributes['operation_date'],
            'description' => $attributes['description'] ?? 'Entry',
            'counterparty' => $attributes['counterparty'] ?? null,
            'raw_statement_description' => $attributes['raw_statement_description'] ?? null,
        ]);
    }
}

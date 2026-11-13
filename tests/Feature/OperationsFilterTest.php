<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\BudgetPhase;
use App\Enums\CapitalMovementDirection;
use App\Enums\CategoryType;
use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\CapitalMovement;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\FinancialOperation;
use App\Models\OwnTransfer;
use App\Models\TermDeposit;
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
                ->where('hasAnyRecords', true),
            );
    }

    public function test_a_page_past_the_end_redirects_to_the_last_page_keeping_filters(): void
    {
        $user = User::factory()->create();
        $this->addOperation($user, ['description' => 'Biedronka', 'operation_date' => '2026-03-01']);

        $this->actingAs($user)
            ->get(route('operations', ['q' => 'biedronka', 'operations_page' => 5]))
            ->assertRedirect(route('operations', [
                'q' => 'biedronka',
                'operations_page' => 1,
                'section' => 'financial_operations',
            ]));
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

    public function test_category_filter_joins_the_years_by_category_id(): void
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
                'category_id' => $this->listCategoryIdOf($newer),
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.data', 2)
                ->where('operations.data.0.id', $newer->id)
                ->where('operations.data.1.id', $older->id),
            );
    }

    public function test_category_rename_does_not_split_the_history(): void
    {
        $user = User::factory()->create();
        $older = $this->addOperation($user, [
            'year' => 2025,
            'category_name' => 'Groceries',
            'category_type' => CategoryType::Expense,
            'operation_date' => '2025-06-01',
        ]);
        $newer = $this->addOperation($user, [
            'year' => 2026,
            'category_name' => 'Groceries',
            'category_type' => CategoryType::Expense,
            'operation_date' => '2026-06-01',
        ]);
        $category = $older->budgetLine->category;
        $category->update(['name' => 'Food']);

        $this->actingAs($user)
            ->get(route('operations', ['category_id' => $category->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.data', 2)
                ->where('operations.data.0.id', $newer->id)
                ->where('operations.data.1.id', $older->id)
                ->where('operations.data.0.category_label', 'Food')
                ->where('filterCategories.0.id', $category->id)
                ->where('filterCategories.0.name', 'Food'),
            );
    }

    public function test_category_filter_ignores_the_old_name_and_type_parameters(): void
    {
        $user = User::factory()->create();
        $this->addOperation($user, [
            'category_name' => 'Groceries',
            'category_type' => CategoryType::Expense,
            'operation_date' => '2026-03-01',
        ]);
        $this->addOperation($user, [
            'category_name' => 'Salary',
            'category_type' => CategoryType::Income,
            'operation_date' => '2026-03-02',
        ]);

        $this->actingAs($user)
            ->get(route('operations', ['category_name' => 'Groceries', 'category_type' => 'expense']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.data', 2)
                ->where('filtersActive', false)
                ->where('filters.category_id', null),
            );
    }

    public function test_category_filter_option_carries_a_selected_category_without_operations(): void
    {
        $user = User::factory()->create();
        $this->addOperation($user, [
            'category_name' => 'Groceries',
            'category_type' => CategoryType::Expense,
            'operation_date' => '2026-03-01',
        ]);
        Category::query()->where('user_id', $user->id)->where('name', 'Groceries')->update(['sort_order' => 1]);
        $unused = BudgetLine::factory()->for(Budget::query()->where('user_id', $user->id)->firstOrFail())->expense()->create(['name' => 'Unused', 'sort_order' => 0]);

        $this->actingAs($user)
            ->get(route('operations', ['category_id' => $unused->category_id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.data', 0)
                ->has('filterCategories', 2)
                ->where('filterCategories.0.id', $unused->category_id)
                ->where('filterCategories.0.name', 'Unused'),
            );
    }

    public function test_category_filter_options_follow_the_order_of_the_list_not_the_name(): void
    {
        $user = User::factory()->create();
        $this->addOperation($user, [
            'category_name' => 'Auto',
            'category_type' => CategoryType::Expense,
            'category_sort_order' => 1,
            'operation_date' => '2026-03-01',
        ]);
        $this->addOperation($user, [
            'category_name' => 'Żywność',
            'category_type' => CategoryType::Expense,
            'category_sort_order' => 0,
            'operation_date' => '2026-03-02',
        ]);
        $this->addOperation($user, [
            'category_name' => 'Pensja',
            'category_type' => CategoryType::Income,
            'category_sort_order' => 5,
            'operation_date' => '2026-03-03',
        ]);

        $this->actingAs($user)
            ->get(route('operations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('filterCategories', 3)
                ->where('filterCategories.0.name', 'Pensja')
                ->where('filterCategories.1.name', 'Żywność')
                ->where('filterCategories.2.name', 'Auto'),
            );
    }

    public function test_category_filter_options_put_group_members_behind_the_group_and_loose_ones_between_groups(): void
    {
        $user = User::factory()->create();
        $first = CategoryGroup::factory()->for($user)->expense()->create(['name' => 'Dom', 'sort_order' => 0]);
        $second = CategoryGroup::factory()->for($user)->expense()->create(['name' => 'Transport', 'sort_order' => 2]);
        $this->addOperation($user, [
            'category_name' => 'Paliwo',
            'category_type' => CategoryType::Expense,
            'category_sort_order' => 3,
            'category_group' => $second,
            'operation_date' => '2026-03-01',
        ]);
        $this->addOperation($user, [
            'category_name' => 'Czynsz',
            'category_type' => CategoryType::Expense,
            'category_sort_order' => 1,
            'category_group' => $first,
            'operation_date' => '2026-03-02',
        ]);
        $this->addOperation($user, [
            'category_name' => 'Kino',
            'category_type' => CategoryType::Expense,
            'category_sort_order' => 1,
            'operation_date' => '2026-03-03',
        ]);

        $this->actingAs($user)
            ->get(route('operations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('filterCategories', 3)
                ->where('filterCategories.0.name', 'Czynsz')
                ->where('filterCategories.0.group', ['id' => $first->id, 'name' => 'Dom'])
                ->where('filterCategories.1.name', 'Kino')
                ->where('filterCategories.1.group', null)
                ->where('filterCategories.2.name', 'Paliwo')
                ->where('filterCategories.2.group', ['id' => $second->id, 'name' => 'Transport']),
            );
    }

    public function test_category_filter_options_mark_archived_by_the_line_of_the_selected_year(): void
    {
        $user = User::factory()->create();
        $this->addOperation($user, [
            'year' => 2025,
            'category_name' => 'Transport',
            'category_type' => CategoryType::Expense,
            'operation_date' => '2025-03-01',
        ]);
        $this->addOperation($user, [
            'year' => 2026,
            'category_name' => 'Transport',
            'category_type' => CategoryType::Expense,
            'category_archived' => true,
            'operation_date' => '2026-03-01',
        ]);

        foreach ([['year' => 2025, 'archived' => false], ['year' => 2026, 'archived' => true], ['year' => null, 'archived' => true]] as $case) {
            $this->actingAs($user)
                ->get(route('operations', array_filter(['year' => $case['year']])))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->has('filterCategories', 1)
                    ->where('filterCategories.0.name', 'Transport')
                    ->where('filterCategories.0.is_archived', $case['archived']),
                );
        }
    }

    public function test_a_selected_category_without_operations_is_archived_by_the_list(): void
    {
        $user = User::factory()->create();
        $this->addOperation($user, [
            'category_name' => 'Groceries',
            'category_type' => CategoryType::Expense,
            'operation_date' => '2026-03-01',
        ]);
        $active = Category::factory()->for($user)->expense()->create(['name' => 'Spare', 'sort_order' => 5]);
        $archived = Category::factory()->for($user)->expense()->archived()->create(['name' => 'Retired', 'sort_order' => 6]);

        $this->actingAs($user)
            ->get(route('operations', ['category_id' => $active->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('filterCategories', 2)
                ->where('filterCategories.0.is_archived', false)
                ->where('filterCategories.1.id', $active->id)
                ->where('filterCategories.1.is_archived', false)
                ->where('filterCategories.1.group', null),
            );

        $this->actingAs($user)
            ->get(route('operations', ['category_id' => $archived->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('filterCategories', 2)
                ->where('filterCategories.1.id', $archived->id)
                ->where('filterCategories.1.is_archived', true),
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
                'category_id' => $this->listCategoryIdOf($expense),
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
            'type' => AccountType::Bank,
            'bank' => Bank::Alior,
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
                'category_id' => $this->listCategoryIdOf($match),
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

    public function test_category_picker_uses_the_list_chrome_of_one_category_and_omits_unused_drafts(): void
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
        BudgetLine::factory()->for($draft)->expense()->create([
            'name' => 'Groceries',
            'icon' => 'star',
            'color' => '#333333',
        ]);

        $this->actingAs($user)
            ->get(route('operations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('filterCategories', 1)
                ->where('filterCategories.0.id', Category::query()->where('user_id', $user->id)->where('name', 'Groceries')->value('id'))
                ->where('filterCategories.0.name', 'Groceries')
                ->where('filterCategories.0.type', 'expense')
                ->where('filterCategories.0.icon', 'tag')
                ->where('filterCategories.0.color', '#111111')
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
            'type' => AccountType::Bank,
            'bank' => Bank::Alior,
            'opening_date' => '2025-01-01',
        ]);
        Account::factory()->for($user)->create([
            'name' => 'Cash',
            'type' => AccountType::Cash,
            'bank' => null,
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
                ->where('filterAccounts.0.type', AccountType::Cash->value)
                ->where('filterAccounts.0.bank', null)
                ->where('filterAccounts.0.mark_url', null)
                ->where('filterAccounts.0.is_archived', false)
                ->where('filterAccounts.1.name', 'Old bank')
                ->where('filterAccounts.1.type', AccountType::Bank->value)
                ->where('filterAccounts.1.bank', Bank::Alior->value)
                ->where('filterAccounts.1.mark_url', url('/marks/alior.svg'))
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
                ->where('hasAnyRecords', true)
                ->where('filtersActive', true),
            );

        $emptyUser = User::factory()->create();

        $this->actingAs($emptyUser)
            ->get(route('operations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.data', 0)
                ->where('hasAnyRecords', false)
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

    public function test_category_filter_rejects_another_users_category(): void
    {
        $user = User::factory()->create();
        $foreign = $this->addOperation(User::factory()->create(), ['operation_date' => '2026-03-01']);
        $this->addOperation($user, ['operation_date' => '2026-03-01']);

        $this->actingAs($user)
            ->from(route('operations'))
            ->get(route('operations', ['category_id' => $this->listCategoryIdOf($foreign)]))
            ->assertRedirect(route('operations'))
            ->assertSessionHasErrors('category_id');
    }

    public function test_term_deposit_filter_shows_that_holdings_capital_movements(): void
    {
        $user = User::factory()->create();
        $this->addOperation($user, ['operation_date' => '2026-03-01']);
        [$account, $keptDeposit] = $this->createAccountAndTermDeposit($user);
        $otherDeposit = TermDeposit::factory()->for($user)->create([
            'name' => 'Other deposit',
            'opening_date' => '2026-01-01',
        ]);
        $kept = $this->addCapitalMovement($user, [
            'account' => $account,
            'term_deposit' => $keptDeposit,
            'description' => 'Parking on kept deposit',
            'movement_date' => '2026-03-01',
        ]);
        $this->addCapitalMovement($user, [
            'account' => $account,
            'term_deposit' => $otherDeposit,
            'description' => 'Parking on other deposit',
            'movement_date' => '2026-03-02',
        ]);

        $this->actingAs($user)
            ->get(route('operations', ['term_deposit_id' => $keptDeposit->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.data', 0)
                ->has('capitalMovements.data', 1)
                ->where('capitalMovements.data.0.id', $kept->id)
                ->where('filters.term_deposit_id', $keptDeposit->id)
                ->where('filtersActive', true),
            );
    }

    public function test_term_deposit_filter_does_not_include_financial_operations(): void
    {
        $user = User::factory()->create();
        $operation = $this->addOperation($user, [
            'description' => 'Salary',
            'operation_date' => '2026-03-01',
        ]);
        [$account, $termDeposit] = $this->createAccountAndTermDeposit($user);
        $this->addCapitalMovement($user, [
            'account' => $account,
            'term_deposit' => $termDeposit,
            'movement_date' => '2026-03-02',
        ]);

        $this->actingAs($user)
            ->get(route('operations', ['term_deposit_id' => $termDeposit->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.data', 0)
                ->where('operations.total', 0)
                ->has('capitalMovements.data', 1),
            );

        $this->actingAs($user)
            ->get(route('operations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.data', 1)
                ->where('operations.data.0.id', $operation->id),
            );
    }

    public function test_category_and_year_filters_omit_capital_movements(): void
    {
        $user = User::factory()->create();
        [$account, $termDeposit] = $this->createAccountAndTermDeposit($user);
        $this->addCapitalMovement($user, [
            'account' => $account,
            'term_deposit' => $termDeposit,
            'movement_date' => '2026-03-01',
        ]);
        $operation = $this->addOperation($user, [
            'year' => 2026,
            'category_name' => 'Groceries',
            'category_type' => CategoryType::Expense,
            'operation_date' => '2026-03-01',
        ]);

        $this->actingAs($user)
            ->get(route('operations', ['year' => 2026]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('capitalMovements.data', 0)
                ->where('capitalMovements.total', 0),
            );

        $this->actingAs($user)
            ->get(route('operations', [
                'category_id' => $this->listCategoryIdOf($operation),
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('capitalMovements.data', 0)
                ->where('capitalMovements.total', 0),
            );
    }

    public function test_account_filter_includes_matching_capital_movements(): void
    {
        $user = User::factory()->create();
        $keptAccount = Account::factory()->for($user)->create([
            'name' => 'Kept account',
            'opening_date' => '2026-01-01',
        ]);
        $otherAccount = Account::factory()->for($user)->create([
            'name' => 'Other account',
            'opening_date' => '2026-01-01',
        ]);
        $termDeposit = TermDeposit::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);
        $kept = $this->addCapitalMovement($user, [
            'account' => $keptAccount,
            'term_deposit' => $termDeposit,
            'movement_date' => '2026-03-01',
        ]);
        $this->addCapitalMovement($user, [
            'account' => $otherAccount,
            'term_deposit' => $termDeposit,
            'movement_date' => '2026-03-02',
        ]);

        $this->actingAs($user)
            ->get(route('operations', ['account_id' => $keptAccount->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('capitalMovements.data', 1)
                ->where('capitalMovements.data.0.id', $kept->id),
            );
    }

    public function test_date_range_filter_includes_matching_capital_movements(): void
    {
        $user = User::factory()->create();
        [$account, $termDeposit] = $this->createAccountAndTermDeposit($user);
        $inside = $this->addCapitalMovement($user, [
            'account' => $account,
            'term_deposit' => $termDeposit,
            'movement_date' => '2026-03-15',
        ]);
        $this->addCapitalMovement($user, [
            'account' => $account,
            'term_deposit' => $termDeposit,
            'movement_date' => '2026-02-01',
        ]);

        $this->actingAs($user)
            ->get(route('operations', [
                'date_from' => '2026-03-01',
                'date_to' => '2026-03-31',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('capitalMovements.data', 1)
                ->where('capitalMovements.data.0.id', $inside->id),
            );
    }

    public function test_search_includes_matching_capital_movements(): void
    {
        $user = User::factory()->create();
        [$account, $termDeposit] = $this->createAccountAndTermDeposit($user);
        $match = $this->addCapitalMovement($user, [
            'account' => $account,
            'term_deposit' => $termDeposit,
            'description' => 'Parking capital',
            'movement_date' => '2026-03-01',
        ]);
        $this->addCapitalMovement($user, [
            'account' => $account,
            'term_deposit' => $termDeposit,
            'description' => 'Return from deposit',
            'movement_date' => '2026-03-02',
        ]);

        $this->actingAs($user)
            ->get(route('operations', ['q' => 'Parking']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('capitalMovements.data', 1)
                ->where('capitalMovements.data.0.id', $match->id),
            );
    }

    public function test_term_deposit_picker_lists_user_holdings(): void
    {
        $user = User::factory()->create();
        $first = TermDeposit::factory()->for($user)->create([
            'name' => 'Alpha deposit',
            'bank' => Bank::LokatyFacto,
            'opening_date' => '2026-01-01',
        ]);
        $second = TermDeposit::factory()->for($user)->create([
            'name' => 'Beta deposit',
            'bank' => Bank::Ing,
            'opening_date' => '2026-01-01',
        ]);

        $this->actingAs($user)
            ->get(route('operations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('filterTermDeposits', 2)
                ->where('filterTermDeposits.0.id', $first->id)
                ->where('filterTermDeposits.0.name', 'Alpha deposit')
                ->where('filterTermDeposits.0.bank', 'lokaty_facto')
                ->where('filterTermDeposits.0.mark_url', url('/marks/lokaty-facto.svg'))
                ->where('filterTermDeposits.1.id', $second->id)
                ->where('filterTermDeposits.1.name', 'Beta deposit')
                ->where('filterTermDeposits.1.bank', 'ing')
                ->where('filterTermDeposits.1.mark_url', null),
            );
    }

    public function test_suppressed_sections_are_empty_when_no_filters_are_active(): void
    {
        $user = User::factory()->create();
        $this->addOperation($user, ['operation_date' => '2026-03-01']);

        $this->actingAs($user)
            ->get(route('operations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('suppressedSections', [])
                ->where('filtersActive', false),
            );
    }

    public function test_search_suppresses_own_transfers_section(): void
    {
        $user = User::factory()->create();
        $this->addOperation($user, [
            'description' => 'Salary',
            'operation_date' => '2026-03-01',
        ]);

        $this->actingAs($user)
            ->get(route('operations', ['q' => 'Salary']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('suppressedSections', ['own_transfers'])
                ->where('filtersActive', true),
            );
    }

    public function test_year_filter_suppresses_own_transfers_and_capital_movements(): void
    {
        $user = User::factory()->create();
        $this->addOperation($user, [
            'year' => 2026,
            'operation_date' => '2026-03-01',
        ]);

        $this->actingAs($user)
            ->get(route('operations', ['year' => 2026]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('suppressedSections', ['own_transfers', 'capital_movements'])
                ->where('filtersActive', true),
            );
    }

    public function test_term_deposit_only_filter_suppresses_financial_operations_and_own_transfers(): void
    {
        $user = User::factory()->create();
        $this->addOperation($user, ['operation_date' => '2026-03-01']);
        [$account, $termDeposit] = $this->createAccountAndTermDeposit($user);
        $this->addCapitalMovement($user, [
            'account' => $account,
            'term_deposit' => $termDeposit,
            'movement_date' => '2026-03-02',
        ]);

        $this->actingAs($user)
            ->get(route('operations', ['term_deposit_id' => $termDeposit->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('suppressedSections', ['financial_operations', 'own_transfers'])
                ->where('filtersActive', true),
            );
    }

    public function test_term_deposit_filter_rejects_another_users_term_deposit(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->addOperation($owner, ['operation_date' => '2026-03-01']);
        $foreignDeposit = TermDeposit::factory()->for($other)->create([
            'opening_date' => '2026-01-01',
        ]);

        $this->actingAs($owner)
            ->from(route('operations'))
            ->get(route('operations', ['term_deposit_id' => $foreignDeposit->id]))
            ->assertRedirect(route('operations'))
            ->assertSessionHasErrors('term_deposit_id');
    }

    public function test_account_and_term_deposit_filters_combine_for_capital_movements(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);
        $keptDeposit = TermDeposit::factory()->for($user)->create([
            'name' => 'Kept',
            'opening_date' => '2026-01-01',
        ]);
        $otherDeposit = TermDeposit::factory()->for($user)->create([
            'name' => 'Other',
            'opening_date' => '2026-01-01',
        ]);
        $this->addOperation($user, [
            'account' => $account,
            'description' => 'Salary on account',
            'operation_date' => '2026-03-01',
        ]);
        $kept = $this->addCapitalMovement($user, [
            'account' => $account,
            'term_deposit' => $keptDeposit,
            'movement_date' => '2026-03-02',
        ]);
        $this->addCapitalMovement($user, [
            'account' => $account,
            'term_deposit' => $otherDeposit,
            'movement_date' => '2026-03-03',
        ]);

        $this->actingAs($user)
            ->get(route('operations', [
                'account_id' => $account->id,
                'term_deposit_id' => $keptDeposit->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.data', 1)
                ->has('capitalMovements.data', 1)
                ->where('capitalMovements.data.0.id', $kept->id),
            );
    }

    private function listCategoryIdOf(FinancialOperation $operation): int
    {
        return $operation->budgetLine->category_id;
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

        $category = BudgetLine::factory()->for($budget)->create([
            'name' => $categoryName,
            'type' => $categoryType,
            'icon' => $attributes['category_icon'] ?? 'tag',
            'color' => $attributes['category_color'] ?? '#868e96',
            'sort_order' => $attributes['category_sort_order'] ?? 0,
            'archived_at' => ($attributes['category_archived'] ?? false) ? now() : null,
        ]);

        if (($attributes['category_group'] ?? null) instanceof CategoryGroup) {
            $category->category->update(['category_group_id' => $attributes['category_group']->id]);
        }

        $operationType = $categoryType === CategoryType::Expense
            ? FinancialOperationType::Expense
            : FinancialOperationType::Income;

        return FinancialOperation::factory()->create([
            'user_id' => $user->id,
            'type' => $operationType,
            'account_id' => $account->id,
            'budget_line_id' => $category->id,
            'budget_id' => $budget->id,
            'operation_date' => $attributes['operation_date'],
            'description' => $attributes['description'] ?? 'Entry',
            'counterparty' => $attributes['counterparty'] ?? null,
            'raw_statement_description' => $attributes['raw_statement_description'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function addCapitalMovement(User $user, array $attributes): CapitalMovement
    {
        $account = $attributes['account'] ?? Account::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);
        $termDeposit = $attributes['term_deposit'] ?? TermDeposit::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);

        return CapitalMovement::factory()->for($user)->create([
            'account_id' => $account->id,
            'term_deposit_id' => $termDeposit->id,
            'movement_date' => $attributes['movement_date'] ?? '2026-03-01',
            'description' => $attributes['description'] ?? 'Capital movement',
            'direction' => $attributes['direction'] ?? CapitalMovementDirection::ToTermDeposit,
            'amount_minor_units' => $attributes['amount_minor_units'] ?? 10000,
        ]);
    }

    /**
     * @return array{0: Account, 1: TermDeposit}
     */
    private function createAccountAndTermDeposit(User $user): array
    {
        $account = Account::factory()->for($user)->bank()->create([
            'opening_date' => '2026-01-01',
        ]);
        $termDeposit = TermDeposit::factory()->for($user)->create([
            'opening_date' => '2026-01-01',
        ]);

        return [$account, $termDeposit];
    }
}

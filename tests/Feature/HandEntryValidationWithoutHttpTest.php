<?php

namespace Tests\Feature;

use App\Enums\BudgetPhase;
use App\Enums\CategoryType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\User;
use App\Support\ExpenseEntryRules;
use App\Support\OperationEntryValidation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The expense rules and the entry after-hook run without a request must give
 * the very same messages as the store route.
 */
class HandEntryValidationWithoutHttpTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string}> the case and a field it must fail on
     */
    public static function failingCases(): array
    {
        return [
            'future date' => ['future_date', 'operation_date'],
            'date before account opening' => ['before_opening', 'operation_date'],
            'foreign account' => ['foreign_account', 'account_id'],
            'foreign category' => ['foreign_category', 'category_id'],
            'income category for an expense' => ['income_category', 'category_id'],
            'draft budget' => ['draft_budget', 'category_id'],
            'archived category' => ['archived_category', 'category_id'],
        ];
    }

    #[DataProvider('failingCases')]
    public function test_plain_values_give_the_same_errors_as_the_store_route(string $case, string $failingField): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $other = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_date' => '2026-01-01']);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $line = BudgetLine::factory()->for($budget)->expense()->create();

        $input = [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 10000,
            'description' => 'Test',
            'account_id' => $account->id,
            'category_id' => $line->category_id,
        ];

        switch ($case) {
            case 'future_date':
                $input['operation_date'] = '2026-06-02';
                break;
            case 'before_opening':
                $input['operation_date'] = '2025-12-31';
                break;
            case 'foreign_account':
                $input['account_id'] = Account::factory()->for($other)->create(['opening_date' => '2026-01-01'])->id;
                break;
            case 'foreign_category':
                $otherBudget = Budget::factory()->for($other)->execution()->create(['year' => 2026]);
                $input['category_id'] = BudgetLine::factory()->for($otherBudget)->expense()->create()->category_id;
                break;
            case 'income_category':
                $input['category_id'] = BudgetLine::factory()->for($budget)->income()->create()->category_id;
                break;
            case 'draft_budget':
                $draft = Budget::factory()->for($user)->create(['year' => 2027, 'phase' => BudgetPhase::Draft]);
                $input['category_id'] = BudgetLine::factory()->for($draft)->expense()->create()->category_id;
                $input['operation_date'] = '2027-01-01';
                $this->travelTo('2027-01-02');
                break;
            case 'archived_category':
                $archived = BudgetLine::factory()->for($budget)->expense()->create();
                $archived->category->update(['archived_at' => now()]);
                $input['category_id'] = $archived->category_id;
                break;
        }

        $this->actingAs($user)
            ->from(route('operations'))
            ->post(route('expenses.store'), $input)
            ->assertSessionHasErrors();
        $httpErrors = session('errors')->getBag('default')->getMessages();

        $validator = Validator::make($input, ExpenseEntryRules::storeRulesFor($user->id));
        OperationEntryValidation::attachValues($validator, $user, CategoryType::Expense, $input, null);

        $this->assertArrayHasKey($failingField, $httpErrors);
        $this->assertSame($httpErrors, $validator->errors()->getMessages());
        $this->assertDatabaseCount('financial_operations', 0);
    }

    public function test_valid_plain_values_pass_and_build_the_payload(): void
    {
        $this->travelTo('2026-06-01');

        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['opening_date' => '2026-01-01']);
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $line = BudgetLine::factory()->for($budget)->expense()->create();

        $input = [
            'operation_date' => '2026-03-15',
            'amount_minor_units' => 10000,
            'description' => 'Test',
            'account_id' => $account->id,
            'category_id' => $line->category_id,
        ];

        $validator = Validator::make($input, ExpenseEntryRules::storeRulesFor($user->id));
        OperationEntryValidation::attachValues($validator, $user, CategoryType::Expense, $input, null);

        $this->assertTrue($validator->passes());
        $this->assertEquals(
            [...$input, 'counterparty' => null],
            ExpenseEntryRules::payloadFrom($validator->validated()),
        );
    }
}

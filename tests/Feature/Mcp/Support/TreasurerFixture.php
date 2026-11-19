<?php

namespace Tests\Feature\Mcp\Support;

use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\CategoryGroup;
use App\Models\FinancialOperation;
use App\Models\TermDeposit;
use App\Models\User;

/**
 * The canonical household of the MCP tool tests, at the App Day 2026-06-15 (call `travelTo`
 * first), and a second user whose data must never show up. Amounts are the hand-counted ones
 * of `MonthPlanPageTest`: the June proposal has To Spend 373 000 grosz.
 */
final class TreasurerFixture
{
    /**
     * @return array{user: User, budget: Budget, account: Account, lines: array<string, BudgetLine>, group: CategoryGroup}
     */
    public static function household(): array
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);

        $lines = [
            'salary' => BudgetLine::factory()->for($budget)->income()->create([
                'name' => 'Pensja', 'sort_order' => 0, 'planned_amount_minor_units' => 9_600_000, 'initial_plan_minor_units' => 9_000_000,
            ]),
            'rent' => BudgetLine::factory()->for($budget)->fixedCharge()->create([
                'name' => 'Czynsz', 'sort_order' => 0, 'planned_amount_minor_units' => 2_400_000, 'initial_plan_minor_units' => 2_400_000, 'execution_minor_units' => 195_000,
            ]),
            'food' => BudgetLine::factory()->for($budget)->expense()->create([
                'name' => 'Jedzenie', 'sort_order' => 1, 'planned_amount_minor_units' => 1_800_000, 'initial_plan_minor_units' => 1_800_000, 'execution_minor_units' => 1_850_000,
            ]),
            'fuel' => BudgetLine::factory()->for($budget)->expense()->create([
                'name' => 'Paliwo', 'sort_order' => 2, 'planned_amount_minor_units' => 480_000, 'initial_plan_minor_units' => 480_000, 'execution_minor_units' => 52_000,
            ]),
            'gift' => BudgetLine::factory()->for($budget)->evenAnnual()->create([
                'name' => 'Prezent', 'sort_order' => 3, 'planned_amount_minor_units' => 100_000, 'initial_plan_minor_units' => 100_000, 'execution_minor_units' => 30_000,
            ]),
        ];

        $group = CategoryGroup::factory()->for($user)->expense()->create(['name' => 'Dom', 'sort_order' => 0]);
        $lines['food']->category->update(['category_group_id' => $group->id]);
        $lines['fuel']->category->update(['category_group_id' => $group->id]);

        $account = Account::factory()->for($user)->bank()->create(['name' => 'Konto Glowne']);

        self::expense($user, $budget, $account, $lines['rent'], '2026-06-02', 195_000, 'Czynsz czerwiec');
        self::expense($user, $budget, $account, $lines['food'], '2026-06-03', 60_000, 'Biedronka zakupy');
        self::expense($user, $budget, $account, $lines['fuel'], '2026-06-04', 52_000, 'Orlen tankowanie');
        self::expense($user, $budget, $account, $lines['gift'], '2026-06-05', 30_000, 'Prezent urodzinowy');

        return ['user' => $user, 'budget' => $budget, 'account' => $account, 'lines' => $lines, 'group' => $group];
    }

    /**
     * Another user with an execution Budget, an account, a term deposit, a Category, a group and an operation,
     * all with names no test of the first user may see.
     *
     * @return array{user: User, account: Account, line: BudgetLine, operation: FinancialOperation}
     */
    public static function stranger(): array
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->execution()->create(['year' => 2026]);
        $line = BudgetLine::factory()->for($budget)->expense()->create([
            'name' => 'Obca Kategoria', 'planned_amount_minor_units' => 777_700, 'initial_plan_minor_units' => 777_700, 'execution_minor_units' => 111_100,
        ]);
        $group = CategoryGroup::factory()->for($user)->expense()->create(['name' => 'Obca Grupa']);
        $line->category->update(['category_group_id' => $group->id]);
        $account = Account::factory()->for($user)->bank()->create(['name' => 'Obce Konto', 'current_balance_minor_units' => 888_800]);
        TermDeposit::factory()->for($user)->create(['name' => 'Obca Lokata', 'current_balance_minor_units' => 999_900]);
        $operation = self::expense($user, $budget, $account, $line, '2026-06-10', 111_100, 'Obcy zakup');

        return ['user' => $user, 'account' => $account, 'line' => $line, 'operation' => $operation];
    }

    public static function expense(User $user, Budget $budget, Account $account, BudgetLine $line, string $date, int $amount, string $description): FinancialOperation
    {
        return FinancialOperation::factory()->for($user)->for($budget)->create([
            'type' => FinancialOperationType::Expense,
            'account_id' => $account->id,
            'budget_line_id' => $line->id,
            'operation_date' => $date,
            'amount_minor_units' => $amount,
            'description' => $description,
        ]);
    }
}

<?php

namespace App\Actions;

use App\Enums\AccountType;
use App\Enums\BudgetPhase;
use App\Enums\CategoryType;
use App\Enums\FinancialOperationType;
use App\Enums\Rhythm;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\FinancialOperation;
use App\Models\User;
use App\Support\CategoryList\CategoryListSequence;
use App\Support\CategoryList\CategoryNameGuard;
use App\Support\CategoryList\CategoryNameKey;
use App\Support\SheetLoad\PlannedOperation;
use App\Support\SheetLoad\SheetLoadException;
use App\Support\SheetLoad\SheetLoadPlan;
use App\Support\SheetLoad\SheetMapping;
use App\Support\SheetLoad\SheetMappingCategoryCheck;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The Sheet Load of one past year (ADR-0085): a Budget in execution, a Sheet Account and the Financial Operations
 * of the plan, written straight to the tables like `PreviousYearMockSeeder`. No domain action runs here, because
 * each of them would change the 2027 draft, the Rhythms or the balances of the real Accounts.
 */
class LoadBudgetSheet
{
    private const int INSERT_CHUNK = 500;

    public function __construct(
        private readonly ChangeCategoryRhythm $changeCategoryRhythm,
        private readonly SheetMappingCategoryCheck $categoryCheck,
    ) {}

    /**
     * @throws SheetLoadException when the year is already there, a mapped Category does not match the database or the written sums do not hold
     */
    public function handle(User $user, SheetLoadPlan $plan, SheetMapping $mapping): Budget
    {
        return DB::transaction(function () use ($user, $plan, $mapping): Budget {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $this->assertYearFree($user, $plan->year);

            $problems = $this->categoryCheck->problems($user, $mapping);

            if ($problems !== []) {
                throw new SheetLoadException("Kategorie {$plan->year}", implode(' ', $problems));
            }

            $newCategoryId = array_key_exists(SheetMapping::NEW_CATEGORY, $plan->categoryTotals())
                ? $this->newCategoryId($user, $mapping)
                : 0;
            $budget = Budget::query()->create([
                'user_id' => $user->id,
                'year' => $plan->year,
                'phase' => BudgetPhase::Execution,
            ]);
            $opening = $plan->openingBalanceMinorUnits();
            $account = Account::query()->create([
                'user_id' => $user->id,
                'name' => 'Arkusz '.$plan->year,
                'type' => AccountType::Cash,
                'bank' => null,
                'opening_date' => CarbonImmutable::create($plan->year, 1, 1)->toDateString(),
                'opening_balance_minor_units' => $opening,
                'current_balance_minor_units' => 0,
                'archived_at' => now(),
                'sheet_year' => $plan->year,
            ]);

            $lines = $this->createLines($budget, $plan, $newCategoryId);
            $this->insertOperations($user, $budget, $account, $plan, $lines, $newCategoryId);
            $this->writeExecution($budget);
            $this->assertSums($budget, $account, $plan);

            return $budget;
        });
    }

    private function assertYearFree(User $user, int $year): void
    {
        if ($user->budgets()->where('year', $year)->exists()) {
            throw new SheetLoadException("Budżet {$year}", 'użytkownik ma już Budżet tego roku; najpierw budget:unload-sheet.');
        }

        if ($user->accounts()->where('sheet_year', $year)->exists()) {
            throw new SheetLoadException("Arkusz {$year}", 'użytkownik ma już konto arkusza tego roku; najpierw budget:unload-sheet.');
        }
    }

    /**
     * The existing expense Category of the new Category's key, or a new archived one with a single Running entry.
     * `ChangeCategoryRhythm` runs only for the new one, since it deletes the entries from the given month.
     */
    private function newCategoryId(User $user, SheetMapping $mapping): int
    {
        $existing = $this->categoryCheck->existingNewCategory($user, $mapping);

        if ($existing !== null) {
            return $existing->id;
        }

        $new = $mapping->newCategory();
        $name = CategoryNameKey::trim($new['name']);
        $nameKey = CategoryNameKey::of($name);
        CategoryNameGuard::assertFree($user->id, CategoryType::Expense, $nameKey);

        $category = Category::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'name_key' => $nameKey,
            'icon' => $new['icon'],
            'color' => $new['color'],
            'type' => CategoryType::Expense,
            'sort_order' => CategoryListSequence::nextSortOrder($user->id, CategoryType::Expense),
            'archived_at' => now(),
        ]);

        $this->changeCategoryRhythm->handle($category, Rhythm::Running, CarbonImmutable::parse($new['rhythm_start'].'-01'));

        return $category->id;
    }

    /**
     * Lines only for Categories with operations, plan 0 until the operations are in.
     *
     * @return array<int, int> Category id => Budget Line id
     */
    private function createLines(Budget $budget, SheetLoadPlan $plan, int $newCategoryId): array
    {
        $now = now();
        $rows = array_map(fn (int|string $target): array => [
            'budget_id' => $budget->id,
            'category_id' => $this->categoryId($target, $newCategoryId),
            'planned_amount_minor_units' => 0,
            'initial_plan_minor_units' => 0,
            'execution_minor_units' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ], array_keys($plan->categoryTotals()));

        BudgetLine::query()->insert($rows);

        /** @var array<int, int> $lines */
        $lines = BudgetLine::query()->where('budget_id', $budget->id)->pluck('id', 'category_id')->all();

        return $lines;
    }

    /**
     * @param  array<int, int>  $lines
     */
    private function insertOperations(User $user, Budget $budget, Account $account, SheetLoadPlan $plan, array $lines, int $newCategoryId): void
    {
        $now = now();
        $rows = array_map(fn (PlannedOperation $operation): array => [
            'user_id' => $user->id,
            'type' => $operation->type->value,
            'account_id' => $account->id,
            'budget_line_id' => $lines[$this->categoryId($operation->category, $newCategoryId)],
            'budget_id' => $budget->id,
            'operation_date' => $operation->date,
            'amount_minor_units' => $operation->amountMinorUnits,
            'description' => $operation->description,
            'counterparty' => null,
            'raw_statement_description' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $plan->operations);

        foreach (array_chunk($rows, self::INSERT_CHUNK) as $chunk) {
            FinancialOperation::query()->insert($chunk);
        }
    }

    /**
     * Initial Plan, Current Plan and Execution of a line are all the sum of its operations.
     */
    private function writeExecution(Budget $budget): void
    {
        DB::update(
            'update budget_lines set execution_minor_units = sums.total, initial_plan_minor_units = sums.total, planned_amount_minor_units = sums.total'
            .' from (select budget_line_id, sum(amount_minor_units) as total from financial_operations where budget_id = ? group by budget_line_id) as sums'
            .' where budget_lines.id = sums.budget_line_id',
            [$budget->id],
        );
    }

    private function assertSums(Budget $budget, Account $account, SheetLoadPlan $plan): void
    {
        $written = FinancialOperation::query()
            ->where('budget_id', $budget->id)
            ->selectRaw('type, SUM(amount_minor_units) as total')
            ->groupBy('type')
            ->pluck('total', 'type');
        $income = (int) ($written[FinancialOperationType::Income->value] ?? 0);
        $expense = (int) ($written[FinancialOperationType::Expense->value] ?? 0);

        if ($income !== $plan->incomeTotal() || $expense !== $plan->expenseTotal()) {
            throw new SheetLoadException("Arkusz {$plan->year}", 'zapisane sumy przychodów i wydatków różnią się od planu.');
        }

        if ($account->opening_balance_minor_units + $income - $expense !== $account->current_balance_minor_units) {
            throw new SheetLoadException("Arkusz {$plan->year}", 'saldo konta arkusza po wczytaniu nie wynosi 0.');
        }

        $lines = BudgetLine::query()->where('budget_id', $budget->id)->get();

        if ($lines->contains(fn (BudgetLine $line): bool => $line->execution_minor_units !== $line->initial_plan_minor_units
            || $line->execution_minor_units !== $line->planned_amount_minor_units)
            || (int) $lines->sum('execution_minor_units') !== $income + $expense) {
            throw new SheetLoadException("Arkusz {$plan->year}", 'Execution linii nie równa się sumie operacji.');
        }
    }

    private function categoryId(int|string $target, int $newCategoryId): int
    {
        return $target === SheetMapping::NEW_CATEGORY ? $newCategoryId : (int) $target;
    }
}

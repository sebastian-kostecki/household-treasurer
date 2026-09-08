<?php

namespace App\Http\Controllers;

use App\Enums\CategoryType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use App\Support\AccountIdentity;
use App\Support\BudgetPlanSummary;
use App\Support\CategoryAppearance;
use App\Support\CategoryExecutionMetrics;
use App\Support\SelectedBudgetYear;
use App\Support\ShellState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();
        $selectedYear = SelectedBudgetYear::resolve($request);
        $budget = $this->findBudgetForYear($user, $selectedYear);

        $accounts = $user->accounts()
            ->orderBy('name')
            ->get()
            ->map(fn (Account $account): array => $this->accountPayload($account))
            ->values()
            ->all();

        $incomeCategories = [];
        $expenseCategories = [];
        $emptyTotals = BudgetPlanSummary::emptyTypeTotals();
        $incomeTotals = [...$emptyTotals];
        $expenseTotals = [...$emptyTotals];
        $planSummary = null;

        if ($budget !== null) {
            $categories = $budget->categories()
                ->ordered()
                ->get();

            $incomeCategories = $categories
                ->where('type', CategoryType::Income)
                ->values()
                ->map(fn (Category $category): array => $this->categoryPayload($category, $budget))
                ->all();

            $expenseCategories = $categories
                ->where('type', CategoryType::Expense)
                ->values()
                ->map(fn (Category $category): array => $this->categoryPayload($category, $budget))
                ->all();

            $planSummary = BudgetPlanSummary::forBudget($budget, $categories);
            $incomeTotals = $planSummary['income'];
            $expenseTotals = $planSummary['expense'];
        }

        return Inertia::render('Dashboard', [
            'accounts' => $accounts,
            'liquidity_total_grosze' => (int) $user->accounts()->sum('current_balance_grosze'),
            'budget' => $budget === null ? null : [
                'year' => $budget->year,
                'phase' => $budget->phase->value,
            ],
            'incomeCategories' => $incomeCategories,
            'expenseCategories' => $expenseCategories,
            'planSummary' => $planSummary,
            'incomeTotals' => $incomeTotals,
            'expenseTotals' => $expenseTotals,
        ]);
    }

    public function dismissFirstRunChecklist(Request $request): RedirectResponse
    {
        $user = $request->user();
        $firstRun = ShellState::forRequest($request)['firstRun'];

        if (! ShellState::isFirstRunComplete($firstRun)) {
            abort(403);
        }

        if ($user->first_run_checklist_dismissed_at === null) {
            $user->first_run_checklist_dismissed_at = now();
            $user->save();
        }

        return to_route('dashboard');
    }

    private function findBudgetForYear(User $user, ?int $year): ?Budget
    {
        if ($year === null) {
            return null;
        }

        return $user->budgets()->where('year', $year)->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function accountPayload(Account $account): array
    {
        return [
            ...AccountIdentity::payload($account),
            'opening_date' => $account->opening_date->toDateString(),
            'current_balance_grosze' => $account->current_balance_grosze,
            'is_negative' => $account->isNegative(),
            'is_archived' => $account->isArchived(),
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     icon: string,
     *     color: string,
     *     initial_plan_grosze: int,
     *     current_plan_grosze: int,
     *     execution_grosze: int,
     *     remaining_grosze: int,
     *     percent_complete: int|null,
     *     is_over_limit: bool,
     *     is_archived: bool,
     * }
     */
    private function categoryPayload(Category $category, Budget $budget): array
    {
        $currentPlanGrosze = $category->planned_amount_grosze;
        $executionGrosze = $category->execution_grosze;
        $initialPlanGrosze = $budget->isExecution()
            ? $category->initial_plan_grosze
            : $category->planned_amount_grosze;

        return [
            'id' => $category->id,
            'name' => $category->name,
            ...CategoryAppearance::fields($category),
            'initial_plan_grosze' => $initialPlanGrosze,
            'current_plan_grosze' => $currentPlanGrosze,
            'execution_grosze' => $executionGrosze,
            'remaining_grosze' => CategoryExecutionMetrics::remainingGrosze(
                $currentPlanGrosze,
                $executionGrosze,
            ),
            'percent_complete' => CategoryExecutionMetrics::percentComplete(
                $currentPlanGrosze,
                $executionGrosze,
            ),
            'is_over_limit' => $category->type === CategoryType::Expense
                && CategoryExecutionMetrics::isOverLimit($currentPlanGrosze, $executionGrosze),
            'is_archived' => $category->isArchived(),
        ];
    }
}

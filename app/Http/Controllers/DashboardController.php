<?php

namespace App\Http\Controllers;

use App\Enums\CategoryType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\TermDeposit;
use App\Models\User;
use App\Support\AccountIdentity;
use App\Support\BudgetPlanSummary;
use App\Support\CategoryAppearance;
use App\Support\CategoryExecutionMetrics;
use App\Support\DashboardNotices;
use App\Support\MonthPace;
use App\Support\MonthToSpend;
use App\Support\ShellState;
use App\Support\SpendableLiquidity;
use App\Support\TotalFunds;
use App\Support\YearElapsed;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();
        $today = CarbonImmutable::today('UTC');
        $currentYear = $today->year;
        $budget = $this->findBudgetForYear($user, $currentYear);

        $allAccounts = $user->accounts()
            ->orderBy('name')
            ->get();

        $accounts = $allAccounts
            ->filter(fn (Account $account): bool => ! $account->isArchived())
            ->values()
            ->map(fn (Account $account): array => $this->accountPayload($account))
            ->all();

        $allTermDeposits = $user->termDeposits()
            ->orderBy('name')
            ->get();

        $termDeposits = $allTermDeposits
            ->filter(fn (TermDeposit $termDeposit): bool => ! $termDeposit->isArchived())
            ->values()
            ->map(fn (TermDeposit $termDeposit): array => $this->termDepositPayload($termDeposit))
            ->all();

        $spendableLiquidityMinorUnits = SpendableLiquidity::forUser($user);

        $incomeCategories = [];
        $expenseCategories = [];
        $dashboardNotices = [];
        $monthToSpend = null;
        $monthToSpendReading = null;
        $monthPace = null;
        $currentMonth = null;
        $emptyTotals = BudgetPlanSummary::emptyTypeTotals();
        $incomeTotals = [...$emptyTotals];
        $expenseTotals = [...$emptyTotals];
        $planSummary = null;
        $yearElapsedAsOf = null;
        $yearElapsedPercent = null;

        if ($budget !== null) {
            $allCategories = $budget->categories()
                ->ordered()
                ->get();
            $visibleCategories = $allCategories->filter(
                fn (Category $category): bool => ! $category->isArchived(),
            );
            $visibleExpenseCategories = $visibleCategories
                ->where('type', CategoryType::Expense)
                ->values();
            $asOf = YearElapsed::asOfDate($budget->year, $today);
            $yearElapsedAsOf = $asOf->toDateString();
            $yearElapsedPercent = YearElapsed::percent($asOf);

            $planSummary = BudgetPlanSummary::forBudget($budget, $visibleCategories);
            $incomeTotals = $planSummary['income'];
            $expenseTotals = $planSummary['expense'];

            if ($budget->isExecution()) {
                $incomeCategories = $visibleCategories
                    ->where('type', CategoryType::Income)
                    ->values()
                    ->map(fn (Category $category): array => $this->categoryPayload($category, $budget))
                    ->all();

                $expenseCategories = $visibleExpenseCategories
                    ->map(fn (Category $category): array => $this->categoryPayload($category, $budget))
                    ->all();

                $currentMonth = $today->month;
                $monthToSpendReading = MonthToSpend::forMonth($budget, $currentMonth, $today);
                $monthPace = MonthPace::forMonth($monthToSpendReading, $budget->year, $currentMonth, $today);
                $summary = $monthToSpendReading->summary();

                if ($summary['state'] !== 'none') {
                    $monthToSpend = [
                        'state' => $summary['state'],
                        'to_spend_minor_units' => (int) $summary['to_spend_minor_units'],
                    ];
                }
            }
        }

        $dashboardNotices = DashboardNotices::forDashboard(
            $budget,
            $allAccounts,
            $allTermDeposits,
            $monthToSpendReading,
            $today,
        );

        return Inertia::render('Dashboard', [
            'accounts' => $accounts,
            'termDeposits' => $termDeposits,
            'spendable_liquidity_minor_units' => $spendableLiquidityMinorUnits,
            'total_funds_minor_units' => TotalFunds::forUser($user),
            'budget_year' => $currentYear,
            'budget' => $budget === null ? null : [
                'year' => $budget->year,
                'phase' => $budget->phase->value,
            ],
            'incomeCategories' => $incomeCategories,
            'expenseCategories' => $expenseCategories,
            'dashboardNotices' => $dashboardNotices,
            'month_to_spend' => $monthToSpend,
            'month_pace' => $monthPace,
            'current_month' => $currentMonth,
            'planSummary' => $planSummary,
            'incomeTotals' => $incomeTotals,
            'expenseTotals' => $expenseTotals,
            'year_elapsed_as_of' => $yearElapsedAsOf,
            'year_elapsed_percent' => $yearElapsedPercent,
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
            'current_balance_minor_units' => $account->current_balance_minor_units,
            'is_negative' => $account->isNegative(),
            'is_archived' => $account->isArchived(),
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     bank: string,
     *     mark_url: string|null,
     *     end_date: string|null,
     *     current_balance_minor_units: int,
     *     is_negative: bool,
     *     is_archived: bool,
     * }
     */
    private function termDepositPayload(TermDeposit $termDeposit): array
    {
        return [
            'id' => $termDeposit->id,
            'name' => $termDeposit->name,
            'bank' => $termDeposit->bank->value,
            'mark_url' => AccountIdentity::markUrl($termDeposit->bank),
            'end_date' => $termDeposit->end_date?->toDateString(),
            'current_balance_minor_units' => $termDeposit->current_balance_minor_units,
            'is_negative' => $termDeposit->isNegative(),
            'is_archived' => $termDeposit->isArchived(),
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     icon: string,
     *     color: string,
     *     initial_plan_minor_units: int,
     *     current_plan_minor_units: int,
     *     execution_minor_units: int,
     *     remaining_minor_units: int,
     *     percent_complete: int|null,
     *     is_over_limit: bool,
     *     is_archived: bool,
     *     is_paced?: bool,
     * }
     */
    private function categoryPayload(
        Category $category,
        Budget $budget,
    ): array {
        $currentPlanMinorUnits = $category->planned_amount_minor_units;
        $executionMinorUnits = $category->execution_minor_units;
        $initialPlanMinorUnits = $budget->isExecution()
            ? $category->initial_plan_minor_units
            : $category->planned_amount_minor_units;

        return [
            'id' => $category->id,
            'name' => $category->name,
            ...CategoryAppearance::fields($category),
            'initial_plan_minor_units' => $initialPlanMinorUnits,
            'current_plan_minor_units' => $currentPlanMinorUnits,
            'execution_minor_units' => $executionMinorUnits,
            'remaining_minor_units' => CategoryExecutionMetrics::remainingMinorUnits(
                $currentPlanMinorUnits,
                $executionMinorUnits,
            ),
            'percent_complete' => CategoryExecutionMetrics::percentComplete(
                $currentPlanMinorUnits,
                $executionMinorUnits,
            ),
            'is_over_limit' => $category->type === CategoryType::Expense
                && CategoryExecutionMetrics::isOverLimit($currentPlanMinorUnits, $executionMinorUnits),
            'is_archived' => $category->isArchived(),
            ...($category->type === CategoryType::Expense ? [
                'is_paced' => $category->is_paced,
            ] : []),
        ];
    }
}

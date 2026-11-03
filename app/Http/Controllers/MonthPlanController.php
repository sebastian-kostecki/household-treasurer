<?php

namespace App\Http\Controllers;

use App\Actions\ApproveMonthPlan;
use App\Actions\ChangeMonthPlan;
use App\Enums\CategoryType;
use App\Http\Requests\ApproveMonthPlanRequest;
use App\Http\Requests\UpdateMonthPlanRequest;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use App\Support\BudgetViewedMonth;
use App\Support\CategoryAppearance;
use App\Support\CategoryExecutionMetrics;
use App\Support\MonthItemDeviation;
use App\Support\MonthToSpend;
use App\Support\MonthYearComparison;
use App\Support\SelectedBudgetYear;
use App\Support\SpendableLiquidity;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MonthPlanController extends Controller
{
    use AuthorizesRequests;

    public function show(Request $request): Response
    {
        $selectedYear = SelectedBudgetYear::resolve($request);
        $budget = $selectedYear === null
            ? null
            : $request->user()->budgets()->where('year', $selectedYear)->first();

        if ($budget === null) {
            return Inertia::render('MonthPlan', [
                'budget' => null,
                'viewedMonth' => null,
                'viewedMonthTiming' => null,
                'currentMonth' => null,
                'monthPlan' => null,
            ]);
        }

        $this->authorize('view', $budget);

        $today = CarbonImmutable::today('UTC');
        $viewedMonth = BudgetViewedMonth::resolve(
            $request->query('month'),
            $budget->year,
            $today,
        );
        $timing = $this->monthTiming($budget->year, $viewedMonth, $today);

        return Inertia::render('MonthPlan', [
            'budget' => [
                'id' => $budget->id,
                'year' => $budget->year,
                'phase' => $budget->phase->value,
            ],
            'viewedMonth' => $viewedMonth,
            'viewedMonthTiming' => $timing,
            'currentMonth' => $today->year === $budget->year ? $today->month : null,
            'monthPlan' => $budget->isExecution()
                ? $this->monthPlanPayload($request->user(), $budget, $viewedMonth, $timing, $today)
                : null,
        ]);
    }

    public function approve(
        ApproveMonthPlanRequest $request,
        Budget $budget,
        int $month,
        ApproveMonthPlan $approveMonthPlan,
    ): RedirectResponse {
        $amounts = [];

        foreach ($request->validated('items', []) as $item) {
            $amounts[(int) $item['category_id']] = (int) $item['amount_minor_units'];
        }

        $approveMonthPlan->handle(
            $budget,
            $month,
            $amounts,
            (int) $request->validated('set_aside_minor_units'),
            CarbonImmutable::today('UTC'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Month plan approved.'),
        ]);

        return to_route('month-plan', ['month' => $month]);
    }

    public function update(
        UpdateMonthPlanRequest $request,
        Budget $budget,
        int $month,
        ChangeMonthPlan $changeMonthPlan,
    ): RedirectResponse {
        $amounts = [];

        foreach ($request->validated('items') as $item) {
            $amounts[(int) $item['category_id']] = (int) $item['amount_minor_units'];
        }

        $changeMonthPlan->handle(
            $budget,
            $month,
            $amounts,
            CarbonImmutable::today('UTC'),
            $request->has('set_aside_minor_units') ? (int) $request->validated('set_aside_minor_units') : null,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Month plan changed.'),
        ]);

        return to_route('month-plan', ['month' => $month]);
    }

    /**
     * @param  'past'|'current'|'future'  $timing
     * @return array{
     *     state: 'proposal'|'approved'|'none',
     *     approved_at: string|null,
     *     can_approve: bool,
     *     can_change: bool,
     *     to_spend_minor_units: int|null,
     *     set_aside_minor_units: int,
     *     spendable_liquidity_minor_units: int|null,
     *     income_items: list<array<string, mixed>>,
     *     expense_items: list<array<string, mixed>>,
     *     year_totals: array{income: array<string, int|bool>, expense: array<string, int|bool>}|null,
     * }
     */
    private function monthPlanPayload(User $user, Budget $budget, int $month, string $timing, CarbonImmutable $today): array
    {
        $toSpend = MonthToSpend::forMonth($budget, $month, $today);
        $reading = $toSpend->reading;

        $canChange = $reading->state === 'approved' && $timing !== 'past';

        $categories = $toSpend->categories;
        $joinCandidateIds = [];

        if ($canChange) {
            $knownIds = $categories->pluck('id')->all();

            $joinCandidateIds = $budget->categories
                ->whereNull('archived_at')
                ->whereNotIn('id', $knownIds)
                ->pluck('id')
                ->all();

            if ($joinCandidateIds !== []) {
                $categories = $budget->categories()
                    ->whereIn('id', [...$knownIds, ...$joinCandidateIds])
                    ->ordered()
                    ->get();
            }
        }

        $incomeItems = [];
        $expenseItems = [];

        foreach ($categories as $category) {
            $row = $this->itemRow(
                $category,
                $toSpend,
                $timing,
                in_array($category->id, $joinCandidateIds, true),
            );

            if ($category->type === CategoryType::Income) {
                $incomeItems[] = $row;
            } else {
                $expenseItems[] = $row;
            }
        }

        return [
            'state' => $reading->state,
            'approved_at' => $reading->approvedAt?->toIso8601String(),
            'can_approve' => $reading->state === 'proposal'
                && $timing !== 'past'
                && ! $toSpend->proposal->isBlockedByPreviousMonth($month),
            'can_change' => $canChange,
            'to_spend_minor_units' => $toSpend->toSpendMinorUnits(),
            'set_aside_minor_units' => $reading->setAsideMinorUnits,
            'spendable_liquidity_minor_units' => $timing !== 'past' && $reading->state !== 'none'
                ? SpendableLiquidity::forUser($user)
                : null,
            'income_items' => $incomeItems,
            'expense_items' => $expenseItems,
            'year_totals' => $timing === 'current'
                ? $this->yearTotals($incomeItems, $expenseItems)
                : null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $incomeItems
     * @param  list<array<string, mixed>>  $expenseItems
     * @return array{income: array<string, int|bool>, expense: array<string, int|bool>}
     */
    private function yearTotals(array $incomeItems, array $expenseItems): array
    {
        return [
            'income' => MonthYearComparison::totals(array_column($incomeItems, 'year_comparison'), CategoryType::Income),
            'expense' => MonthYearComparison::totals(array_column($expenseItems, 'year_comparison'), CategoryType::Expense),
        ];
    }

    /**
     * @param  'past'|'current'|'future'  $timing
     * @return array{
     *     category_id: int,
     *     name: string,
     *     icon: string,
     *     color: string,
     *     type: string,
     *     month_item_kind: string|null,
     *     is_one_off: bool,
     *     is_archived: bool,
     *     plan_minor_units: int,
     *     actual_minor_units: int,
     *     has_positive_amount: bool,
     *     can_change_kind: bool,
     *     in_plan: bool,
     *     deviation_minor_units: int|null,
     *     is_over_plan: bool,
     *     is_settled: bool,
     *     available_limit_minor_units: int|null,
     *     is_idle_one_off: bool,
     *     approved_minor_units: int|null,
     *     is_join_candidate: bool,
     *     year_comparison: array{year_minor_units: int, annual_plan_minor_units: int, remaining_minor_units: int, is_over_year: bool}|null,
     * }
     */
    private function itemRow(Category $category, MonthToSpend $toSpend, string $timing, bool $isJoinCandidate): array
    {
        $isExpense = $category->type === CategoryType::Expense;
        $isOneOff = $isExpense && ! $category->is_paced;
        $amounts = $toSpend->rowAmounts($category);
        $plan = $amounts['plan_minor_units'];
        $actual = $amounts['actual_minor_units'];
        $hasPositiveAmount = $amounts['has_positive_amount'];

        return [
            'category_id' => $category->id,
            'name' => $category->name,
            ...CategoryAppearance::fields($category),
            'type' => $category->type->value,
            'month_item_kind' => $isExpense ? $category->month_item_kind->value : null,
            'is_one_off' => $isOneOff,
            'is_archived' => $category->isArchived(),
            'plan_minor_units' => $plan,
            'actual_minor_units' => $actual,
            'has_positive_amount' => $hasPositiveAmount,
            'can_change_kind' => $isExpense && ! $category->isArchived() && $timing !== 'past',
            'in_plan' => array_key_exists($category->id, $toSpend->reading->amounts),
            'approved_minor_units' => $toSpend->reading->approvedAmounts[$category->id] ?? null,
            'is_join_candidate' => $isJoinCandidate,
            'deviation_minor_units' => MonthItemDeviation::deviationMinorUnits(
                $category->type,
                $category->month_item_kind,
                $plan,
                $actual,
                $hasPositiveAmount,
            ),
            'is_over_plan' => MonthItemDeviation::isOverPlan($category->type, $plan, $actual),
            'is_settled' => MonthItemDeviation::isSettled($category->type, $category->month_item_kind, $hasPositiveAmount),
            'available_limit_minor_units' => $isOneOff
                ? CategoryExecutionMetrics::remainingMinorUnits(
                    $category->planned_amount_minor_units,
                    $category->execution_minor_units,
                )
                : null,
            'is_idle_one_off' => $isOneOff && $plan === 0 && ! $toSpend->actuals->hasOperations($category->id),
            'year_comparison' => $timing === 'current'
                ? MonthYearComparison::forCategory($category, $amounts)
                : null,
        ];
    }

    /**
     * @return 'past'|'current'|'future'
     */
    private function monthTiming(int $year, int $month, CarbonImmutable $today): string
    {
        $viewed = [$year, $month];
        $now = [$today->year, $today->month];

        if ($viewed < $now) {
            return 'past';
        }

        return $viewed === $now ? 'current' : 'future';
    }
}

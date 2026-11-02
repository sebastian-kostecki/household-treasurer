<?php

namespace App\Http\Controllers;

use App\Actions\ApproveMonthPlan;
use App\Enums\CategoryType;
use App\Http\Requests\ApproveMonthPlanRequest;
use App\Models\Budget;
use App\Models\Category;
use App\Support\BudgetViewedMonth;
use App\Support\CategoryAppearance;
use App\Support\CategoryExecutionMetrics;
use App\Support\MonthCategoryActuals;
use App\Support\MonthItemDeviation;
use App\Support\MonthPlanProposal;
use App\Support\MonthPlanReading;
use App\Support\SelectedBudgetYear;
use App\Support\ToSpendCalculator;
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
                ? $this->monthPlanPayload($budget, $viewedMonth, $timing, $today)
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

        $approveMonthPlan->handle($budget, $month, $amounts, CarbonImmutable::today('UTC'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Month plan approved.'),
        ]);

        return to_route('month-plan', ['month' => $month]);
    }

    /**
     * @param  'past'|'current'|'future'  $timing
     * @return array{
     *     state: 'proposal'|'approved'|'none',
     *     approved_at: string|null,
     *     can_approve: bool,
     *     to_spend_minor_units: int|null,
     *     income_items: list<array<string, mixed>>,
     *     expense_items: list<array<string, mixed>>,
     * }
     */
    private function monthPlanPayload(Budget $budget, int $month, string $timing, CarbonImmutable $today): array
    {
        $proposal = MonthPlanProposal::forBudget($budget, $today);
        $reading = $proposal->reading($month);
        $actuals = MonthCategoryActuals::forMonth($budget, $month);

        $rowIds = array_values(array_unique([
            ...array_keys($reading->amounts),
            ...$actuals->categoryIds(),
        ]));

        $categories = $budget->categories()
            ->whereIn('id', $rowIds)
            ->ordered()
            ->get();

        $calculatorItems = [];
        $incomeItems = [];
        $expenseItems = [];

        foreach ($categories as $category) {
            $row = $this->itemRow($category, $reading, $actuals, $timing);

            $calculatorItems[] = [
                'type' => $category->type,
                'kind' => $category->month_item_kind,
                'plan' => $row['plan_minor_units'],
                'actual' => $row['actual_minor_units'],
                'has_positive_amount' => $row['has_positive_amount'],
            ];

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
                && ! $proposal->isBlockedByPreviousMonth($month),
            'to_spend_minor_units' => $reading->state === 'none'
                ? null
                : ToSpendCalculator::toSpendMinorUnits($calculatorItems),
            'income_items' => $incomeItems,
            'expense_items' => $expenseItems,
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
     * }
     */
    private function itemRow(
        Category $category,
        MonthPlanReading $reading,
        MonthCategoryActuals $actuals,
        string $timing,
    ): array {
        $isExpense = $category->type === CategoryType::Expense;
        $isOneOff = $isExpense && ! $category->is_paced;
        $plan = $reading->amounts[$category->id] ?? 0;
        $actual = $actuals->actual($category->id);
        $hasPositiveAmount = $actuals->hasPositiveAmount($category->id);

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
            'in_plan' => array_key_exists($category->id, $reading->amounts),
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
            'is_idle_one_off' => $isOneOff && $plan === 0 && ! $actuals->hasOperations($category->id),
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

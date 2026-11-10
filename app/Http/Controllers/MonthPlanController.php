<?php

namespace App\Http\Controllers;

use App\Actions\ApproveMonthPlan;
use App\Actions\ChangeMonthPlan;
use App\Enums\CategoryType;
use App\Http\Requests\ApproveMonthPlanRequest;
use App\Http\Requests\UpdateMonthPlanRequest;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\User;
use App\Support\BudgetViewedMonth;
use App\Support\MonthPace;
use App\Support\MonthPlanItemRow;
use App\Support\MonthPlanJoinCandidates;
use App\Support\MonthSummary;
use App\Support\MonthToSpend;
use App\Support\MonthYearComparison;
use App\Support\SelectedBudgetYear;
use App\Support\SpendableLiquidity;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * @phpstan-import-type YearComparison from MonthYearComparison
 */
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
     *     to_spend_income_minor_units: int|null,
     *     to_spend_expense_minor_units: int|null,
     *     month_states: list<'summary'|'approved'|'proposal'|null>,
     *     set_aside_minor_units: int,
     *     spendable_liquidity_minor_units: int|null,
     *     month_pace: array{as_of: string, elapsed_day: int, days_in_month: int, pool_plan_minor_units: int, pool_actual_minor_units: int, percent: int|null, is_ahead: bool}|null,
     *     income_items: list<array<string, mixed>>,
     *     expense_items: list<array<string, mixed>>,
     *     year_totals: array{income: YearComparison, expense: YearComparison}|null,
     *     summary: array{
     *         income: array{approved_minor_units: int, final_minor_units: int, actual_minor_units: int, deviation_minor_units: int},
     *         expense: array{approved_minor_units: int, final_minor_units: int, actual_minor_units: int, deviation_minor_units: int},
     *         set_aside: array{approved_minor_units: int, final_minor_units: int, real_minor_units: int, difference_minor_units: int},
     *         year_draw_minor_units: int,
     *     }|null,
     * }
     */
    private function monthPlanPayload(User $user, Budget $budget, int $month, string $timing, CarbonImmutable $today): array
    {
        $toSpend = MonthToSpend::forMonth($budget, $month, $today);
        $reading = $toSpend->reading;
        $breakdown = $toSpend->toSpendBreakdown();

        $canChange = $reading->state === 'approved' && $timing !== 'past';

        $canApprove = $reading->state === 'proposal'
            && $timing !== 'past'
            && ! $toSpend->proposal->isBlockedByPreviousMonth($month);

        [$categories, $joinCandidateIds] = $this->categoriesWithJoinCandidates($budget, $toSpend->categories, $canChange);

        [$incomeItems, $expenseItems] = $this->itemRows($categories, $joinCandidateIds, $toSpend, $timing, $month, $today);

        if ($canChange || $canApprove) {
            $joining = MonthPlanJoinCandidates::rows($user, $budget, $timing, $month, $today);
            $incomeItems = [...$incomeItems, ...$joining['income']];
            $expenseItems = [...$expenseItems, ...$joining['expense']];
        }

        return [
            'state' => $reading->state,
            'approved_at' => $reading->approvedAt?->toIso8601String(),
            'can_approve' => $canApprove,
            'can_change' => $canChange,
            'to_spend_minor_units' => $breakdown['to_spend_minor_units'] ?? null,
            'to_spend_income_minor_units' => $breakdown['income_minor_units'] ?? null,
            'to_spend_expense_minor_units' => $breakdown['expense_minor_units'] ?? null,
            'month_states' => $toSpend->proposal->monthStates(),
            'set_aside_minor_units' => $reading->setAsideMinorUnits,
            'spendable_liquidity_minor_units' => $timing !== 'past' && $reading->state !== 'none'
                ? SpendableLiquidity::forUser($user)
                : null,
            'month_pace' => $timing === 'future'
                ? null
                : MonthPace::forMonth($toSpend, $budget->year, $month, $today),
            'income_items' => $incomeItems,
            'expense_items' => $expenseItems,
            'year_totals' => $timing === 'current'
                ? $this->yearTotals($incomeItems, $expenseItems)
                : null,
            'summary' => $timing === 'past' ? MonthSummary::forMonth($toSpend, $budget, $month)?->toArray() : null,
        ];
    }

    /**
     * @param  Collection<int, BudgetLine>  $categories
     * @return array{0: Collection<int, BudgetLine>, 1: list<int>}
     */
    private function categoriesWithJoinCandidates(Budget $budget, Collection $categories, bool $canChange): array
    {
        if (! $canChange) {
            return [$categories, []];
        }

        $knownIds = $categories->pluck('id')->all();

        $joinCandidateIds = $budget->budgetLines()
            ->openForChoice()
            ->whereNotIn('id', $knownIds)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        if ($joinCandidateIds === []) {
            return [$categories, []];
        }

        return [
            $budget->budgetLines()
                ->whereIn('id', [...$knownIds, ...$joinCandidateIds])
                ->ordered()
                ->get(),
            array_values($joinCandidateIds),
        ];
    }

    /**
     * @param  iterable<BudgetLine>  $categories
     * @param  list<int>  $joinCandidateIds
     * @param  'past'|'current'|'future'  $timing
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function itemRows(iterable $categories, array $joinCandidateIds, MonthToSpend $toSpend, string $timing, int $month, CarbonImmutable $today): array
    {
        $incomeItems = [];
        $expenseItems = [];

        foreach ($categories as $category) {
            $row = MonthPlanItemRow::forLine(
                $category,
                $toSpend,
                $timing,
                in_array($category->id, $joinCandidateIds, true),
                $month,
                $today,
            );

            if ($category->hasType(CategoryType::Income)) {
                $incomeItems[] = $row;
            } else {
                $expenseItems[] = $row;
            }
        }

        return [$incomeItems, $expenseItems];
    }

    /**
     * @param  list<array<string, mixed>>  $incomeItems
     * @param  list<array<string, mixed>>  $expenseItems
     * @return array{income: YearComparison, expense: YearComparison}
     */
    private function yearTotals(array $incomeItems, array $expenseItems): array
    {
        return [
            'income' => MonthYearComparison::totals(array_column($incomeItems, 'year_comparison'), CategoryType::Income),
            'expense' => MonthYearComparison::totals(array_column($expenseItems, 'year_comparison'), CategoryType::Expense),
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

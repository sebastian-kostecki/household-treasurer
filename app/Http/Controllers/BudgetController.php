<?php

namespace App\Http\Controllers;

use App\Actions\CreateBudget;
use App\Actions\StartBudgetExecution;
use App\Enums\CategoryType;
use App\Http\Requests\SelectBudgetYearRequest;
use App\Http\Requests\StoreBudgetRequest;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\User;
use App\Support\BudgetCollectedAtYearStart;
use App\Support\BudgetDeficitWarning;
use App\Support\BudgetPlanSummary;
use App\Support\BudgetViewedMonth;
use App\Support\CategoryAppearance;
use App\Support\CategoryExecutionMetrics;
use App\Support\CategoryList\CategoryTypeChange;
use App\Support\CategoryRhythms;
use App\Support\SelectedBudgetYear;
use App\Support\YearElapsed;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    use AuthorizesRequests;

    public function show(Request $request): Response
    {
        $user = $request->user();
        $selectedYear = SelectedBudgetYear::resolve($request);
        $budget = $this->findBudgetForYear($user, $selectedYear);

        if ($budget === null) {
            return Inertia::render('Budget', [
                'budget' => null,
                'incomeCategories' => [],
                'expenseCategories' => [],
                'incomeGroups' => [],
                'expenseGroups' => [],
                'planSummary' => null,
                'incomeTotals' => BudgetPlanSummary::emptyTypeTotals(),
                'expenseTotals' => BudgetPlanSummary::emptyTypeTotals(),
                'deficitWarning' => null,
                'show_archived' => false,
                'year_elapsed_percent' => null,
                'categoryAppearanceOptions' => CategoryAppearance::formOptions(),
            ]);
        }

        $allCategories = $budget->budgetLines()
            ->with('category.group')
            ->ordered()
            ->get();

        $showArchived = $request->boolean('show_archived');
        $today = CarbonImmutable::today('UTC');

        $categories = $showArchived
            ? $allCategories
            : $allCategories->filter(
                fn (BudgetLine $category): bool => ! $category->isArchived(),
            );

        $planSummary = BudgetPlanSummary::forBudget($budget, $categories);
        $typeLockedIds = CategoryTypeChange::lockedIds(
            array_values($categories->pluck('category_id')->map(fn ($id): int => (int) $id)->all()),
        );

        $rhythms = CategoryRhythms::forCategoryIds($categories->pluck('category_id'));
        $viewedMonth = BudgetViewedMonth::defaultForYear($budget->year, $today);
        $collectedAtYearStart = new BudgetCollectedAtYearStart($budget, $rhythms, $today);

        return Inertia::render('Budget', [
            'budget' => [
                'id' => $budget->id,
                'year' => $budget->year,
                'phase' => $budget->phase->value,
                'can_delete' => $budget->isDraft(),
                'can_start_execution' => $budget->isDraft(),
                'can_reorder' => $budget->isDraft() || $budget->isExecution(),
            ],
            'incomeCategories' => $categories
                ->filter(fn (BudgetLine $line): bool => $line->hasType(CategoryType::Income))
                ->values()
                ->map(fn (BudgetLine $line): array => $this->categoryPayload($line, $budget, $typeLockedIds, $rhythms, $viewedMonth, $collectedAtYearStart))
                ->all(),
            'expenseCategories' => $categories
                ->filter(fn (BudgetLine $line): bool => $line->hasType(CategoryType::Expense))
                ->values()
                ->map(fn (BudgetLine $line): array => $this->categoryPayload($line, $budget, $typeLockedIds, $rhythms, $viewedMonth, $collectedAtYearStart))
                ->all(),
            'incomeGroups' => BudgetPlanSummary::groupsOfType($budget, $categories, CategoryType::Income),
            'expenseGroups' => BudgetPlanSummary::groupsOfType($budget, $categories, CategoryType::Expense),
            'planSummary' => $planSummary,
            'incomeTotals' => $planSummary['income'],
            'expenseTotals' => $planSummary['expense'],
            'deficitWarning' => $budget->isDraft()
                ? BudgetDeficitWarning::forBudget($budget, $user, $categories)
                : null,
            'show_archived' => $showArchived,
            'year_elapsed_percent' => $budget->isExecution()
                ? YearElapsed::percent(YearElapsed::asOfDate($budget->year, $today))
                : null,
            'categoryAppearanceOptions' => CategoryAppearance::formOptions(),
        ]);
    }

    public function store(StoreBudgetRequest $request, CreateBudget $createBudget): RedirectResponse
    {
        $validated = $request->validated();

        $budget = $createBudget->handle($request->user(), $validated['year']);

        $request->session()->put('budget_year', $budget->year);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Budget created.'),
        ]);

        return to_route('budget');
    }

    public function destroy(Request $request, Budget $budget): RedirectResponse
    {
        $this->authorize('delete', $budget);

        $budget->delete();

        if ((int) $request->session()->get('budget_year') === $budget->year) {
            $request->session()->forget('budget_year');
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Budget deleted.'),
        ]);

        return to_route('budget');
    }

    public function selectYear(SelectBudgetYearRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $request->session()->put('budget_year', $validated['year']);

        return match ($validated['destination'] ?? 'budget') {
            'month-plan' => to_route('month-plan'),
            'reports' => to_route('reports'),
            default => to_route('budget'),
        };
    }

    public function startExecution(Request $request, Budget $budget, StartBudgetExecution $startBudgetExecution): RedirectResponse
    {
        $this->authorize('startExecution', $budget);

        $startBudgetExecution->handle($budget);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Budget execution started.'),
        ]);

        return to_route('budget');
    }

    private function findBudgetForYear(User $user, ?int $year): ?Budget
    {
        if ($year === null) {
            return null;
        }

        return $user->budgets()->where('year', $year)->first();
    }

    /**
     * One row of the Budget: the list Category (`id` is the Category id, not the
     * line id) with the line's amounts. `group_id` and `sort_order` place it in
     * the tree of the type (ADR-0069); the row of an archived line carries them
     * too, but the archive section shows no groups.
     *
     * @param  list<int>  $typeLockedIds  list Category ids whose type cannot change
     * @param  int  $viewedMonth  the month whose Rhythm the row shows (BudgetViewedMonth)
     * @param  BudgetCollectedAtYearStart  $collectedAtYearStart  the „zebrane startowe” reading (ADR-0076)
     * @return array{
     *     id: int,
     *     name: string,
     *     icon: string,
     *     color: string,
     *     type: string,
     *     rhythm: 'fixed_charge'|'running'|'even_annual'|'irregular'|null,
     *     group_id: int|null,
     *     sort_order: int,
     *     planned_amount_minor_units: int,
     *     initial_plan_minor_units: int,
     *     current_plan_minor_units: int,
     *     execution_minor_units: int,
     *     remaining_minor_units: int,
     *     percent_complete: int|null,
     *     is_over_limit: bool,
     *     can_edit: bool,
     *     can_delete: bool,
     *     can_change_type: bool,
     *     is_archived: bool,
     *     can_archive: bool,
     *     can_restore: bool,
     *     can_align_current_plan: bool,
     *     collected_at_year_start: array{minor_units: int, is_forecast: bool}|null,
     * }
     */
    private function categoryPayload(BudgetLine $line, Budget $budget, array $typeLockedIds, CategoryRhythms $rhythms, int $viewedMonth, BudgetCollectedAtYearStart $collectedAtYearStart): array
    {
        $category = $line->category;
        $currentPlanMinorUnits = $line->planned_amount_minor_units;
        $executionMinorUnits = $line->execution_minor_units;
        $initialPlanMinorUnits = $budget->isExecution()
            ? $line->initial_plan_minor_units
            : $line->planned_amount_minor_units;
        $remainingMinorUnits = CategoryExecutionMetrics::remainingMinorUnits(
            $currentPlanMinorUnits,
            $executionMinorUnits,
        );

        return [
            'id' => $category->id,
            'name' => $category->name,
            ...CategoryAppearance::fields($line),
            'type' => $category->type->value,
            'group_id' => $category->category_group_id,
            'sort_order' => $category->sort_order,
            'rhythm' => $rhythms->rhythmIn($category->id, $budget->year, $viewedMonth)?->value,
            'planned_amount_minor_units' => $line->planned_amount_minor_units,
            'initial_plan_minor_units' => $initialPlanMinorUnits,
            'current_plan_minor_units' => $currentPlanMinorUnits,
            'execution_minor_units' => $executionMinorUnits,
            'remaining_minor_units' => $remainingMinorUnits,
            'percent_complete' => CategoryExecutionMetrics::percentComplete(
                $currentPlanMinorUnits,
                $executionMinorUnits,
            ),
            'is_over_limit' => $category->type === CategoryType::Expense
                && CategoryExecutionMetrics::isOverLimit($currentPlanMinorUnits, $executionMinorUnits),
            'can_edit' => ($budget->isDraft() || $budget->isExecution()) && ! $line->isArchived(),
            'can_delete' => $budget->isDraft() && ! $line->isArchived(),
            'can_change_type' => ! $line->isArchived() && ! in_array($category->id, $typeLockedIds, true),
            'is_archived' => $line->isArchived(),
            'can_archive' => $budget->isExecution() && ! $line->isArchived() && ! $category->isArchived(),
            'can_restore' => $category->isArchived(),
            'can_align_current_plan' => $budget->isExecution()
                && ! $line->isArchived()
                && $remainingMinorUnits !== 0,
            'collected_at_year_start' => $collectedAtYearStart->forCategory($category->id),
        ];
    }
}

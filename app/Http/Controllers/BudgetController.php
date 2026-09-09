<?php

namespace App\Http\Controllers;

use App\Actions\StartBudgetExecution;
use App\Enums\BudgetPhase;
use App\Enums\CategoryType;
use App\Http\Requests\SelectBudgetYearRequest;
use App\Http\Requests\StoreBudgetRequest;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use App\Support\BudgetDeficitWarning;
use App\Support\BudgetPlanSummary;
use App\Support\CategoryAppearance;
use App\Support\CategoryExecutionMetrics;
use App\Support\SelectedBudgetYear;
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
                'planSummary' => null,
                'incomeTotals' => BudgetPlanSummary::emptyTypeTotals(),
                'expenseTotals' => BudgetPlanSummary::emptyTypeTotals(),
                'deficitWarning' => null,
                'show_archived' => false,
                'categoryAppearanceOptions' => CategoryAppearance::formOptions(),
            ]);
        }

        $categories = $budget->categories()
            ->ordered()
            ->get();

        $showArchived = $request->boolean('show_archived');

        if (! $showArchived) {
            $categories = $categories->filter(
                fn (Category $category): bool => ! $category->isArchived(),
            );
        }

        $planSummary = BudgetPlanSummary::forBudget($budget, $categories);

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
                ->where('type', CategoryType::Income)
                ->values()
                ->map(fn (Category $category): array => $this->categoryPayload($category, $budget))
                ->all(),
            'expenseCategories' => $categories
                ->where('type', CategoryType::Expense)
                ->values()
                ->map(fn (Category $category): array => $this->categoryPayload($category, $budget))
                ->all(),
            'planSummary' => $planSummary,
            'incomeTotals' => $planSummary['income'],
            'expenseTotals' => $planSummary['expense'],
            'deficitWarning' => $budget->isDraft()
                ? BudgetDeficitWarning::forBudget($budget, $user, $categories)
                : null,
            'show_archived' => $showArchived,
            'categoryAppearanceOptions' => CategoryAppearance::formOptions(),
        ]);
    }

    public function store(StoreBudgetRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $budget = $request->user()->budgets()->create([
            'year' => $validated['year'],
            'phase' => BudgetPhase::Draft,
        ]);

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

        return to_route('budget');
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
     * @return array{
     *     id: int,
     *     name: string,
     *     icon: string,
     *     color: string,
     *     type: string,
     *     planned_amount_minor_units: int,
     *     initial_plan_minor_units: int,
     *     current_plan_minor_units: int,
     *     execution_minor_units: int,
     *     remaining_minor_units: int,
     *     is_over_limit: bool,
     *     can_edit: bool,
     *     can_delete: bool,
     *     can_change_type: bool,
     *     is_archived: bool,
     *     can_archive: bool,
     *     can_restore: bool,
     * }
     */
    private function categoryPayload(Category $category, Budget $budget): array
    {
        $currentPlanMinorUnits = $category->planned_amount_minor_units;
        $executionMinorUnits = $category->execution_minor_units;
        $initialPlanMinorUnits = $budget->isExecution()
            ? $category->initial_plan_minor_units
            : $category->planned_amount_minor_units;

        return [
            'id' => $category->id,
            'name' => $category->name,
            ...CategoryAppearance::fields($category),
            'type' => $category->type->value,
            'planned_amount_minor_units' => $category->planned_amount_minor_units,
            'initial_plan_minor_units' => $initialPlanMinorUnits,
            'current_plan_minor_units' => $currentPlanMinorUnits,
            'execution_minor_units' => $executionMinorUnits,
            'remaining_minor_units' => CategoryExecutionMetrics::remainingMinorUnits(
                $currentPlanMinorUnits,
                $executionMinorUnits,
            ),
            'is_over_limit' => $category->type === CategoryType::Expense
                && CategoryExecutionMetrics::isOverLimit($currentPlanMinorUnits, $executionMinorUnits),
            'can_edit' => ($budget->isDraft() || $budget->isExecution()) && ! $category->isArchived(),
            'can_delete' => $budget->isDraft() && ! $category->isArchived(),
            'can_change_type' => $budget->isDraft() && ! $category->isArchived(),
            'is_archived' => $category->isArchived(),
            'can_archive' => $budget->isExecution() && ! $category->isArchived(),
            'can_restore' => $category->isArchived(),
        ];
    }
}

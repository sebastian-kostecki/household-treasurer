<?php

namespace App\Mcp\Tools;

use App\Enums\CategoryType;
use App\Mcp\Tools\Concerns\AnswersForUser;
use App\Models\CategoryGroup;
use App\Support\AppDay;
use App\Support\BudgetForYear;
use App\Support\MonthPlanGroupSums;
use App\Support\MonthPlanItemRow;
use App\Support\MonthSummary;
use App\Support\MonthTiming;
use App\Support\MonthToSpend;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * @phpstan-import-type MonthPlanRow from MonthPlanItemRow
 */
#[Description('The month plan against execution: per Category the plan, the actual amount, the deviation and whether it is over plan, the Category Group sums, and the month totals. Defaults to the current month; for a year without a Budget in execution it says the year and the Budget phase and gives no month plan.')]
#[IsReadOnly]
#[IsIdempotent]
class MonthPlanTool extends Tool
{
    use AnswersForUser;

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->userOf($request);
        $today = AppDay::today();

        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $year = (int) ($validated['year'] ?? $today->year);
        $month = (int) ($validated['month'] ?? $today->month);

        $budget = BudgetForYear::find($user, $year);

        if ($budget === null || ! $budget->isExecution()) {
            return $this->answer($today, [
                'year' => $year,
                'month' => $month,
                'budget_phase' => $budget?->phase->value,
                'state' => 'none',
                'message' => "The year {$year} has no Budget in execution, so it has no month plan.",
            ]);
        }

        $timing = MonthTiming::of($year, $month, $today);
        $toSpend = MonthToSpend::forMonth($budget, $month, $today);
        EloquentCollection::make($toSpend->categories->all())->load('category');

        /** @var list<MonthPlanRow> $rows */
        $rows = $toSpend->categories
            ->map(fn ($line): array => MonthPlanItemRow::forLine($line, $toSpend, $timing, false, $month, $today))
            ->values()
            ->all();

        $groups = $user->categoryGroups()
            ->orderBy('sort_order')
            ->orderBy('name_key')
            ->orderBy('id')
            ->get();

        $breakdown = $toSpend->toSpendBreakdown();

        return $this->answer($today, [
            'year' => $year,
            'month' => $month,
            'timing' => $timing,
            'budget_phase' => $budget->phase->value,
            'state' => $toSpend->reading->state,
            'totals' => [
                'to_spend_minor_units' => $breakdown['to_spend_minor_units'] ?? null,
                'income_minor_units' => $breakdown['income_minor_units'] ?? null,
                'expense_minor_units' => $breakdown['expense_minor_units'] ?? null,
                'set_aside_minor_units' => $toSpend->reading->setAsideMinorUnits,
            ],
            'summary' => $timing === 'past' ? MonthSummary::forMonth($toSpend, $budget, $month)?->toArray() : null,
            'income' => $this->section($rows, $groups->where('type', CategoryType::Income)->values()->all(), CategoryType::Income),
            'expense' => $this->section($rows, $groups->where('type', CategoryType::Expense)->values()->all(), CategoryType::Expense),
        ]);
    }

    /**
     * @param  list<MonthPlanRow>  $rows
     * @param  array<int, CategoryGroup>  $groups
     * @return array{categories: array<int, array<string, mixed>>, groups: array<int, array<string, mixed>>}
     */
    private function section(array $rows, array $groups, CategoryType $type): array
    {
        $ofType = array_values(array_filter($rows, fn (array $row): bool => $row['type'] === $type->value));

        return [
            'categories' => array_map(fn (array $row): array => [
                'category_id' => $row['category_id'],
                'name' => $row['name'],
                'group_id' => $row['group_id'],
                'rhythm' => $row['rhythm'],
                'is_archived' => $row['is_archived'],
                'is_idle_even_annual' => $row['is_idle_even_annual'],
                'plan_minor_units' => $row['plan_minor_units'],
                'actual_minor_units' => $row['actual_minor_units'],
                'deviation_minor_units' => $row['deviation_minor_units'],
                'is_over_plan' => $row['is_over_plan'],
            ], $ofType),
            'groups' => MonthPlanGroupSums::forRows(
                array_values(array_map(fn (CategoryGroup $group): array => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'sort_order' => $group->sort_order,
                ], $groups)),
                $ofType,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'year' => $schema->integer()->min(2000)->max(2100)->description('Budget year; defaults to the year of app_day.'),
            'month' => $schema->integer()->min(1)->max(12)->description('Month number 1-12; defaults to the month of app_day.'),
        ];
    }
}

<?php

namespace App\Mcp\Tools;

use App\Enums\CategoryType;
use App\Mcp\Tools\Concerns\AnswersForUser;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Support\AppDay;
use App\Support\BudgetForYear;
use App\Support\BudgetPlanSummary;
use App\Support\CategoryExecutionMetrics;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('The annual Budget: per Category the initial plan, the Current Plan, the execution, the remainder, the percent complete and whether an expense is over its limit, with Category Group and type totals. Works for a draft Budget too (plan only). Defaults to the year of app_day.')]
#[IsReadOnly]
#[IsIdempotent]
class YearPlanTool extends Tool
{
    use AnswersForUser;

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->userOf($request);
        $today = AppDay::today();

        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $year = (int) ($validated['year'] ?? $today->year);
        $budget = BudgetForYear::find($user, $year);

        if ($budget === null) {
            return $this->answer($today, [
                'year' => $year,
                'budget_phase' => null,
            ]);
        }

        $lines = $budget->budgetLines()->with('category.group')->ordered()->get()
            ->filter(fn (BudgetLine $line): bool => ! $line->isArchived())
            ->values();

        $summary = BudgetPlanSummary::forBudget($budget, $lines);

        return $this->answer($today, [
            'year' => $year,
            'budget_phase' => $budget->phase->value,
            'totals' => $summary,
            'income' => $this->section($budget, $lines, CategoryType::Income),
            'expense' => $this->section($budget, $lines, CategoryType::Expense),
        ]);
    }

    /**
     * @param  Collection<int, BudgetLine>  $lines
     * @return array{categories: array<int, array<string, mixed>>, groups: array<int, array<string, mixed>>}
     */
    private function section(Budget $budget, Collection $lines, CategoryType $type): array
    {
        return [
            'categories' => $lines
                ->filter(fn (BudgetLine $line): bool => $line->hasType($type))
                ->map(fn (BudgetLine $line): array => [
                    'category_id' => $line->category_id,
                    'name' => $line->category->name,
                    'group_id' => $line->category->category_group_id,
                    ...BudgetPlanSummary::forLines($budget, collect([$line])),
                    'is_over_limit' => $type === CategoryType::Expense
                        && CategoryExecutionMetrics::isOverLimit($line->planned_amount_minor_units, $line->execution_minor_units),
                ])
                ->values()
                ->all(),
            'groups' => BudgetPlanSummary::groupsOfType($budget, $lines, $type),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'year' => $schema->integer()->min(2000)->max(2100)->description('Budget year; defaults to the year of app_day.'),
        ];
    }
}

<?php

namespace App\Support\Budget;

use App\Enums\CategoryType;
use App\Enums\FinancialOperationType;
use App\Models\Budget;
use App\Models\CashExpectation;
use App\Models\Category;
use App\Models\FinancialOperation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class BudgetAgendaMonthEntries
{
    /**
     * @param  Collection<int, Category>  $categories
     * @return array{
     *     agenda_entries_by_category: array<int, list<array{
     *         kind: 'expected'|'booked',
     *         date: string,
     *         description: string,
     *         amount_minor_units: int,
     *         cash_expectation_id?: int,
     *         financial_operation_id?: int,
     *         budget_year: int,
     *     }>>,
     *     income_execution_in_month: array<int, int>,
     * }
     */
    public static function forBudget(
        User $user,
        Budget $budget,
        int $viewedMonth,
        Collection $categories,
    ): array {
        $agendaCategoryIds = $categories
            ->filter(fn (Category $category): bool => $category->isAgendaCategory())
            ->pluck('id')
            ->all();

        $incomeCategoryIds = $categories
            ->where('type', CategoryType::Income)
            ->pluck('id')
            ->all();

        $monthStart = CarbonImmutable::create($budget->year, $viewedMonth, 1, 0, 0, 0, 'UTC');
        $monthEnd = $monthStart->endOfMonth();

        $agendaEntriesByCategory = [];

        foreach ($agendaCategoryIds as $categoryId) {
            $agendaEntriesByCategory[$categoryId] = [];
        }

        if ($agendaCategoryIds !== []) {
            $expectations = CashExpectation::query()
                ->where('user_id', $user->id)
                ->whereIn('category_id', $agendaCategoryIds)
                ->whereBetween('expectation_date', [
                    $monthStart->toDateString(),
                    $monthEnd->toDateString(),
                ])
                ->orderBy('expectation_date')
                ->orderBy('id')
                ->get();

            foreach ($expectations as $expectation) {
                $categoryId = $expectation->category_id;

                $agendaEntriesByCategory[$categoryId][] = [
                    'kind' => 'expected',
                    'date' => $expectation->expectation_date->toDateString(),
                    'description' => $expectation->description,
                    'amount_minor_units' => $expectation->amount_minor_units,
                    'cash_expectation_id' => $expectation->id,
                    'budget_year' => $budget->year,
                ];
            }

            $operations = FinancialOperation::query()
                ->where('budget_id', $budget->id)
                ->whereIn('category_id', $agendaCategoryIds)
                ->whereBetween('operation_date', [
                    $monthStart->toDateString(),
                    $monthEnd->toDateString(),
                ])
                ->orderBy('operation_date')
                ->orderBy('id')
                ->get();

            foreach ($operations as $operation) {
                $categoryId = $operation->category_id;

                $agendaEntriesByCategory[$categoryId][] = [
                    'kind' => 'booked',
                    'date' => $operation->operation_date->toDateString(),
                    'description' => $operation->description,
                    'amount_minor_units' => $operation->amount_minor_units,
                    'financial_operation_id' => $operation->id,
                    'budget_year' => $budget->year,
                ];
            }

            foreach ($agendaEntriesByCategory as $categoryId => $entries) {
                usort($entries, function (array $left, array $right): int {
                    $dateCompare = strcmp($left['date'], $right['date']);

                    if ($dateCompare !== 0) {
                        return $dateCompare;
                    }

                    if ($left['kind'] === $right['kind']) {
                        return 0;
                    }

                    return $left['kind'] === 'expected' ? -1 : 1;
                });

                $agendaEntriesByCategory[$categoryId] = $entries;
            }
        }

        $incomeExecutionInMonth = [];

        if ($incomeCategoryIds !== []) {
            $incomeOperations = FinancialOperation::query()
                ->where('budget_id', $budget->id)
                ->where('type', FinancialOperationType::Income)
                ->whereIn('category_id', $incomeCategoryIds)
                ->whereBetween('operation_date', [
                    $monthStart->toDateString(),
                    $monthEnd->toDateString(),
                ])
                ->get(['category_id', 'amount_minor_units']);

            foreach ($incomeOperations as $operation) {
                $categoryId = $operation->category_id;
                $incomeExecutionInMonth[$categoryId] = ($incomeExecutionInMonth[$categoryId] ?? 0)
                    + $operation->amount_minor_units;
            }
        }

        return [
            'agenda_entries_by_category' => $agendaEntriesByCategory,
            'income_execution_in_month' => $incomeExecutionInMonth,
        ];
    }
}

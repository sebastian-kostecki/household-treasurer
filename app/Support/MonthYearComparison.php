<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Models\BudgetLine;

/**
 * Year after the Month (context/foundation/glossary.md): Execution of a Category before the current
 * month plus the amount its month item counts with by the To Spend rule, set
 * against the Category's Current Plan. Year Remainder is Current Plan minus that.
 *
 * Valid only for the current calendar month of a Budget in execution: operations
 * are never dated in the future, so Execution before the month is the Category's
 * stored Execution minus the actual amount in the month. For any other month that
 * identity breaks and the numbers would be wrong. There is no copy in
 * resources/js/lib: the page reloads after a save.
 *
 * @phpstan-type YearComparison array{year_minor_units: int, annual_plan_minor_units: int, remaining_minor_units: int, is_over_year: bool}
 */
class MonthYearComparison
{
    /**
     * @param  array{plan_minor_units: int, actual_minor_units: int, has_positive_amount: bool}  $rowAmounts  as MonthToSpend::rowAmounts
     * @return YearComparison
     */
    public static function forCategory(BudgetLine $category, array $rowAmounts): array
    {
        $before = $category->execution_minor_units - $rowAmounts['actual_minor_units'];
        $year = $before + ToSpendCalculator::itemMinorUnits(
            $category->category->type,
            $category->month_item_kind,
            $rowAmounts['plan_minor_units'],
            $rowAmounts['actual_minor_units'],
            $rowAmounts['has_positive_amount'],
        );
        $annual = $category->planned_amount_minor_units;

        return self::result($category->category->type, $year, $annual);
    }

    /**
     * @param  list<YearComparison>  $comparisons  results of forCategory
     * @return YearComparison
     */
    public static function totals(array $comparisons, CategoryType $type): array
    {
        $year = 0;
        $annual = 0;

        foreach ($comparisons as $comparison) {
            $year += $comparison['year_minor_units'];
            $annual += $comparison['annual_plan_minor_units'];
        }

        return self::result($type, $year, $annual);
    }

    /**
     * @return YearComparison
     */
    private static function result(CategoryType $type, int $year, int $annual): array
    {
        $remaining = $annual - $year;

        return [
            'year_minor_units' => $year,
            'annual_plan_minor_units' => $annual,
            'remaining_minor_units' => $remaining,
            'is_over_year' => $type === CategoryType::Expense && $remaining < 0,
        ];
    }
}

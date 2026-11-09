<?php

namespace App\Support;

use App\Enums\CategoryType;
use App\Enums\DashboardNoticeSeverity;
use App\Models\Account;
use App\Models\Budget;
use App\Models\TermDeposit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class DashboardNotices
{
    /**
     * @param  Collection<int, Account>  $accounts
     * @param  Collection<int, TermDeposit>  $termDeposits
     * @return list<array{
     *     kind: 'negative_account',
     *     severity: 'exceeded'|'warning',
     *     id: int,
     *     name: string,
     *     type: string,
     *     bank: string|null,
     *     mark_url: string|null,
     *     amount_minor_units: int,
     * }|array{
     *     kind: 'negative_term_deposit',
     *     severity: 'exceeded'|'warning',
     *     id: int,
     *     name: string,
     *     bank: string,
     *     mark_url: string|null,
     *     amount_minor_units: int,
     * }|array{
     *     kind: 'month_item_over_plan'|'one_off_over_limit',
     *     severity: 'exceeded'|'warning',
     *     id: int,
     *     name: string,
     *     icon: string,
     *     color: string,
     *     category_name: string,
     *     amount_minor_units: int,
     * }|array{
     *     kind: 'month_to_spend_negative',
     *     severity: 'exceeded',
     *     month: int,
     *     amount_minor_units: int,
     * }|array{
     *     kind: 'month_plan_not_approved',
     *     severity: 'warning',
     *     month: int,
     * }|array{
     *     kind: 'month_pace_ahead',
     *     severity: 'warning',
     *     month: int,
     *     percent: int,
     *     elapsed_day: int,
     *     days_in_month: int,
     * }>
     *
     * The order of appending is the contract of the list: every exceeded notice
     * comes before every warning one, and kinds follow the order below.
     */
    public static function forDashboard(
        ?Budget $budget,
        Collection $accounts,
        Collection $termDeposits,
        ?MonthToSpend $monthToSpend,
        CarbonImmutable $today,
    ): array {
        $notices = [];

        foreach ($accounts as $account) {
            if ($account->isArchived() || ! $account->isNegative()) {
                continue;
            }

            $notices[] = [
                'kind' => 'negative_account',
                'severity' => DashboardNoticeSeverity::Exceeded->value,
                ...AccountIdentity::payload($account),
                'amount_minor_units' => $account->current_balance_minor_units,
            ];
        }

        foreach ($termDeposits as $termDeposit) {
            if ($termDeposit->isArchived() || ! $termDeposit->isNegative()) {
                continue;
            }

            $notices[] = [
                'kind' => 'negative_term_deposit',
                'severity' => DashboardNoticeSeverity::Exceeded->value,
                'id' => $termDeposit->id,
                'name' => $termDeposit->name,
                'bank' => $termDeposit->bank->value,
                'mark_url' => AccountIdentity::markUrl($termDeposit->bank),
                'amount_minor_units' => $termDeposit->current_balance_minor_units,
            ];
        }

        $monthReading = $monthToSpend !== null
            && $budget !== null
            && $today->year === $budget->year
            && $monthToSpend->reading->state !== 'none'
            ? $monthToSpend
            : null;

        if ($monthReading !== null) {
            $toSpendMinorUnits = $monthReading->toSpendMinorUnits();

            if ($toSpendMinorUnits !== null && $toSpendMinorUnits < 0) {
                $notices[] = [
                    'kind' => 'month_to_spend_negative',
                    'severity' => DashboardNoticeSeverity::Exceeded->value,
                    'month' => $today->month,
                    'amount_minor_units' => $toSpendMinorUnits,
                ];
            }

            $overPlanAlerts = [];

            foreach ($monthReading->categories->values() as $budgetOrder => $category) {
                if ($category->isArchived()) {
                    continue;
                }

                $amounts = $monthReading->rowAmounts($category);

                if (! MonthItemDeviation::isOverPlan(
                    $category->category->type,
                    $amounts['plan_minor_units'],
                    $amounts['actual_minor_units'],
                )) {
                    continue;
                }

                $overPlanAlerts[] = [
                    'budget_order' => $budgetOrder,
                    'id' => $category->id,
                    'name' => $category->category->name,
                    ...CategoryAppearance::fields($category),
                    'category_name' => $category->category->name,
                    'amount_minor_units' => $amounts['plan_minor_units'] - $amounts['actual_minor_units'],
                ];
            }

            $overPlanAlerts = self::sortByMostOverThenBudgetOrder($overPlanAlerts);

            foreach ($overPlanAlerts as $alert) {
                $notices[] = [
                    'kind' => 'month_item_over_plan',
                    'severity' => DashboardNoticeSeverity::Exceeded->value,
                    'id' => $alert['id'],
                    'name' => $alert['name'],
                    'icon' => $alert['icon'],
                    'color' => $alert['color'],
                    'category_name' => $alert['category_name'],
                    'amount_minor_units' => $alert['amount_minor_units'],
                ];
            }
        }

        if ($budget !== null && $budget->isExecution() && $today->year === $budget->year) {
            $expenseCategories = $budget->budgetLines()
                ->ofType(CategoryType::Expense)
                ->ordered()
                ->get();

            $oneOffAlerts = [];

            foreach ($expenseCategories->values() as $budgetOrder => $category) {
                if ($category->isArchived() || $category->is_paced) {
                    continue;
                }

                $remainingMinorUnits = CategoryExecutionMetrics::remainingMinorUnits(
                    $category->planned_amount_minor_units,
                    $category->execution_minor_units,
                );

                if ($remainingMinorUnits >= 0) {
                    continue;
                }

                $oneOffAlerts[] = [
                    'budget_order' => $budgetOrder,
                    'id' => $category->id,
                    'name' => $category->category->name,
                    ...CategoryAppearance::fields($category),
                    'category_name' => $category->category->name,
                    'amount_minor_units' => $remainingMinorUnits,
                ];
            }

            $oneOffAlerts = self::sortByMostOverThenBudgetOrder($oneOffAlerts);

            foreach ($oneOffAlerts as $alert) {
                $notices[] = [
                    'kind' => 'one_off_over_limit',
                    'severity' => DashboardNoticeSeverity::Exceeded->value,
                    'id' => $alert['id'],
                    'name' => $alert['name'],
                    'icon' => $alert['icon'],
                    'color' => $alert['color'],
                    'category_name' => $alert['category_name'],
                    'amount_minor_units' => $alert['amount_minor_units'],
                ];
            }
        }

        if ($monthReading !== null) {
            if ($monthReading->reading->state === 'proposal' && $today->day >= 3) {
                $notices[] = [
                    'kind' => 'month_plan_not_approved',
                    'severity' => DashboardNoticeSeverity::Warning->value,
                    'month' => $today->month,
                ];
            }

            $pace = MonthPace::forMonth($monthReading, $budget->year, $today->month, $today);

            if ($pace !== null && $pace['is_ahead'] && $pace['percent'] !== null) {
                $notices[] = [
                    'kind' => 'month_pace_ahead',
                    'severity' => DashboardNoticeSeverity::Warning->value,
                    'month' => $today->month,
                    'percent' => $pace['percent'],
                    'elapsed_day' => $pace['elapsed_day'],
                    'days_in_month' => $pace['days_in_month'],
                ];
            }
        }

        return $notices;
    }

    /**
     * Most negative amount first; a tie keeps the budget order.
     *
     * @template T of array{budget_order: int, amount_minor_units: int}
     *
     * @param  list<T>  $alerts
     * @return list<T>
     */
    private static function sortByMostOverThenBudgetOrder(array $alerts): array
    {
        usort(
            $alerts,
            function (array $left, array $right): int {
                $byAmount = $left['amount_minor_units'] <=> $right['amount_minor_units'];

                if ($byAmount !== 0) {
                    return $byAmount;
                }

                return $left['budget_order'] <=> $right['budget_order'];
            },
        );

        return $alerts;
    }
}

<?php

namespace App\Support;

use App\Enums\CategoryType;
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
     *     id: int,
     *     name: string,
     *     type: string,
     *     bank: string|null,
     *     mark_url: string|null,
     *     amount_minor_units: int,
     * }|array{
     *     kind: 'negative_term_deposit',
     *     id: int,
     *     name: string,
     *     bank: string,
     *     mark_url: string|null,
     *     amount_minor_units: int,
     * }|array{
     *     kind: 'paced_month_remaining'|'one_off_over_limit',
     *     id: int,
     *     name: string,
     *     icon: string,
     *     color: string,
     *     category_name: string,
     *     amount_minor_units: int,
     * }>
     */
    public static function forDashboard(
        ?Budget $budget,
        Collection $accounts,
        Collection $termDeposits,
        CarbonImmutable $today,
    ): array {
        $notices = [];

        foreach ($accounts as $account) {
            if ($account->isArchived() || ! $account->isNegative()) {
                continue;
            }

            $notices[] = [
                'kind' => 'negative_account',
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
                'id' => $termDeposit->id,
                'name' => $termDeposit->name,
                'bank' => $termDeposit->bank->value,
                'mark_url' => AccountIdentity::markUrl($termDeposit->bank),
                'amount_minor_units' => $termDeposit->current_balance_minor_units,
            ];
        }

        if ($budget !== null && $budget->isExecution() && $today->year === $budget->year) {
            $expenseCategories = $budget->categories()
                ->where('type', CategoryType::Expense)
                ->ordered()
                ->get();

            foreach (DashboardPacedCategoryMonthAlerts::forBudget(
                $budget,
                $expenseCategories,
                $today,
            ) as $alert) {
                $notices[] = [
                    'kind' => 'paced_month_remaining',
                    'id' => $alert['id'],
                    'name' => $alert['name'],
                    'icon' => $alert['icon'],
                    'color' => $alert['color'],
                    'category_name' => $alert['name'],
                    'amount_minor_units' => $alert['month_remaining_minor_units'],
                ];
            }

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
                    'name' => $category->name,
                    ...CategoryAppearance::fields($category),
                    'category_name' => $category->name,
                    'amount_minor_units' => $remainingMinorUnits,
                ];
            }

            usort(
                $oneOffAlerts,
                function (array $left, array $right): int {
                    $byRemaining = $left['amount_minor_units'] <=> $right['amount_minor_units'];

                    if ($byRemaining !== 0) {
                        return $byRemaining;
                    }

                    return $left['budget_order'] <=> $right['budget_order'];
                },
            );

            foreach ($oneOffAlerts as $alert) {
                $notices[] = [
                    'kind' => 'one_off_over_limit',
                    'id' => $alert['id'],
                    'name' => $alert['name'],
                    'icon' => $alert['icon'],
                    'color' => $alert['color'],
                    'category_name' => $alert['category_name'],
                    'amount_minor_units' => $alert['amount_minor_units'],
                ];
            }
        }

        return $notices;
    }
}

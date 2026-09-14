<?php

namespace App\Support\Dashboard;

use App\Models\CashExpectation;
use App\Models\User;
use Carbon\CarbonImmutable;

class CashExpectationNotices
{
    /**
     * @return list<array{
     *     id: int,
     *     expectation_date: string,
     *     description: string,
     *     amount_minor_units: int,
     *     type: string,
     *     is_expense_refund: bool,
     *     is_overdue: bool,
     *     is_today: bool,
     *     budget_year: int,
     *     category_name: string,
     *     account_name: string,
     * }>
     */
    public static function forUser(User $user, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today('UTC');
        $todayString = $today->toDateString();

        $expectations = $user->cashExpectations()
            ->with(['category.budget', 'account'])
            ->whereDate('expectation_date', '<=', $todayString)
            ->orderBy('expectation_date')
            ->orderBy('id')
            ->get();

        return $expectations
            ->map(function (CashExpectation $expectation) use ($todayString): array {
                $date = $expectation->expectation_date->toDateString();
                $isToday = $date === $todayString;

                return [
                    'id' => $expectation->id,
                    'expectation_date' => $date,
                    'description' => $expectation->description,
                    'amount_minor_units' => $expectation->amount_minor_units,
                    'type' => $expectation->type->value,
                    'is_expense_refund' => $expectation->isExpenseRefund(),
                    'is_overdue' => $date < $todayString,
                    'is_today' => $isToday,
                    'budget_year' => $expectation->category->budget->year,
                    'category_name' => $expectation->category->name,
                    'account_name' => $expectation->account->name,
                ];
            })
            ->values()
            ->all();
    }
}

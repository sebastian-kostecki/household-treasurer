<?php

namespace App\Actions;

use App\Enums\FinancialOperationType;
use App\Models\CashExpectation;
use App\Models\Category;
use App\Models\User;
use App\Support\CashExpectations\MonthlyExpectationDates;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateCashExpectations
{
    /**
     * @param  array{
     *     type: FinancialOperationType,
     *     expectation_date: string,
     *     amount_minor_units: int,
     *     description: string,
     *     counterparty?: string|null,
     *     account_id: int,
     *     category_id: int,
     *     occurrence: 'once'|'monthly',
     * }  $payload
     * @return list<CashExpectation>
     */
    public function handle(User $user, array $payload): array
    {
        $dates = $payload['occurrence'] === 'monthly'
            ? $this->monthlyDates($user, $payload)
            : [$payload['expectation_date']];

        if ($dates === []) {
            throw ValidationException::withMessages([
                'expectation_date' => __('No expectation dates remain after applying the account opening date.'),
            ]);
        }

        return DB::transaction(function () use ($user, $payload, $dates): array {
            $created = [];

            foreach ($dates as $date) {
                $created[] = $user->cashExpectations()->create([
                    'type' => $payload['type'],
                    'expectation_date' => $date,
                    'amount_minor_units' => $payload['amount_minor_units'],
                    'description' => $payload['description'],
                    'counterparty' => $payload['counterparty'] ?? null,
                    'account_id' => $payload['account_id'],
                    'category_id' => $payload['category_id'],
                ]);
            }

            return $created;
        });
    }

    /**
     * @param  array{
     *     expectation_date: string,
     *     account_id: int,
     *     category_id: int,
     * }  $payload
     * @return list<string>
     */
    private function monthlyDates(User $user, array $payload): array
    {
        $account = $user->accounts()->findOrFail($payload['account_id']);
        $category = Category::query()->with('budget')->findOrFail($payload['category_id']);
        $budgetYear = $category->budget->year;

        return MonthlyExpectationDates::datesThroughDecember(
            $payload['expectation_date'],
            $budgetYear,
            $account->opening_date->toDateString(),
        );
    }
}

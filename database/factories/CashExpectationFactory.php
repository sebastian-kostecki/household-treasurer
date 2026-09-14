<?php

namespace Database\Factories;

use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\CashExpectation;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashExpectation>
 */
class CashExpectationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => FinancialOperationType::Expense,
            'account_id' => Account::factory(),
            'category_id' => Category::factory(),
            'expectation_date' => fake()->date(),
            'amount_minor_units' => fake()->numberBetween(100, 50000),
            'description' => fake()->sentence(3),
            'counterparty' => fake()->optional()->company(),
        ];
    }

    public function income(): static
    {
        return $this->state(fn (): array => [
            'type' => FinancialOperationType::Income,
            'amount_minor_units' => fake()->numberBetween(100, 50000),
        ]);
    }

    public function expense(): static
    {
        return $this->state(fn (): array => [
            'type' => FinancialOperationType::Expense,
            'amount_minor_units' => fake()->numberBetween(100, 50000),
        ]);
    }

    public function expenseRefund(): static
    {
        return $this->state(fn (): array => [
            'type' => FinancialOperationType::Expense,
            'amount_minor_units' => -fake()->numberBetween(100, 50000),
        ]);
    }
}

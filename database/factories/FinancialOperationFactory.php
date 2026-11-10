<?php

namespace Database\Factories;

use App\Enums\FinancialOperationType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\FinancialOperation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialOperation>
 */
class FinancialOperationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => FinancialOperationType::Income,
            'account_id' => Account::factory(),
            'budget_line_id' => BudgetLine::factory()->income(),
            'budget_id' => Budget::factory(),
            'operation_date' => fake()->date(),
            'amount_minor_units' => fake()->numberBetween(100, 500000),
            'description' => fake()->sentence(),
        ];
    }

    public function income(): static
    {
        return $this->state(fn (): array => [
            'type' => FinancialOperationType::Income,
        ]);
    }

    public function expense(): static
    {
        return $this->state(fn (): array => [
            'type' => FinancialOperationType::Expense,
            'budget_line_id' => BudgetLine::factory()->expense(),
        ]);
    }
}

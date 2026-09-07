<?php

namespace Database\Factories;

use App\Enums\BudgetPhase;
use App\Models\Budget;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Budget>
 */
class BudgetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'year' => (int) fake()->unique()->numberBetween(2020, 2030),
            'phase' => BudgetPhase::Draft,
        ];
    }

    public function execution(): static
    {
        return $this->state(fn (): array => [
            'phase' => BudgetPhase::Execution,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\MonthPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonthPlan>
 */
class MonthPlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'budget_id' => Budget::factory()->execution(),
            'month' => fake()->numberBetween(1, 12),
            'approved_at' => now(),
        ];
    }
}

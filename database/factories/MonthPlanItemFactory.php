<?php

namespace Database\Factories;

use App\Models\BudgetLine;
use App\Models\MonthPlan;
use App\Models\MonthPlanItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonthPlanItem>
 */
class MonthPlanItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->numberBetween(0, 500000);

        return [
            'month_plan_id' => MonthPlan::factory(),
            'budget_line_id' => BudgetLine::factory(),
            'approved_amount_minor_units' => $amount,
            'current_amount_minor_units' => $amount,
        ];
    }
}

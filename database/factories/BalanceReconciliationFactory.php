<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\BalanceReconciliation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BalanceReconciliation>
 */
class BalanceReconciliationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_id' => Account::factory(),
            'reconciled_on' => fake()->date(),
            'bank_balance_minor_units' => fake()->numberBetween(-50000, 500000),
        ];
    }
}

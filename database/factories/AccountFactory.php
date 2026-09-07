<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $openingBalanceGrosze = fake()->numberBetween(0, 500000);

        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'type' => fake()->randomElement(AccountType::cases()),
            'opening_date' => fake()->date(),
            'opening_balance_grosze' => $openingBalanceGrosze,
            'current_balance_grosze' => $openingBalanceGrosze,
        ];
    }

    public function bank(): static
    {
        return $this->state(fn (): array => [
            'type' => AccountType::Bank,
        ]);
    }

    public function cash(): static
    {
        return $this->state(fn (): array => [
            'type' => AccountType::Cash,
        ]);
    }

    public function negative(): static
    {
        return $this->state(function (): array {
            $openingBalanceGrosze = fake()->numberBetween(-50000, -100);

            return [
                'opening_balance_grosze' => $openingBalanceGrosze,
                'current_balance_grosze' => $openingBalanceGrosze,
            ];
        });
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'archived_at' => now(),
        ]);
    }
}

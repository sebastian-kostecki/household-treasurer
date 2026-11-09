<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Enums\Bank;
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
        $openingBalanceMinorUnits = fake()->numberBetween(0, 500000);
        $type = fake()->randomElement(AccountType::cases());

        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'type' => $type,
            'bank' => $type === AccountType::Bank ? Bank::Other : null,
            'opening_date' => fake()->dateTimeBetween('2000-01-01', '2020-12-31')->format('Y-m-d'),
            'opening_balance_minor_units' => $openingBalanceMinorUnits,
            'current_balance_minor_units' => $openingBalanceMinorUnits,
        ];
    }

    public function bank(): static
    {
        return $this->state(fn (): array => [
            'type' => AccountType::Bank,
            'bank' => Bank::Other,
        ]);
    }

    public function cash(): static
    {
        return $this->state(fn (): array => [
            'type' => AccountType::Cash,
            'bank' => null,
        ]);
    }

    public function negative(): static
    {
        return $this->state(function (): array {
            $openingBalanceMinorUnits = fake()->numberBetween(-50000, -100);

            return [
                'opening_balance_minor_units' => $openingBalanceMinorUnits,
                'current_balance_minor_units' => $openingBalanceMinorUnits,
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

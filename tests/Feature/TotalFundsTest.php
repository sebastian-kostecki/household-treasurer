<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\TermDeposit;
use App\Models\User;
use App\Support\TotalFunds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TotalFundsTest extends TestCase
{
    use RefreshDatabase;

    public function test_total_funds_sums_accounts_and_term_deposits_including_archived_and_negative(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create(['current_balance_minor_units' => 100000]);
        Account::factory()->for($user)->archived()->create(['current_balance_minor_units' => 50000]);
        TermDeposit::factory()->for($user)->create(['current_balance_minor_units' => 200000]);
        TermDeposit::factory()->for($user)->archived()->create(['current_balance_minor_units' => 30000]);
        TermDeposit::factory()->for($user)->negative()->create(['current_balance_minor_units' => -7000]);

        $this->assertSame(373000, TotalFunds::forUser($user));
    }

    public function test_total_funds_is_zero_without_accounts_and_term_deposits(): void
    {
        $this->assertSame(0, TotalFunds::forUser(User::factory()->create()));
    }

    public function test_total_funds_ignores_other_users_data(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Account::factory()->for($user)->create(['current_balance_minor_units' => 1000]);
        Account::factory()->for($other)->create(['current_balance_minor_units' => 90000]);
        TermDeposit::factory()->for($other)->create(['current_balance_minor_units' => 80000]);

        $this->assertSame(1000, TotalFunds::forUser($user));
    }
}

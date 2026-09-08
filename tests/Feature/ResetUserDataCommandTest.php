<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\FinancialOperation;
use App\Models\OwnTransfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResetUserDataCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_deletes_all_domain_data_for_user(): void
    {
        $user = User::factory()->create([
            'first_run_checklist_dismissed_at' => now(),
        ]);
        $otherUser = User::factory()->create();

        $account = Account::factory()->for($user)->create();
        $budget = Budget::factory()->for($user)->create();
        $category = Category::factory()->for($budget)->create();
        $operation = FinancialOperation::factory()->for($user)->create([
            'account_id' => $account->id,
            'budget_id' => $budget->id,
            'category_id' => $category->id,
        ]);
        $ownTransfer = OwnTransfer::factory()->for($user)->create([
            'source_account_id' => $account->id,
            'destination_account_id' => Account::factory()->for($user)->create()->id,
        ]);

        $otherAccount = Account::factory()->for($otherUser)->create();
        $otherBudget = Budget::factory()->for($otherUser)->create();

        DB::table('sessions')->insert([
            'id' => 'session-for-target-user',
            'user_id' => $user->id,
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);

        Artisan::call('user:reset-data', [
            'user' => $user->email,
            '--force' => true,
        ]);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('users', ['id' => $otherUser->id]);
        $this->assertDatabaseHas('accounts', ['id' => $otherAccount->id]);
        $this->assertDatabaseHas('budgets', ['id' => $otherBudget->id]);

        $this->assertDatabaseMissing('financial_operations', ['id' => $operation->id]);
        $this->assertDatabaseMissing('own_transfers', ['id' => $ownTransfer->id]);
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
        $this->assertDatabaseMissing('budgets', ['id' => $budget->id]);
        $this->assertDatabaseMissing('accounts', ['id' => $account->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'session-for-target-user']);

        $this->assertNull($user->refresh()->first_run_checklist_dismissed_at);
    }

    public function test_command_accepts_user_id(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create();

        Artisan::call('user:reset-data', [
            'user' => (string) $user->id,
            '--force' => true,
        ]);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseEmpty('accounts');
    }

    public function test_command_fails_when_user_is_not_found(): void
    {
        $this->artisan('user:reset-data', [
            'user' => 'missing@example.com',
            '--force' => true,
        ])->assertFailed();
    }
}

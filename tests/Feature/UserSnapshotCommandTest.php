<?php

namespace Tests\Feature;

use App\Actions\BackupUserData;
use App\Actions\RestoreUserData;
use App\Enums\Bank;
use App\Enums\BudgetPhase;
use App\Enums\CapitalMovementDirection;
use App\Models\Account;
use App\Models\Budget;
use App\Models\CapitalMovement;
use App\Models\Category;
use App\Models\FinancialOperation;
use App\Models\OwnTransfer;
use App\Models\TermDeposit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserSnapshotCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_command_writes_snapshot_file(): void
    {
        Storage::fake('local');

        $user = $this->createUserWithDomainData();

        Artisan::call('user:backup', [
            'user' => $user->email,
        ]);

        $files = Storage::disk('local')->files(BackupUserData::SNAPSHOT_DIRECTORY);

        $this->assertCount(1, $files);

        $snapshot = json_decode(Storage::disk('local')->get($files[0]), true);

        $this->assertSame(4, $snapshot['version']);
        $this->assertSame($user->id, $snapshot['source']['user_id']);
        $this->assertSame($user->email, $snapshot['source']['email']);
        $this->assertCount(2, $snapshot['accounts']);
        $this->assertCount(1, $snapshot['term_deposits']);
        $this->assertSame(Bank::Mbank->value, $snapshot['term_deposits'][0]['bank']);
        $this->assertSame('2027-01-01', $snapshot['term_deposits'][0]['end_date']);
        $this->assertCount(1, $snapshot['budgets']);
        $this->assertCount(1, $snapshot['categories']);
        $this->assertCount(1, $snapshot['financial_operations']);
        $this->assertCount(1, $snapshot['own_transfers']);
        $this->assertCount(1, $snapshot['capital_movements']);
    }

    public function test_restore_command_replaces_target_user_domain_data(): void
    {
        Storage::fake('local');

        $sourceUser = $this->createUserWithDomainData();
        $targetUser = User::factory()->create();

        $path = app(BackupUserData::class)->handle($sourceUser);

        Account::factory()->for($targetUser)->create();
        Budget::factory()->for($targetUser)->create();

        Artisan::call('user:restore', [
            'user' => $targetUser->email,
            'path' => $path,
            '--force' => true,
        ]);

        $targetUser->refresh();

        $this->assertSame(2, $targetUser->accounts()->count());
        $this->assertSame(1, $targetUser->termDeposits()->count());
        $this->assertSame(1, $targetUser->budgets()->count());
        $this->assertSame(1, $targetUser->financialOperations()->count());
        $this->assertSame(1, $targetUser->ownTransfers()->count());
        $this->assertSame(1, $targetUser->capitalMovements()->count());
        $this->assertNotNull($targetUser->first_run_checklist_dismissed_at);

        $restoredAccountIds = $targetUser->accounts()->pluck('id')->all();
        $this->assertNotEquals(
            $sourceUser->accounts()->pluck('id')->all(),
            $restoredAccountIds,
        );
    }

    public function test_restore_command_can_move_snapshot_between_users(): void
    {
        Storage::fake('local');

        $sourceUser = $this->createUserWithDomainData();
        $targetUser = User::factory()->create();

        $path = app(BackupUserData::class)->handle($sourceUser);

        Artisan::call('user:restore', [
            'user' => (string) $targetUser->id,
            'path' => $path,
            '--force' => true,
        ]);

        $this->assertSame(2, $targetUser->accounts()->count());
        $this->assertSame(1, $targetUser->budgets()->count());
        $this->assertDatabaseCount('accounts', 4);
        $this->assertDatabaseCount('budgets', 2);
    }

    public function test_restore_command_restores_v1_snapshot_without_term_deposits(): void
    {
        Storage::fake('local');

        $sourceUser = $this->createUserWithDomainData();
        $targetUser = User::factory()->create();
        $path = app(BackupUserData::class)->handle($sourceUser);

        $snapshot = json_decode(file_get_contents($path), true);
        $snapshot['version'] = 1;
        unset($snapshot['term_deposits'], $snapshot['capital_movements']);

        Storage::disk('local')->makeDirectory(BackupUserData::SNAPSHOT_DIRECTORY);
        $legacyPath = Storage::disk('local')->path(BackupUserData::SNAPSHOT_DIRECTORY.'/legacy-v1.json');
        file_put_contents($legacyPath, json_encode($snapshot, JSON_THROW_ON_ERROR));

        Artisan::call('user:restore', [
            'user' => $targetUser->email,
            'path' => $legacyPath,
            '--force' => true,
        ]);

        $this->assertSame(2, $targetUser->accounts()->count());
        $this->assertSame(0, $targetUser->termDeposits()->count());
    }

    public function test_restore_command_restores_v3_term_deposits_with_default_bank(): void
    {
        Storage::fake('local');

        $sourceUser = $this->createUserWithDomainData();
        $targetUser = User::factory()->create();
        $path = app(BackupUserData::class)->handle($sourceUser);

        $snapshot = json_decode(file_get_contents($path), true);
        $snapshot['version'] = 3;
        unset($snapshot['term_deposits'][0]['bank'], $snapshot['term_deposits'][0]['end_date']);

        Storage::disk('local')->makeDirectory(BackupUserData::SNAPSHOT_DIRECTORY);
        $legacyPath = Storage::disk('local')->path(BackupUserData::SNAPSHOT_DIRECTORY.'/legacy-v3.json');
        file_put_contents($legacyPath, json_encode($snapshot, JSON_THROW_ON_ERROR));

        Artisan::call('user:restore', [
            'user' => $targetUser->email,
            'path' => $legacyPath,
            '--force' => true,
        ]);

        $restoredTermDeposit = $targetUser->termDeposits()->firstOrFail();

        $this->assertSame(Bank::Other, $restoredTermDeposit->bank);
        $this->assertNull($restoredTermDeposit->end_date);
    }

    public function test_restore_command_fails_for_unsupported_snapshot_version(): void
    {
        Storage::fake('local');

        $targetUser = User::factory()->create();
        $path = storage_path('app/private/unsupported-snapshot.json');

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, json_encode(['version' => 99, 'source' => ['user_id' => 1, 'email' => 'a@example.com']]));

        $this->artisan('user:restore', [
            'user' => $targetUser->email,
            'path' => $path,
            '--force' => true,
        ])->assertFailed();
    }

    public function test_restore_command_fails_when_snapshot_file_is_missing(): void
    {
        $targetUser = User::factory()->create();

        $this->artisan('user:restore', [
            'user' => $targetUser->email,
            'path' => storage_path('app/private/missing-snapshot.json'),
            '--force' => true,
        ])->assertFailed();
    }

    public function test_backup_command_fails_when_user_is_not_found(): void
    {
        $this->artisan('user:backup', [
            'user' => 'missing@example.com',
        ])->assertFailed();
    }

    public function test_restore_action_treats_missing_import_identity_keys_as_null(): void
    {
        Storage::fake('local');

        $sourceUser = $this->createUserWithDomainData();
        $targetUser = User::factory()->create();
        $path = app(BackupUserData::class)->handle($sourceUser);

        $snapshot = json_decode(file_get_contents($path), true);
        unset(
            $snapshot['financial_operations'][0]['import_identity_digest'],
            $snapshot['financial_operations'][0]['import_identity_account_id'],
            $snapshot['own_transfers'][0]['source_import_identity_digest'],
        );

        Storage::disk('local')->makeDirectory(BackupUserData::SNAPSHOT_DIRECTORY);
        $legacyPath = Storage::disk('local')->path(BackupUserData::SNAPSHOT_DIRECTORY.'/legacy.json');
        file_put_contents($legacyPath, json_encode($snapshot, JSON_THROW_ON_ERROR));

        app(RestoreUserData::class)->handle($targetUser, $legacyPath);

        $restoredOperation = $targetUser->financialOperations()->firstOrFail();
        $restoredTransfer = $targetUser->ownTransfers()->firstOrFail();

        $this->assertNull($restoredOperation->import_identity_digest);
        $this->assertNull($restoredTransfer->source_import_identity_digest);
    }

    public function test_restore_action_preserves_domain_values_with_new_ids(): void
    {
        Storage::fake('local');

        $sourceUser = $this->createUserWithDomainData();
        $targetUser = User::factory()->create();

        $sourceAccount = $sourceUser->accounts()->orderBy('id')->first();
        $sourceBudget = $sourceUser->budgets()->first();
        $sourceCategory = Category::query()->where('budget_id', $sourceBudget->id)->first();
        $sourceOperation = $sourceUser->financialOperations()->first();
        $sourceTransfer = $sourceUser->ownTransfers()->first();

        $path = app(BackupUserData::class)->handle($sourceUser);

        app(RestoreUserData::class)->handle($targetUser, $path);

        $restoredAccount = $targetUser->accounts()->where('name', $sourceAccount->name)->firstOrFail();
        $restoredBudget = $targetUser->budgets()->where('year', $sourceBudget->year)->firstOrFail();
        $restoredCategory = Category::query()->where('budget_id', $restoredBudget->id)->where('name', $sourceCategory->name)->firstOrFail();
        $restoredOperation = $targetUser->financialOperations()->where('description', $sourceOperation->description)->firstOrFail();
        $restoredTransfer = $targetUser->ownTransfers()->where('description', $sourceTransfer->description)->firstOrFail();

        $this->assertNotSame($sourceAccount->id, $restoredAccount->id);
        $this->assertSame($sourceAccount->current_balance_minor_units, $restoredAccount->current_balance_minor_units);
        $this->assertSame($sourceBudget->phase, $restoredBudget->phase);
        $this->assertSame($sourceCategory->execution_minor_units, $restoredCategory->execution_minor_units);
        $this->assertSame($sourceOperation->amount_minor_units, $restoredOperation->amount_minor_units);
        $this->assertSame($sourceOperation->raw_statement_description, $restoredOperation->raw_statement_description);
        $this->assertSame($sourceOperation->import_identity_digest, $restoredOperation->import_identity_digest);
        $this->assertSame($sourceOperation->import_identity_amount_minor_units, $restoredOperation->import_identity_amount_minor_units);
        $this->assertSame($sourceTransfer->amount_minor_units, $restoredTransfer->amount_minor_units);
        $this->assertSame($sourceTransfer->source_import_identity_digest, $restoredTransfer->source_import_identity_digest);
        $this->assertSame($sourceTransfer->destination_import_identity_digest, $restoredTransfer->destination_import_identity_digest);
        $this->assertSame($sourceTransfer->source_raw_statement_description, $restoredTransfer->source_raw_statement_description);
        $this->assertSame($sourceTransfer->destination_raw_statement_description, $restoredTransfer->destination_raw_statement_description);
        $this->assertSame($restoredAccount->id, $restoredOperation->account_id);
        $this->assertSame($restoredCategory->id, $restoredOperation->category_id);
        $this->assertSame($restoredBudget->id, $restoredOperation->budget_id);
    }

    private function createUserWithDomainData(): User
    {
        $user = User::factory()->create([
            'first_run_checklist_dismissed_at' => now(),
        ]);

        $sourceAccount = Account::factory()->for($user)->create(['name' => 'Main bank']);
        $destinationAccount = Account::factory()->for($user)->cash()->create(['name' => 'Cash']);
        TermDeposit::factory()->for($user)->create([
            'name' => 'Lokata 12M',
            'bank' => Bank::Mbank,
            'end_date' => '2027-01-01',
            'opening_balance_minor_units' => 200000,
            'current_balance_minor_units' => 200000,
        ]);
        $budget = Budget::factory()->for($user)->create([
            'year' => 2026,
            'phase' => BudgetPhase::Execution,
        ]);
        $category = Category::factory()->for($budget)->income()->create([
            'name' => 'Salary',
            'execution_minor_units' => 150000,
        ]);

        FinancialOperation::factory()->for($user)->create([
            'account_id' => $sourceAccount->id,
            'budget_id' => $budget->id,
            'category_id' => $category->id,
            'description' => 'Paycheck',
            'raw_statement_description' => 'ACME PAYROLL',
            'import_identity_account_id' => $sourceAccount->id,
            'import_identity_date' => '2026-03-01',
            'import_identity_amount_minor_units' => 150000,
            'import_identity_digest' => 'digest-paycheck',
        ]);

        OwnTransfer::factory()->for($user)->create([
            'source_account_id' => $sourceAccount->id,
            'destination_account_id' => $destinationAccount->id,
            'description' => 'ATM withdrawal',
            'source_raw_statement_description' => 'ATM WDL',
            'destination_raw_statement_description' => 'Cash deposit row',
            'source_import_identity_account_id' => $sourceAccount->id,
            'source_import_identity_date' => '2026-03-02',
            'source_import_identity_amount_minor_units' => -50000,
            'source_import_identity_digest' => 'digest-atm',
            'destination_import_identity_account_id' => $destinationAccount->id,
            'destination_import_identity_date' => '2026-03-03',
            'destination_import_identity_amount_minor_units' => 50000,
            'destination_import_identity_digest' => 'digest-cash',
        ]);

        $termDeposit = $user->termDeposits()->firstOrFail();

        CapitalMovement::factory()->for($user)->create([
            'account_id' => $sourceAccount->id,
            'term_deposit_id' => $termDeposit->id,
            'movement_date' => '2026-03-04',
            'amount_minor_units' => 75000,
            'direction' => CapitalMovementDirection::ToTermDeposit,
            'description' => 'Parking on deposit',
        ]);

        return $user;
    }
}

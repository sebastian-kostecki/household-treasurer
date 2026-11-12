<?php

namespace Tests\Feature;

use App\Actions\BackupUserData;
use App\Actions\ChangeCategoryRhythm;
use App\Actions\RestoreUserData;
use App\Enums\Bank;
use App\Enums\BudgetPhase;
use App\Enums\CapitalMovementDirection;
use App\Enums\Rhythm;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\CapitalMovement;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\CategoryRhythm;
use App\Models\FinancialOperation;
use App\Models\MonthPlan;
use App\Models\MonthPlanItem;
use App\Models\OwnTransfer;
use App\Models\TermDeposit;
use App\Models\User;
use App\Support\CategoryList\LegacyRhythmConversion;
use App\Support\CsvImport\ImportDuplicateDetector;
use App\Support\CsvImport\ImportIdentityDigest;
use App\Support\UserSnapshot\UserSnapshotException;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoHouseholdSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
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

        $this->assertSame(11, $snapshot['version']);
        $this->assertSame([], $snapshot['category_groups']);
        $this->assertNull($snapshot['categories'][0]['category_group_id']);
        $this->assertSame([], $snapshot['month_plans']);
        $this->assertArrayNotHasKey('is_paced', $snapshot['budget_lines'][0]);
        $this->assertArrayNotHasKey('month_item_kind', $snapshot['budget_lines'][0]);
        $this->assertSame($snapshot['categories'][0]['id'], $snapshot['budget_lines'][0]['category_id']);
        $this->assertArrayHasKey('description', $snapshot['categories'][0]);
        $this->assertArrayNotHasKey('name', $snapshot['budget_lines'][0]);
        $this->assertArrayNotHasKey('planned_amount_minor_units', $snapshot['categories'][0]);
        $this->assertSame($user->id, $snapshot['source']['user_id']);
        $this->assertSame($user->email, $snapshot['source']['email']);
        $this->assertCount(2, $snapshot['accounts']);
        $this->assertCount(1, $snapshot['term_deposits']);
        $this->assertSame(Bank::Mbank->value, $snapshot['term_deposits'][0]['bank']);
        $this->assertSame('2027-01-01', $snapshot['term_deposits'][0]['end_date']);
        $this->assertCount(1, $snapshot['budgets']);
        $this->assertCount(1, $snapshot['categories']);
        $this->assertCount(1, $snapshot['budget_lines']);
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

    public function test_restore_replaces_the_category_list_of_a_user_who_already_has_one(): void
    {
        Storage::fake('local');

        $sourceUser = $this->createUserWithDomainData();
        $targetUser = User::factory()->create();
        $targetBudget = Budget::factory()->for($targetUser)->create();
        BudgetLine::factory()->for($targetBudget)->income()->create(['name' => 'Salary']);

        $path = app(BackupUserData::class)->handle($sourceUser);

        Artisan::call('user:restore', [
            'user' => $targetUser->email,
            'path' => $path,
            '--force' => true,
        ]);

        $this->assertSame(['Salary'], $targetUser->categories()->pluck('name')->all());
        $this->assertSame(1, $targetUser->budgets()->count());
        $this->assertSame(
            $targetUser->categories()->firstOrFail()->id,
            BudgetLine::query()->whereIn('budget_id', $targetUser->budgets()->select('id'))->firstOrFail()->category_id,
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

        $snapshot = $this->asLegacySnapshot(json_decode(file_get_contents($path), true), 1);
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

        $snapshot = $this->asLegacySnapshot(json_decode(file_get_contents($path), true), 3);
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

    public function test_snapshot_round_trips_month_plan_and_rhythm_with_remapped_ids(): void
    {
        Storage::fake('local');

        $sourceUser = User::factory()->create();
        $budget = Budget::factory()->for($sourceUser)->execution()->create(['year' => 2026]);
        $billCategory = BudgetLine::factory()->for($budget)->fixedCharge()->create(['name' => 'Rent']);
        $runningCategory = BudgetLine::factory()->for($budget)->expense()->create(['name' => 'Food']);
        $monthPlan = MonthPlan::factory()->for($budget)->create([
            'month' => 3,
            'approved_at' => '2026-03-02 10:00:00',
            'approved_set_aside_minor_units' => 50000,
            'current_set_aside_minor_units' => -30000,
        ]);
        MonthPlanItem::factory()->for($monthPlan)->for($billCategory)->create([
            'approved_amount_minor_units' => 150000,
            'current_amount_minor_units' => 160000,
        ]);
        MonthPlanItem::factory()->for($monthPlan)->for($runningCategory)->create([
            'approved_amount_minor_units' => 40000,
            'current_amount_minor_units' => 40000,
        ]);

        $path = app(BackupUserData::class)->handle($sourceUser);
        $snapshot = json_decode(file_get_contents($path), true);

        $this->assertSame(11, $snapshot['version']);
        $this->assertCount(1, $snapshot['month_plans']);
        $this->assertCount(2, $snapshot['month_plans'][0]['items']);

        $targetUser = User::factory()->create();
        app(RestoreUserData::class)->handle($targetUser, $path);

        $restoredBill = BudgetLine::query()
            ->whereHas('budget', fn ($query) => $query->where('user_id', $targetUser->id))
            ->whereHas('category', fn ($query) => $query->where('name', 'Rent'))
            ->firstOrFail();
        $restoredPlan = MonthPlan::query()
            ->whereHas('budget', fn ($query) => $query->where('user_id', $targetUser->id))
            ->firstOrFail();
        $restoredItem = $restoredPlan->items()->where('budget_line_id', $restoredBill->id)->firstOrFail();

        $this->assertSame(
            ['fixed_charge'],
            $restoredBill->category->rhythms()->pluck('rhythm')->map(fn ($rhythm) => $rhythm->value)->all(),
        );
        $this->assertSame(3, $restoredPlan->month);
        $this->assertSame('2026-03-02 10:00:00', $restoredPlan->approved_at->format('Y-m-d H:i:s'));
        $this->assertNotSame($monthPlan->id, $restoredPlan->id);
        $this->assertNotSame($budget->id, $restoredPlan->budget_id);
        $this->assertSame(50000, $restoredPlan->approved_set_aside_minor_units);
        $this->assertSame(-30000, $restoredPlan->current_set_aside_minor_units);
        $this->assertSame(150000, $restoredItem->approved_amount_minor_units);
        $this->assertSame(160000, $restoredItem->current_amount_minor_units);
        $this->assertSame(2, $restoredPlan->items()->count());
    }

    public function test_restore_command_restores_v4_snapshot_as_running_and_with_no_plans(): void
    {
        Storage::fake('local');

        $sourceUser = User::factory()->create();
        $budget = Budget::factory()->for($sourceUser)->execution()->create(['year' => 2026]);
        $category = BudgetLine::factory()->for($budget)->fixedCharge()->create(['name' => 'Rent']);
        MonthPlanItem::factory()
            ->for(MonthPlan::factory()->for($budget)->create(['month' => 3]))
            ->for($category)
            ->create();
        $targetUser = User::factory()->create();
        $path = app(BackupUserData::class)->handle($sourceUser);

        $snapshot = $this->asLegacySnapshot(json_decode(file_get_contents($path), true), 4);
        unset($snapshot['month_plans']);
        unset($snapshot['categories'][0]['month_item_kind']);

        $legacyPath = Storage::disk('local')->path(BackupUserData::SNAPSHOT_DIRECTORY.'/legacy-v4.json');
        Storage::disk('local')->makeDirectory(BackupUserData::SNAPSHOT_DIRECTORY);
        file_put_contents($legacyPath, json_encode($snapshot, JSON_THROW_ON_ERROR));

        Artisan::call('user:restore', [
            'user' => $targetUser->email,
            'path' => $legacyPath,
            '--force' => true,
        ]);

        // A version 4 line has no Month Item Kind, so the mapping reads it as Running (ADR-0072).
        $this->assertSame(['Rent' => [['running', 2026, 1]]], $this->rhythmsByName($targetUser));
        $this->assertSame(0, MonthPlan::query()
            ->whereHas('budget', fn ($query) => $query->where('user_id', $targetUser->id))
            ->count());
    }

    public function test_restore_command_restores_v5_month_plan_without_set_aside_keys_as_zero(): void
    {
        Storage::fake('local');

        $sourceUser = User::factory()->create();
        $budget = Budget::factory()->for($sourceUser)->execution()->create(['year' => 2026]);
        MonthPlan::factory()->for($budget)->create([
            'month' => 3,
            'approved_set_aside_minor_units' => 50000,
            'current_set_aside_minor_units' => -30000,
        ]);
        $targetUser = User::factory()->create();
        $path = app(BackupUserData::class)->handle($sourceUser);

        $snapshot = $this->asLegacySnapshot(json_decode(file_get_contents($path), true), 5);
        unset(
            $snapshot['month_plans'][0]['approved_set_aside_minor_units'],
            $snapshot['month_plans'][0]['current_set_aside_minor_units'],
        );

        $legacyPath = Storage::disk('local')->path(BackupUserData::SNAPSHOT_DIRECTORY.'/v5-without-set-aside.json');
        file_put_contents($legacyPath, json_encode($snapshot, JSON_THROW_ON_ERROR));

        Artisan::call('user:restore', [
            'user' => $targetUser->email,
            'path' => $legacyPath,
            '--force' => true,
        ]);

        $restoredPlan = MonthPlan::query()
            ->whereHas('budget', fn ($query) => $query->where('user_id', $targetUser->id))
            ->firstOrFail();

        $this->assertSame(0, $restoredPlan->approved_set_aside_minor_units);
        $this->assertSame(0, $restoredPlan->current_set_aside_minor_units);
    }

    public function test_restore_command_rejects_v5_snapshot_without_month_plans(): void
    {
        Storage::fake('local');

        $sourceUser = $this->createUserWithDomainData();
        $targetUser = User::factory()->create();
        $path = app(BackupUserData::class)->handle($sourceUser);

        $snapshot = $this->asLegacySnapshot(json_decode(file_get_contents($path), true), 5);
        unset($snapshot['month_plans']);

        $brokenPath = Storage::disk('local')->path(BackupUserData::SNAPSHOT_DIRECTORY.'/broken-v5.json');
        file_put_contents($brokenPath, json_encode($snapshot, JSON_THROW_ON_ERROR));

        $exitCode = Artisan::call('user:restore', [
            'user' => $targetUser->email,
            'path' => $brokenPath,
            '--force' => true,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('month_plans', Artisan::output());
        $this->assertSame(0, $targetUser->accounts()->count());
    }

    public function test_restore_command_rejects_malformed_month_plans(): void
    {
        Storage::fake('local');

        $sourceUser = User::factory()->create();
        $budget = Budget::factory()->for($sourceUser)->execution()->create(['year' => 2026]);
        $otherBudget = Budget::factory()->for($sourceUser)->execution()->create(['year' => 2025]);
        $category = BudgetLine::factory()->for($budget)->fixedCharge()->create(['name' => 'Rent']);
        $otherCategory = BudgetLine::factory()->for($otherBudget)->expense()->create(['name' => 'Food']);
        MonthPlanItem::factory()
            ->for(MonthPlan::factory()->for($budget)->create(['month' => 3]))
            ->for($category)
            ->create();
        $path = app(BackupUserData::class)->handle($sourceUser);
        $valid = json_decode(file_get_contents($path), true);

        $mutations = [
            'month out of range' => function (array $snapshot): array {
                $snapshot['month_plans'][0]['month'] = 13;

                return $snapshot;
            },
            'missing approved_at' => function (array $snapshot): array {
                unset($snapshot['month_plans'][0]['approved_at']);

                return $snapshot;
            },
            'duplicated plan' => function (array $snapshot): array {
                $snapshot['month_plans'][] = $snapshot['month_plans'][0];

                return $snapshot;
            },
            'duplicated category' => function (array $snapshot): array {
                $snapshot['month_plans'][0]['items'][] = $snapshot['month_plans'][0]['items'][0];

                return $snapshot;
            },
            'category of another budget' => function (array $snapshot) use ($otherCategory): array {
                $snapshot['month_plans'][0]['items'][0]['budget_line_id'] = $otherCategory->id;

                return $snapshot;
            },
        ];

        foreach ($mutations as $label => $mutate) {
            $brokenPath = Storage::disk('local')->path(BackupUserData::SNAPSHOT_DIRECTORY.'/broken-plan.json');
            file_put_contents($brokenPath, json_encode($mutate($valid), JSON_THROW_ON_ERROR));
            $targetUser = User::factory()->create();

            $exitCode = Artisan::call('user:restore', [
                'user' => $targetUser->email,
                'path' => $brokenPath,
                '--force' => true,
            ]);

            $this->assertSame(1, $exitCode, $label);
            $this->assertSame(0, MonthPlan::query()
                ->whereHas('budget', fn ($query) => $query->where('user_id', $targetUser->id))
                ->count(), $label);
        }
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
        $this->assertSame(
            $targetUser->accounts()->where('name', 'Main bank')->value('id'),
            $restoredTransfer->source_import_identity_account_id,
        );
    }

    public function test_restore_action_preserves_domain_values_with_new_ids(): void
    {
        Storage::fake('local');

        $sourceUser = $this->createUserWithDomainData();
        $targetUser = User::factory()->create();

        $sourceAccount = $sourceUser->accounts()->orderBy('id')->first();
        $sourceBudget = $sourceUser->budgets()->first();
        $sourceCategory = BudgetLine::query()->where('budget_id', $sourceBudget->id)->first();
        $sourceOperation = $sourceUser->financialOperations()->first();
        $sourceTransfer = $sourceUser->ownTransfers()->first();

        $path = app(BackupUserData::class)->handle($sourceUser);

        app(RestoreUserData::class)->handle($targetUser, $path);

        $restoredAccount = $targetUser->accounts()->where('name', $sourceAccount->name)->firstOrFail();
        $restoredBudget = $targetUser->budgets()->where('year', $sourceBudget->year)->firstOrFail();
        $restoredCategory = BudgetLine::query()->where('budget_id', $restoredBudget->id)->whereHas('category', fn ($query) => $query->where('name', $sourceCategory->category->name))->firstOrFail();
        $restoredOperation = $targetUser->financialOperations()->where('description', $sourceOperation->description)->firstOrFail();
        $restoredTransfer = $targetUser->ownTransfers()->where('description', $sourceTransfer->description)->firstOrFail();

        $this->assertNotSame($sourceAccount->id, $restoredAccount->id);
        $this->assertSame($sourceAccount->current_balance_minor_units, $restoredAccount->current_balance_minor_units);
        $this->assertSame($sourceBudget->phase, $restoredBudget->phase);
        $this->assertSame($sourceCategory->execution_minor_units, $restoredCategory->execution_minor_units);
        $this->assertSame($sourceOperation->amount_minor_units, $restoredOperation->amount_minor_units);
        $this->assertSame($sourceOperation->raw_statement_description, $restoredOperation->raw_statement_description);
        $this->assertSame($this->expectedDigest($targetUser, $restoredAccount->id, $restoredOperation->import_identity_date->toDateString(), $restoredOperation->import_identity_amount_minor_units, $restoredOperation->raw_statement_description), $restoredOperation->import_identity_digest);
        $this->assertNotSame($sourceOperation->import_identity_digest, $restoredOperation->import_identity_digest);
        $this->assertSame($restoredAccount->id, $restoredOperation->import_identity_account_id);
        $this->assertSame($sourceOperation->import_identity_amount_minor_units, $restoredOperation->import_identity_amount_minor_units);
        $this->assertSame($sourceTransfer->amount_minor_units, $restoredTransfer->amount_minor_units);
        $restoredDestinationAccount = $targetUser->accounts()->where('name', $sourceTransfer->destinationAccount->name)->firstOrFail();
        $this->assertSame($this->expectedDigest($targetUser, $restoredAccount->id, $restoredTransfer->source_import_identity_date->toDateString(), $restoredTransfer->source_import_identity_amount_minor_units, $restoredTransfer->source_raw_statement_description), $restoredTransfer->source_import_identity_digest);
        $this->assertSame($this->expectedDigest($targetUser, $restoredDestinationAccount->id, $restoredTransfer->destination_import_identity_date->toDateString(), $restoredTransfer->destination_import_identity_amount_minor_units, $restoredTransfer->destination_raw_statement_description), $restoredTransfer->destination_import_identity_digest);
        $this->assertSame($restoredAccount->id, $restoredTransfer->source_import_identity_account_id);
        $this->assertSame($restoredDestinationAccount->id, $restoredTransfer->destination_import_identity_account_id);
        $this->assertSame($sourceTransfer->source_raw_statement_description, $restoredTransfer->source_raw_statement_description);
        $this->assertSame($sourceTransfer->destination_raw_statement_description, $restoredTransfer->destination_raw_statement_description);
        $this->assertSame($restoredAccount->id, $restoredOperation->account_id);
        $this->assertSame($restoredCategory->id, $restoredOperation->budget_line_id);
        $this->assertSame($restoredBudget->id, $restoredOperation->budget_id);
    }

    public function test_restore_action_restores_import_identity_onto_user_with_existing_data(): void
    {
        Storage::fake('local');

        $user = $this->createUserWithImportIdentities();
        $path = app(BackupUserData::class)->handle($user);

        app(RestoreUserData::class)->handle($user, $path);

        $mainBank = $user->accounts()->where('name', 'Main bank')->firstOrFail();
        $cash = $user->accounts()->where('name', 'Cash')->firstOrFail();
        $operation = $user->financialOperations()->firstOrFail();
        $transfer = $user->ownTransfers()->firstOrFail();
        $movement = $user->capitalMovements()->firstOrFail();

        $this->assertSame($mainBank->id, $operation->import_identity_account_id);
        $this->assertSame($this->expectedDigest($user, $mainBank->id, '2026-03-01', 150000, 'ACME PAYROLL'), $operation->import_identity_digest);
        $this->assertSame($mainBank->id, $transfer->source_import_identity_account_id);
        $this->assertSame($this->expectedDigest($user, $mainBank->id, '2026-03-02', -50000, 'ATM WDL'), $transfer->source_import_identity_digest);
        $this->assertSame($cash->id, $transfer->destination_import_identity_account_id);
        $this->assertSame($this->expectedDigest($user, $cash->id, '2026-03-03', 50000, 'Cash deposit row'), $transfer->destination_import_identity_digest);
        $this->assertSame($mainBank->id, $movement->import_identity_account_id);
        $this->assertSame($this->expectedDigest($user, $mainBank->id, '2026-03-04', 75000, 'DEPOSIT ROW'), $movement->import_identity_digest);
    }

    public function test_import_preview_flags_restored_operation_as_duplicate(): void
    {
        Storage::fake('local');

        $user = $this->createUserWithImportIdentities();
        $path = app(BackupUserData::class)->handle($user);

        app(RestoreUserData::class)->handle($user, $path);

        $mainBank = $user->accounts()->where('name', 'Main bank')->firstOrFail();
        $cash = $user->accounts()->where('name', 'Cash')->firstOrFail();
        $detector = app(ImportDuplicateDetector::class);

        $operationRows = $detector->annotateRows($user, $mainBank->id, [
            ['operation_date' => '2026-03-01', 'amount_minor_units' => 150000, 'raw_statement_description' => 'ACME PAYROLL', 'valid' => true],
            ['operation_date' => '2026-03-01', 'amount_minor_units' => 150001, 'raw_statement_description' => 'ACME PAYROLL', 'valid' => true],
        ]);
        $legRows = $detector->annotateRows($user, $cash->id, [
            ['operation_date' => '2026-03-03', 'amount_minor_units' => 50000, 'raw_statement_description' => 'Cash deposit row', 'valid' => true],
        ]);

        $this->assertTrue($operationRows[0]['import_duplicate']);
        $this->assertFalse($operationRows[1]['import_duplicate']);
        $this->assertTrue($legRows[0]['import_duplicate']);
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function importIdentityRefusals(): array
    {
        $cases = [];

        foreach (['operation', 'source leg', 'destination leg', 'capital movement'] as $kind) {
            foreach (['raw statement description', 'identity date', 'identity amount', 'identity account'] as $gap) {
                $cases["{$kind}: missing {$gap} with digest"] = [$kind, $gap, true];
            }

            $cases["{$kind}: identity account outside snapshot without digest"] = [$kind, 'identity account', false];
        }

        return $cases;
    }

    #[DataProvider('importIdentityRefusals')]
    public function test_restore_action_refuses_incomplete_import_identity(string $kind, string $gap, bool $withDigest): void
    {
        Storage::fake('local');

        $user = $this->createUserWithImportIdentities();
        $path = app(BackupUserData::class)->handle($user);
        $snapshot = json_decode(file_get_contents($path), true);

        [$list, $prefix, $rawKey] = match ($kind) {
            'operation' => ['financial_operations', '', 'raw_statement_description'],
            'source leg' => ['own_transfers', 'source_', 'source_raw_statement_description'],
            'destination leg' => ['own_transfers', 'destination_', 'destination_raw_statement_description'],
            'capital movement' => ['capital_movements', '', 'raw_statement_description'],
        };

        if (! $withDigest) {
            unset($snapshot[$list][0][$prefix.'import_identity_digest']);
        }

        match ($gap) {
            'raw statement description' => $snapshot[$list][0][$rawKey] = null,
            'identity date' => $snapshot[$list][0][$prefix.'import_identity_date'] = null,
            'identity amount' => $snapshot[$list][0][$prefix.'import_identity_amount_minor_units'] = null,
            'identity account' => $snapshot[$list][0][$prefix.'import_identity_account_id'] = 999999,
        };

        $brokenPath = Storage::disk('local')->path(BackupUserData::SNAPSHOT_DIRECTORY.'/refused-identity.json');
        file_put_contents($brokenPath, json_encode($snapshot, JSON_THROW_ON_ERROR));

        $accountIds = $user->accounts()->orderBy('id')->pluck('id')->all();
        $operationId = $user->financialOperations()->value('id');

        $recordLabel = match ($kind) {
            'operation' => 'financial operation',
            'source leg' => 'own transfer',
            'destination leg' => 'own transfer',
            'capital movement' => 'capital movement',
        };
        $legSuffix = match ($kind) {
            'source leg' => ' source ',
            'destination leg' => ' destination ',
            default => ' ',
        };
        $reason = match ($gap) {
            'raw statement description' => 'but no raw statement description',
            'identity date' => 'but no import identity date',
            'identity amount' => 'but no import identity amount',
            'identity account' => 'references unknown import identity account id 999999',
        };

        try {
            app(RestoreUserData::class)->handle($user, $brokenPath);
            $this->fail('The restore should have refused the snapshot.');
        } catch (UserSnapshotException $exception) {
            $this->assertStringContainsString($recordLabel, $exception->getMessage());
            $this->assertStringContainsString($legSuffix, $exception->getMessage());
            $this->assertStringContainsString($reason, $exception->getMessage());
            $this->assertSame($accountIds, $user->accounts()->orderBy('id')->pluck('id')->all());
            $this->assertSame(1, $user->financialOperations()->count());
            $this->assertSame($operationId, $user->financialOperations()->value('id'));
        }
    }

    /**
     * @return array<string, array{string, mixed, string}>
     */
    public static function malformedImportIdentityInputs(): array
    {
        return [
            'date not Y-m-d' => ['import_identity_date', '12.03.2026', 'date that is not Y-m-d'],
            'date with time' => ['import_identity_date', '2026-03-12 00:00:00', 'date that is not Y-m-d'],
            'amount as string' => ['import_identity_amount_minor_units', '150000', 'amount that is not an integer'],
            'amount as float' => ['import_identity_amount_minor_units', 1500.5, 'amount that is not an integer'],
        ];
    }

    #[DataProvider('malformedImportIdentityInputs')]
    public function test_restore_action_refuses_malformed_import_identity_input(string $key, mixed $value, string $reason): void
    {
        Storage::fake('local');

        $user = $this->createUserWithImportIdentities();
        $path = app(BackupUserData::class)->handle($user);
        $snapshot = json_decode(file_get_contents($path), true);
        $snapshot['financial_operations'][0][$key] = $value;

        $brokenPath = Storage::disk('local')->path(BackupUserData::SNAPSHOT_DIRECTORY.'/malformed-identity.json');
        file_put_contents($brokenPath, json_encode($snapshot, JSON_THROW_ON_ERROR));

        $operationId = $user->financialOperations()->value('id');

        try {
            app(RestoreUserData::class)->handle($user, $brokenPath);
            $this->fail('The restore should have refused the snapshot.');
        } catch (UserSnapshotException $exception) {
            $this->assertStringContainsString('financial operation', $exception->getMessage());
            $this->assertStringContainsString($reason, $exception->getMessage());
            $this->assertSame($operationId, $user->financialOperations()->value('id'));
        }
    }

    private function expectedDigest(User $user, int $accountId, string $date, int $amountMinorUnits, ?string $rawStatementDescription): string
    {
        return app(ImportIdentityDigest::class)->compute($user->id, $accountId, $date, $amountMinorUnits, $rawStatementDescription)['digest'];
    }

    private function createUserWithImportIdentities(): User
    {
        $user = $this->createUserWithDomainData();

        $user->capitalMovements()->firstOrFail()->forceFill([
            'raw_statement_description' => 'DEPOSIT ROW',
            'import_identity_account_id' => $user->accounts()->where('name', 'Main bank')->value('id'),
            'import_identity_date' => '2026-03-04',
            'import_identity_amount_minor_units' => 75000,
            'import_identity_digest' => 'digest-deposit',
        ])->save();

        return $user;
    }

    public function test_restore_command_rejects_v6_snapshot_without_budget_lines(): void
    {
        Storage::fake('local');

        $sourceUser = $this->createUserWithDomainData();
        $targetUser = User::factory()->create();
        $path = app(BackupUserData::class)->handle($sourceUser);

        $snapshot = json_decode(file_get_contents($path), true);
        unset($snapshot['budget_lines']);

        $brokenPath = Storage::disk('local')->path(BackupUserData::SNAPSHOT_DIRECTORY.'/broken-v6.json');
        file_put_contents($brokenPath, json_encode($snapshot, JSON_THROW_ON_ERROR));

        $exitCode = Artisan::call('user:restore', [
            'user' => $targetUser->email,
            'path' => $brokenPath,
            '--force' => true,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('budget_lines', Artisan::output());
        $this->assertSame(0, $targetUser->accounts()->count());
    }

    public function test_restore_command_rejects_malformed_v6_budget_lines(): void
    {
        Storage::fake('local');

        $sourceUser = User::factory()->create();
        $budget = Budget::factory()->for($sourceUser)->execution()->create(['year' => 2026]);
        BudgetLine::factory()->for($budget)->expense()->create(['name' => 'Food']);
        $path = app(BackupUserData::class)->handle($sourceUser);
        $valid = json_decode(file_get_contents($path), true);

        $mutations = [
            'unknown category' => function (array $snapshot): array {
                $snapshot['budget_lines'][0]['category_id'] = 999999;

                return $snapshot;
            },
            'two lines of one category in one budget' => function (array $snapshot): array {
                $snapshot['budget_lines'][] = [...$snapshot['budget_lines'][0], 'id' => 999999];

                return $snapshot;
            },
            'unknown budget' => function (array $snapshot): array {
                $snapshot['budget_lines'][0]['budget_id'] = 999999;

                return $snapshot;
            },
        ];

        foreach ($mutations as $label => $mutate) {
            $brokenPath = Storage::disk('local')->path(BackupUserData::SNAPSHOT_DIRECTORY.'/broken-lines.json');
            file_put_contents($brokenPath, json_encode($mutate($valid), JSON_THROW_ON_ERROR));
            $targetUser = User::factory()->create();

            $exitCode = Artisan::call('user:restore', [
                'user' => $targetUser->email,
                'path' => $brokenPath,
                '--force' => true,
            ]);

            $this->assertSame(1, $exitCode, $label);
            $this->assertSame(0, $targetUser->categories()->count(), $label);
            $this->assertSame(0, $targetUser->budgets()->count(), $label);
        }
    }

    public function test_restore_command_rejects_an_operation_whose_budget_line_belongs_to_another_budget(): void
    {
        Storage::fake('local');

        $sourceUser = User::factory()->create();
        $account = Account::factory()->for($sourceUser)->create();
        $budget2026 = Budget::factory()->for($sourceUser)->execution()->create(['year' => 2026]);
        $budget2027 = Budget::factory()->for($sourceUser)->execution()->create(['year' => 2027]);
        $line2026 = BudgetLine::factory()->for($budget2026)->expense()->create(['name' => 'Food']);
        BudgetLine::factory()->for($budget2027)->expense()->create(['category_id' => $line2026->category_id]);
        FinancialOperation::factory()->for($sourceUser)->create([
            'account_id' => $account->id,
            'budget_id' => $budget2026->id,
            'budget_line_id' => $line2026->id,
        ]);

        $snapshot = json_decode(file_get_contents(app(BackupUserData::class)->handle($sourceUser)), true);
        $snapshot['financial_operations'][0]['budget_id'] = $budget2027->id;
        $brokenPath = Storage::disk('local')->path(BackupUserData::SNAPSHOT_DIRECTORY.'/broken-operation.json');
        file_put_contents($brokenPath, json_encode($snapshot, JSON_THROW_ON_ERROR));
        $targetUser = User::factory()->create();

        $exitCode = Artisan::call('user:restore', [
            'user' => $targetUser->email,
            'path' => $brokenPath,
            '--force' => true,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('another budget', Artisan::output());
        $this->assertSame(0, $targetUser->budgets()->count());
        $this->assertSame(0, $targetUser->financialOperations()->count());
    }

    public function test_snapshot_v6_round_trips_the_category_list_and_its_budget_lines(): void
    {
        Storage::fake('local');

        $sourceUser = User::factory()->create();
        $budget2026 = Budget::factory()->for($sourceUser)->execution()->create(['year' => 2026]);
        $budget2027 = Budget::factory()->for($sourceUser)->create(['year' => 2027]);
        $line2026 = BudgetLine::factory()->for($budget2026)->expense()->create([
            'name' => 'Rozrywka',
            'planned_amount_minor_units' => 300001,
            'initial_plan_minor_units' => 299999,
            'execution_minor_units' => 111111,
        ]);
        $line2027 = BudgetLine::factory()->for($budget2027)->expense()->create([
            'name' => 'Rozrywka',
            'category_id' => $line2026->category_id,
            'planned_amount_minor_units' => 310000,
            'initial_plan_minor_units' => 0,
            'execution_minor_units' => 0,
        ]);
        $archived = BudgetLine::factory()->for($budget2026)->expense()->archived()->create(['name' => 'Wakacje']);
        $archived->category->update(['archived_at' => '2026-09-09 10:00:00']);
        $line2026->category->update(['name' => 'Rozrywka i kultura', 'icon' => 'film']);
        $account = Account::factory()->for($sourceUser)->create();
        FinancialOperation::factory()->for($sourceUser)->create([
            'account_id' => $account->id,
            'budget_id' => $budget2026->id,
            'budget_line_id' => $line2026->id,
            'amount_minor_units' => 111111,
        ]);

        $path = app(BackupUserData::class)->handle($sourceUser);
        $snapshot = json_decode(file_get_contents($path), true);

        $this->assertCount(2, $snapshot['categories']);
        $this->assertCount(3, $snapshot['budget_lines']);

        $targetUser = User::factory()->create();
        app(RestoreUserData::class)->handle($targetUser, $path);

        $list = $targetUser->categories()->orderBy('name')->get()->keyBy('name');
        $this->assertSame(['Rozrywka i kultura', 'Wakacje'], $list->keys()->all());
        $this->assertSame('film', $list['Rozrywka i kultura']->icon);
        $this->assertSame('rozrywka i kultura', $list['Rozrywka i kultura']->name_key);
        $this->assertNull($list['Rozrywka i kultura']->archived_at);
        $this->assertSame('2026-09-09 10:00:00', $list['Wakacje']->archived_at->format('Y-m-d H:i:s'));

        $restoredLines = BudgetLine::query()
            ->whereHas('budget', fn ($query) => $query->where('user_id', $targetUser->id))
            ->with('budget')
            ->get();
        $restored2026 = $restoredLines->first(fn (BudgetLine $line): bool => $line->budget->year === 2026 && $line->category_id === $list['Rozrywka i kultura']->id);
        $restored2027 = $restoredLines->first(fn (BudgetLine $line): bool => $line->budget->year === 2027);

        $this->assertCount(3, $restoredLines);
        $this->assertSame($list['Rozrywka i kultura']->id, $restored2027->category_id);
        $this->assertSame(300001, $restored2026->planned_amount_minor_units);
        $this->assertSame(299999, $restored2026->initial_plan_minor_units);
        $this->assertSame(111111, $restored2026->execution_minor_units);
        $this->assertSame(310000, $restored2027->planned_amount_minor_units);
        $this->assertSame('Rozrywka i kultura', $restored2026->category->name);
        $this->assertSame($restored2026->id, $targetUser->financialOperations()->firstOrFail()->budget_line_id);
        $this->assertNotSame($line2027->id, $restored2027->id);
    }

    public function test_restore_command_restores_the_v5_fixture_through_the_legacy_conversion(): void
    {
        $targetUser = User::factory()->create();

        $exitCode = Artisan::call('user:restore', [
            'user' => $targetUser->email,
            'path' => base_path('tests/Fixtures/snapshots/v5-category-list.json'),
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());

        $list = $targetUser->categories()->get()->keyBy('name');

        $this->assertEqualsCanonicalizing(
            ['Pensja', 'Rozrywka', 'Rozrywka (2)', 'Wakacje', 'Zdrowie'],
            $list->keys()->all(),
        );
        $this->assertSame('zdrowie', $list['Zdrowie']->name_key);
        $this->assertSame('pill', $list['Zdrowie']->icon);
        $this->assertSame('income', $list['Pensja']->type->value);
        $this->assertNotNull($list['Wakacje']->archived_at);
        $this->assertNull($list['Rozrywka']->archived_at);
        $this->assertNull($list['Rozrywka (2)']->archived_at);
        $this->assertNull($list['Zdrowie']->archived_at);

        $lines = BudgetLine::query()
            ->whereHas('budget', fn ($query) => $query->where('user_id', $targetUser->id))
            ->with('budget')
            ->orderBy('id')
            ->get();

        $this->assertSame(
            ['2026:Rozrywka', '2026:Rozrywka (2)', '2027:Rozrywka', '2027:Rozrywka (2)', '2025:Wakacje', '2026:Zdrowie', '2027:Zdrowie', '2026:Pensja'],
            $lines->map(fn (BudgetLine $line): string => $line->budget->year.':'.$list->firstWhere('id', $line->category_id)->name)->all(),
        );
        $this->assertSame(111111, $lines[0]->execution_minor_units);
        $this->assertSame(8500000, $lines[7]->execution_minor_units);

        $operations = $targetUser->financialOperations()->orderBy('id')->get();

        $this->assertSame($lines[0]->id, $operations[0]->budget_line_id);
        $this->assertSame($lines[7]->id, $operations[1]->budget_line_id);
        $this->assertSame($lines[0]->id, MonthPlan::query()->firstOrFail()->items()->firstOrFail()->budget_line_id);
    }

    public function test_restore_command_restores_the_v6_fixture_with_a_null_description(): void
    {
        $targetUser = User::factory()->create();

        $exitCode = Artisan::call('user:restore', [
            'user' => $targetUser->email,
            'path' => base_path('tests/Fixtures/snapshots/v6-category-list.json'),
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());

        $list = $targetUser->categories()->get()->keyBy('name');

        $this->assertEqualsCanonicalizing(['Pensja', 'Rozrywka', 'Wakacje'], $list->keys()->all());
        $this->assertNull($list['Rozrywka']->description);
        $this->assertNotNull($list['Wakacje']->archived_at);
        $this->assertSame('income', $list['Pensja']->type->value);

        $lines = BudgetLine::query()
            ->whereHas('budget', fn ($query) => $query->where('user_id', $targetUser->id))
            ->orderBy('id')
            ->get();

        $this->assertCount(3, $lines);
        $this->assertSame(111111, $lines[0]->execution_minor_units);
        $this->assertSame($lines[0]->id, $targetUser->financialOperations()->orderBy('id')->firstOrFail()->budget_line_id);
        $this->assertSame($lines[0]->id, MonthPlan::query()->firstOrFail()->items()->firstOrFail()->budget_line_id);
    }

    public function test_a_category_description_survives_a_backup_and_restore(): void
    {
        $sourceUser = User::factory()->create();
        Category::factory()->for($sourceUser)->create(['name' => 'Zdrowie', 'description' => 'Leki i wizyty u lekarza']);
        Category::factory()->for($sourceUser)->create(['name' => 'Dom']);

        $path = app(BackupUserData::class)->handle($sourceUser);

        $targetUser = User::factory()->create();
        app(RestoreUserData::class)->handle($targetUser, $path);

        $list = $targetUser->categories()->get()->keyBy('name');

        $this->assertSame('Leki i wizyty u lekarza', $list['Zdrowie']->description);
        $this->assertNull($list['Dom']->description);
    }

    public function test_snapshot_v8_round_trips_category_groups_and_membership(): void
    {
        Storage::fake('local');

        $sourceUser = User::factory()->create();
        $budget = Budget::factory()->for($sourceUser)->execution()->create(['year' => 2026]);
        $group = CategoryGroup::factory()->for($sourceUser)->expense()->create(['name' => 'Opłaty stałe', 'sort_order' => 0]);
        $member = Category::factory()->for($sourceUser)->expense()->create([
            'name' => 'Czynsz',
            'category_group_id' => $group->id,
            'sort_order' => 1,
        ]);
        $loose = Category::factory()->for($sourceUser)->expense()->create(['name' => 'Wakacje', 'sort_order' => 2]);
        BudgetLine::factory()->for($budget)->create(['category_id' => $member->id]);
        BudgetLine::factory()->for($budget)->create(['category_id' => $loose->id]);

        $path = app(BackupUserData::class)->handle($sourceUser);
        $snapshot = json_decode(file_get_contents($path), true);

        $this->assertSame(11, $snapshot['version']);
        $this->assertCount(1, $snapshot['category_groups']);
        $this->assertSame(
            ['id', 'name', 'type', 'sort_order', 'created_at', 'updated_at'],
            array_keys($snapshot['category_groups'][0]),
        );

        $targetUser = User::factory()->create();
        app(RestoreUserData::class)->handle($targetUser, $path);

        $restoredGroup = $targetUser->categoryGroups()->firstOrFail();
        $categories = $targetUser->categories()->get()->keyBy('name');

        $this->assertSame('Opłaty stałe', $restoredGroup->name);
        $this->assertSame('opłaty stałe', $restoredGroup->name_key);
        $this->assertSame('expense', $restoredGroup->type->value);
        $this->assertSame(0, $restoredGroup->sort_order);
        $this->assertNotSame($group->id, $restoredGroup->id);
        $this->assertSame($restoredGroup->id, $categories['Czynsz']->category_group_id);
        $this->assertNull($categories['Wakacje']->category_group_id);
        $this->assertSame(1, $categories['Czynsz']->sort_order);
        $this->assertSame(2, $categories['Wakacje']->sort_order);
    }

    public function test_restore_command_restores_a_v6_snapshot_without_groups(): void
    {
        Storage::fake('local');

        $sourceUser = $this->createUserWithDomainData();
        $path = app(BackupUserData::class)->handle($sourceUser);
        $snapshot = $this->asV6Snapshot(json_decode(file_get_contents($path), true));
        $v6Path = Storage::disk('local')->path(BackupUserData::SNAPSHOT_DIRECTORY.'/v6.json');
        file_put_contents($v6Path, json_encode($snapshot, JSON_THROW_ON_ERROR));

        $this->assertSame(6, $snapshot['version']);
        $this->assertArrayNotHasKey('category_groups', $snapshot);
        $this->assertArrayNotHasKey('category_group_id', $snapshot['categories'][0]);

        $targetUser = User::factory()->create();
        $exitCode = Artisan::call('user:restore', [
            'user' => $targetUser->email,
            'path' => $v6Path,
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());
        $this->assertSame(0, $targetUser->categoryGroups()->count());
        $this->assertSame(['Salary'], $targetUser->categories()->pluck('name')->all());
        $this->assertNull($targetUser->categories()->firstOrFail()->category_group_id);
        $this->assertSame(1, $targetUser->financialOperations()->count());
    }

    public function test_restore_command_rejects_an_inconsistent_v8_snapshot(): void
    {
        Storage::fake('local');

        $sourceUser = User::factory()->create();
        $budget = Budget::factory()->for($sourceUser)->execution()->create(['year' => 2026]);
        $group = CategoryGroup::factory()->for($sourceUser)->expense()->create(['name' => 'Opłaty stałe']);
        $member = Category::factory()->for($sourceUser)->expense()->create([
            'name' => 'Czynsz',
            'category_group_id' => $group->id,
            'sort_order' => 1,
        ]);
        BudgetLine::factory()->for($budget)->create(['category_id' => $member->id]);
        $valid = json_decode(file_get_contents(app(BackupUserData::class)->handle($sourceUser)), true);

        $mutations = [
            'missing category_groups key' => function (array $snapshot): array {
                unset($snapshot['category_groups']);

                return $snapshot;
            },
            'duplicated group' => function (array $snapshot): array {
                $snapshot['category_groups'][] = [...$snapshot['category_groups'][0], 'id' => 999999, 'name' => ' OPŁATY stałe '];

                return $snapshot;
            },
            'unknown group' => function (array $snapshot): array {
                $snapshot['categories'][0]['category_group_id'] = 999999;

                return $snapshot;
            },
            'group of another type' => function (array $snapshot): array {
                $snapshot['category_groups'][0]['type'] = 'income';

                return $snapshot;
            },
        ];

        foreach ($mutations as $label => $mutate) {
            $brokenPath = Storage::disk('local')->path(BackupUserData::SNAPSHOT_DIRECTORY.'/broken-groups.json');
            file_put_contents($brokenPath, json_encode($mutate($valid), JSON_THROW_ON_ERROR));
            $targetUser = User::factory()->create();

            $exitCode = Artisan::call('user:restore', [
                'user' => $targetUser->email,
                'path' => $brokenPath,
                '--force' => true,
            ]);

            $this->assertSame(1, $exitCode, $label);
            $this->assertSame(0, $targetUser->categoryGroups()->count(), $label);
            $this->assertSame(0, $targetUser->categories()->count(), $label);
            $this->assertSame(0, $targetUser->budgets()->count(), $label);
        }
    }

    public function test_restore_command_restores_a_v7_snapshot_with_descriptions_and_without_groups(): void
    {
        Storage::fake('local');

        $sourceUser = $this->createUserWithDomainData();
        $sourceUser->categories()->firstOrFail()->update(['description' => 'Pensja z etatu']);
        $path = app(BackupUserData::class)->handle($sourceUser);
        $snapshot = $this->asV7Snapshot(json_decode(file_get_contents($path), true));
        $v7Path = Storage::disk('local')->path(BackupUserData::SNAPSHOT_DIRECTORY.'/v7.json');
        file_put_contents($v7Path, json_encode($snapshot, JSON_THROW_ON_ERROR));

        $this->assertSame(7, $snapshot['version']);
        $this->assertArrayNotHasKey('category_groups', $snapshot);

        $targetUser = User::factory()->create();
        $exitCode = Artisan::call('user:restore', [
            'user' => $targetUser->email,
            'path' => $v7Path,
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());
        $this->assertSame(0, $targetUser->categoryGroups()->count());
        $this->assertSame('Pensja z etatu', $targetUser->categories()->firstOrFail()->description);
        $this->assertNull($targetUser->categories()->firstOrFail()->category_group_id);
        $this->assertSame(1, $targetUser->financialOperations()->count());
    }

    public function test_snapshot_v9_round_trips_the_rhythm_history_after_a_change(): void
    {
        Storage::fake('local');
        $this->travelTo('2026-03-10');

        $sourceUser = User::factory()->create();
        $changed = Category::factory()->for($sourceUser)->expense()->create(['name' => 'Czynsz']);
        Category::factory()->for($sourceUser)->expense()->create(['name' => 'Żywność']);
        Category::factory()->for($sourceUser)->income()->create(['name' => 'Pensja']);
        app(ChangeCategoryRhythm::class)->handle($changed, Rhythm::FixedCharge, CarbonImmutable::parse('2026-11-10'));

        $path = app(BackupUserData::class)->handle($sourceUser);
        $snapshot = json_decode(file_get_contents($path), true);
        $byName = collect($snapshot['categories'])->keyBy('name');

        $this->assertSame(
            [
                ['rhythm' => 'running', 'start_year' => 2026, 'start_month' => 3, 'opening_collected_minor_units' => null],
                ['rhythm' => 'fixed_charge', 'start_year' => 2026, 'start_month' => 11, 'opening_collected_minor_units' => null],
            ],
            $byName['Czynsz']['rhythms'],
        );
        $this->assertCount(1, $byName['Żywność']['rhythms']);
        $this->assertSame([], $byName['Pensja']['rhythms']);

        $targetUser = User::factory()->create();
        app(RestoreUserData::class)->handle($targetUser, $path);

        $restored = $targetUser->categories()->with('rhythms')->get()->keyBy('name');

        $this->assertSame(
            [[Rhythm::Running, 2026, 3], [Rhythm::FixedCharge, 2026, 11]],
            $restored['Czynsz']->rhythms->map(fn (CategoryRhythm $entry): array => [$entry->rhythm, $entry->start_year, $entry->start_month])->all(),
        );
        $this->assertCount(1, $restored['Żywność']->rhythms);
        $this->assertCount(0, $restored['Pensja']->rhythms);
        $this->assertNotSame($changed->id, $restored['Czynsz']->id);
    }

    public function test_snapshot_v9_round_trips_the_demo_household(): void
    {
        Storage::fake('local');

        (new DemoHouseholdSeeder)->run('demo@example.test');
        $sourceUser = User::query()->where('email', 'demo@example.test')->firstOrFail();

        $expected = $this->rhythmsByName($sourceUser);
        $this->assertNotEmpty($expected);

        $path = app(BackupUserData::class)->handle($sourceUser);
        $targetUser = User::factory()->create();
        app(RestoreUserData::class)->handle($targetUser, $path);

        $this->assertSame($expected, $this->rhythmsByName($targetUser));
        $this->assertSame(0, $targetUser->categories()->where('type', 'income')->whereHas('rhythms')->count());
        $this->assertSame(0, $targetUser->categories()->where('type', 'expense')->whereDoesntHave('rhythms')->count());
    }

    public function test_restore_command_restores_the_v5_fixture_with_the_rhythm_made_from_the_flags(): void
    {
        $this->travelTo('2026-11-10');
        $targetUser = User::factory()->create();

        $exitCode = Artisan::call('user:restore', [
            'user' => $targetUser->email,
            'path' => base_path('tests/Fixtures/snapshots/v5-category-list.json'),
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());

        $rhythms = $this->rhythmsByName($targetUser);

        $this->assertSame(
            [
                'Pensja' => [],
                'Rozrywka' => [['running', 2026, 1]],
                'Rozrywka (2)' => [['running', 2026, 1]],
                'Wakacje' => [['running', 2025, 1]],
                'Zdrowie' => [['running', 2026, 1]],
            ],
            $rhythms,
        );
    }

    public function test_restore_command_restores_the_v6_fixture_with_the_rhythm_made_from_the_flags(): void
    {
        $this->travelTo('2026-11-10');
        $targetUser = User::factory()->create();

        $exitCode = Artisan::call('user:restore', [
            'user' => $targetUser->email,
            'path' => base_path('tests/Fixtures/snapshots/v6-category-list.json'),
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());
        $this->assertSame(
            ['Pensja' => [], 'Rozrywka' => [['running', 2026, 1]], 'Wakacje' => [['running', 2025, 1]]],
            $this->rhythmsByName($targetUser),
        );
    }

    public function test_restore_command_restores_the_v8_fixture_with_the_same_rhythm_as_the_migration(): void
    {
        $this->travelTo('2026-11-10');
        $targetUser = User::factory()->create();

        $exitCode = Artisan::call('user:restore', [
            'user' => $targetUser->email,
            'path' => base_path('tests/Fixtures/snapshots/v8-category-list.json'),
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());
        $this->assertSame(
            [
                'Bez pozycji' => [['running', 2026, 11]],
                'Czynsz' => [['running', 2026, 1], ['fixed_charge', 2027, 1]],
                'Pensja' => [],
                'Prąd' => [['fixed_charge', 2026, 1]],
                'Rekolekcje' => [['even_annual', 2026, 1]],
            ],
            $this->rhythmsByName($targetUser),
        );

        // The parity with the backfill migration: the same frozen class on the same flags.
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/snapshots/v8-category-list.json')), true, flags: JSON_THROW_ON_ERROR);
        $categories = collect($fixture['categories'])->keyBy('id');
        $years = collect($fixture['budgets'])->pluck('year', 'id');
        $converted = LegacyRhythmConversion::convert(
            array_map(fn (array $line): array => [
                'category_id' => $line['category_id'],
                'category_type' => $categories[$line['category_id']]['type'],
                'year' => $years[$line['budget_id']],
                'is_paced' => $line['is_paced'],
                'month_item_kind' => $line['month_item_kind'],
            ], $fixture['budget_lines']),
            $categories->where('type', 'expense')->keys()->all(),
            2026,
            11,
        );
        $expected = [];

        foreach ($converted as $entry) {
            $expected[$categories[$entry['category_id']]['name']][] = [$entry['rhythm'], $entry['start_year'], $entry['start_month']];
        }

        $actual = array_filter($this->rhythmsByName($targetUser), fn (array $entries): bool => $entries !== []);
        ksort($expected);

        $this->assertSame($expected, $actual);
    }

    public function test_restore_command_restores_a_v8_snapshot_written_by_the_current_code_with_the_rhythm_from_the_flags(): void
    {
        Storage::fake('local');
        $this->travelTo('2026-11-10');

        $sourceUser = User::factory()->create();
        $budget = Budget::factory()->for($sourceUser)->execution()->create(['year' => 2026]);
        $category = Category::factory()->for($sourceUser)->expense()->create(['name' => 'Czynsz']);
        BudgetLine::factory()->for($budget)->create(['category_id' => $category->id]);
        $path = app(BackupUserData::class)->handle($sourceUser);
        $snapshot = $this->asV8Snapshot(json_decode(file_get_contents($path), true), ['is_paced' => true, 'month_item_kind' => 'bill']);
        $v8Path = Storage::disk('local')->path(BackupUserData::SNAPSHOT_DIRECTORY.'/v8.json');
        file_put_contents($v8Path, json_encode($snapshot, JSON_THROW_ON_ERROR));

        $this->assertSame(8, $snapshot['version']);
        $this->assertArrayNotHasKey('rhythms', $snapshot['categories'][0]);

        $targetUser = User::factory()->create();
        $exitCode = Artisan::call('user:restore', ['user' => $targetUser->email, 'path' => $v8Path, '--force' => true]);

        $this->assertSame(0, $exitCode, Artisan::output());
        $this->assertSame(['Czynsz' => [['fixed_charge', 2026, 1]]], $this->rhythmsByName($targetUser));
    }

    public function test_restore_command_rejects_an_inconsistent_v9_snapshot(): void
    {
        Storage::fake('local');

        $sourceUser = User::factory()->create();
        Category::factory()->for($sourceUser)->expense()->create(['name' => 'Czynsz', 'sort_order' => 1]);
        Category::factory()->for($sourceUser)->income()->create(['name' => 'Pensja', 'sort_order' => 0]);
        $valid = json_decode(file_get_contents(app(BackupUserData::class)->handle($sourceUser)), true);
        $expenseIndex = collect($valid['categories'])->search(fn (array $category): bool => $category['type'] === 'expense');
        $incomeIndex = collect($valid['categories'])->search(fn (array $category): bool => $category['type'] === 'income');

        $mutations = [
            'expense without an entry' => function (array $snapshot) use ($expenseIndex): array {
                $snapshot['categories'][$expenseIndex]['rhythms'] = [];

                return $snapshot;
            },
            'income with an entry' => function (array $snapshot) use ($incomeIndex): array {
                $snapshot['categories'][$incomeIndex]['rhythms'] = [['rhythm' => 'running', 'start_year' => 2026, 'start_month' => 1]];

                return $snapshot;
            },
            'unknown rhythm' => function (array $snapshot) use ($expenseIndex): array {
                $snapshot['categories'][$expenseIndex]['rhythms'][0]['rhythm'] = 'weekly';

                return $snapshot;
            },
            'month out of range' => function (array $snapshot) use ($expenseIndex): array {
                $snapshot['categories'][$expenseIndex]['rhythms'][0]['start_month'] = 13;

                return $snapshot;
            },
            'duplicated start' => function (array $snapshot) use ($expenseIndex): array {
                $snapshot['categories'][$expenseIndex]['rhythms'][] = $snapshot['categories'][$expenseIndex]['rhythms'][0];

                return $snapshot;
            },
            'missing rhythms key' => function (array $snapshot) use ($expenseIndex): array {
                unset($snapshot['categories'][$expenseIndex]['rhythms']);

                return $snapshot;
            },
            'entry that is not an object' => function (array $snapshot) use ($expenseIndex): array {
                $snapshot['categories'][$expenseIndex]['rhythms'] = ['running'];

                return $snapshot;
            },
            'year out of range' => function (array $snapshot) use ($expenseIndex): array {
                $snapshot['categories'][$expenseIndex]['rhythms'][0]['start_year'] = 70_000;

                return $snapshot;
            },
        ];

        foreach ($mutations as $label => $mutate) {
            $brokenPath = Storage::disk('local')->path(BackupUserData::SNAPSHOT_DIRECTORY.'/broken-rhythms.json');
            file_put_contents($brokenPath, json_encode($mutate($valid), JSON_THROW_ON_ERROR));
            $targetUser = User::factory()->create();

            $exitCode = Artisan::call('user:restore', ['user' => $targetUser->email, 'path' => $brokenPath, '--force' => true]);

            $this->assertSame(1, $exitCode, $label);
            $this->assertSame(0, $targetUser->categories()->count(), $label);
            $this->assertSame(0, CategoryRhythm::query()->whereIn('category_id', $targetUser->categories()->select('id'))->count(), $label);
        }
    }

    public function test_snapshot_v10_round_trips_an_irregular_category_with_its_opening_collected_and_month(): void
    {
        Storage::fake('local');
        $this->travelTo('2026-03-10');

        $sourceUser = User::factory()->create();
        $irregular = Category::factory()->for($sourceUser)->expense()->create(['name' => 'Serwis auta']);
        Category::factory()->for($sourceUser)->expense()->create(['name' => 'Żywność']);
        app(ChangeCategoryRhythm::class)->handle($irregular, Rhythm::Irregular, CarbonImmutable::parse('2026-07-02'), 50000);

        $path = app(BackupUserData::class)->handle($sourceUser);
        $snapshot = json_decode(file_get_contents($path), true);
        $byName = collect($snapshot['categories'])->keyBy('name');

        $this->assertSame(11, $snapshot['version']);
        $this->assertSame(
            [
                ['rhythm' => 'running', 'start_year' => 2026, 'start_month' => 3, 'opening_collected_minor_units' => null],
                ['rhythm' => 'irregular', 'start_year' => 2026, 'start_month' => 7, 'opening_collected_minor_units' => 50000],
            ],
            $byName['Serwis auta']['rhythms'],
        );
        $this->assertNull($byName['Żywność']['rhythms'][0]['opening_collected_minor_units']);

        $targetUser = User::factory()->create();
        app(RestoreUserData::class)->handle($targetUser, $path);

        $restored = $targetUser->categories()->with('rhythms')->get()->keyBy('name');

        $this->assertSame(
            [[Rhythm::Running, 2026, 3, null], [Rhythm::Irregular, 2026, 7, 50000]],
            $restored['Serwis auta']->rhythms
                ->map(fn (CategoryRhythm $entry): array => [$entry->rhythm, $entry->start_year, $entry->start_month, $entry->opening_collected_minor_units])
                ->all(),
        );
        $this->assertNull($restored['Żywność']->rhythms->sole()->opening_collected_minor_units);
    }

    public function test_restore_command_rejects_an_inconsistent_v10_snapshot(): void
    {
        Storage::fake('local');
        $this->travelTo('2026-03-10');

        $sourceUser = User::factory()->create();
        $irregular = Category::factory()->for($sourceUser)->expense()->create(['name' => 'Serwis auta', 'sort_order' => 1]);
        Category::factory()->for($sourceUser)->expense()->create(['name' => 'Czynsz', 'sort_order' => 2]);
        app(ChangeCategoryRhythm::class)->handle($irregular, Rhythm::Irregular, CarbonImmutable::parse('2026-07-02'), 50000);
        $valid = json_decode(file_get_contents(app(BackupUserData::class)->handle($sourceUser)), true);
        $irregularIndex = collect($valid['categories'])->search(fn (array $category): bool => $category['name'] === 'Serwis auta');
        $otherIndex = collect($valid['categories'])->search(fn (array $category): bool => $category['name'] === 'Czynsz');

        $mutations = [
            'irregular without an amount' => function (array $snapshot) use ($irregularIndex): array {
                $snapshot['categories'][$irregularIndex]['rhythms'][1]['opening_collected_minor_units'] = null;

                return $snapshot;
            },
            'negative amount' => function (array $snapshot) use ($irregularIndex): array {
                $snapshot['categories'][$irregularIndex]['rhythms'][1]['opening_collected_minor_units'] = -1;

                return $snapshot;
            },
            'non-numeric amount' => function (array $snapshot) use ($irregularIndex): array {
                $snapshot['categories'][$irregularIndex]['rhythms'][1]['opening_collected_minor_units'] = '500';

                return $snapshot;
            },
            'amount on another rhythm' => function (array $snapshot) use ($otherIndex): array {
                $snapshot['categories'][$otherIndex]['rhythms'][0]['opening_collected_minor_units'] = 100;

                return $snapshot;
            },
        ];

        foreach ($mutations as $label => $mutate) {
            $brokenPath = Storage::disk('local')->path(BackupUserData::SNAPSHOT_DIRECTORY.'/broken-opening.json');
            file_put_contents($brokenPath, json_encode($mutate($valid), JSON_THROW_ON_ERROR));
            $targetUser = User::factory()->create();

            $exitCode = Artisan::call('user:restore', ['user' => $targetUser->email, 'path' => $brokenPath, '--force' => true]);

            $this->assertSame(1, $exitCode, $label);
            $this->assertSame(0, $targetUser->categories()->count(), $label);
        }
    }

    public function test_restore_command_restores_the_v9_fixture_with_the_rhythm_history_and_no_opening_collected(): void
    {
        $this->travelTo('2026-11-10');
        $targetUser = User::factory()->create();

        $exitCode = Artisan::call('user:restore', [
            'user' => $targetUser->email,
            'path' => base_path('tests/Fixtures/snapshots/v9-category-list.json'),
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());
        $this->assertSame(
            [
                'Czynsz' => [['running', 2026, 1], ['fixed_charge', 2026, 11]],
                'Pensja' => [],
                'Rekolekcje' => [['even_annual', 2026, 1]],
            ],
            $this->rhythmsByName($targetUser),
        );
        $this->assertSame(
            0,
            CategoryRhythm::query()->whereIn('category_id', $targetUser->categories()->select('id'))->whereNotNull('opening_collected_minor_units')->count(),
        );
    }

    public function test_restore_command_restores_a_v9_snapshot_written_by_the_current_code_and_refuses_an_irregular_entry_in_it(): void
    {
        Storage::fake('local');
        $this->travelTo('2026-03-10');

        $sourceUser = User::factory()->create();
        Category::factory()->for($sourceUser)->expense()->create(['name' => 'Czynsz']);
        $path = app(BackupUserData::class)->handle($sourceUser);
        $valid = $this->asV9Snapshot(json_decode(file_get_contents($path), true));
        $v9Path = Storage::disk('local')->path(BackupUserData::SNAPSHOT_DIRECTORY.'/v9.json');
        file_put_contents($v9Path, json_encode($valid, JSON_THROW_ON_ERROR));

        $this->assertSame(9, $valid['version']);
        $this->assertArrayNotHasKey('opening_collected_minor_units', $valid['categories'][0]['rhythms'][0]);

        $targetUser = User::factory()->create();
        $this->assertSame(0, Artisan::call('user:restore', ['user' => $targetUser->email, 'path' => $v9Path, '--force' => true]), Artisan::output());
        $this->assertSame(['Czynsz' => [['running', 2026, 3]]], $this->rhythmsByName($targetUser));

        $valid['categories'][0]['rhythms'][0]['rhythm'] = 'irregular';
        file_put_contents($v9Path, json_encode($valid, JSON_THROW_ON_ERROR));
        $otherUser = User::factory()->create();

        $this->assertSame(1, Artisan::call('user:restore', ['user' => $otherUser->email, 'path' => $v9Path, '--force' => true]));
        $this->assertSame(0, $otherUser->categories()->count());
    }

    public function test_a_v8_one_off_flag_restores_as_even_annual(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        BudgetLine::factory()->for($budget)->expense()->create(['name' => 'One-off']);

        $path = app(BackupUserData::class)->handle($user);
        $snapshot = $this->asV8Snapshot(json_decode(file_get_contents($path), true), ['is_paced' => false, 'month_item_kind' => 'running']);
        file_put_contents($path, json_encode($snapshot));

        $targetUser = User::factory()->create();
        app(RestoreUserData::class)->handle($targetUser, $path);

        $this->assertSame(['One-off' => [['even_annual', 2026, 1]]], $this->rhythmsByName($targetUser));
    }

    public function test_a_v8_line_without_the_flags_restores_its_expense_category_as_running(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['year' => 2026]);
        $category = BudgetLine::factory()->for($budget)->evenAnnual()->create(['name' => 'Legacy']);

        $path = app(BackupUserData::class)->handle($user);
        $snapshot = $this->asV8Snapshot(json_decode(file_get_contents($path), true));
        file_put_contents($path, json_encode($snapshot));

        $this->assertArrayNotHasKey('is_paced', $snapshot['budget_lines'][0]);

        $targetUser = User::factory()->create();
        app(RestoreUserData::class)->handle($targetUser, $path);

        $restoredCategory = BudgetLine::query()
            ->whereHas('budget', fn ($query) => $query->where('user_id', $targetUser->id))
            ->whereHas('category', fn ($query) => $query->where('name', 'Legacy'))
            ->firstOrFail();

        $this->assertSame(['Legacy' => [['running', 2026, 1]]], $this->rhythmsByName($targetUser));
        $this->assertNotSame($category->id, $restoredCategory->id);
    }

    /**
     * @return array<string, list<array{0: string, 1: int, 2: int}>> Category name => entries
     */
    private function rhythmsByName(User $user): array
    {
        $result = [];

        foreach ($user->categories()->with('rhythms')->orderBy('name')->get() as $category) {
            $result[$category->name] = $category->rhythms
                ->map(fn (CategoryRhythm $entry): array => [$entry->rhythm->value, $entry->start_year, $entry->start_month])
                ->all();
        }

        ksort($result);

        return $result;
    }

    public function test_backup_writes_the_default_set_aside_percent_and_restore_round_trips_it(): void
    {
        Storage::fake('local');

        $sourceUser = User::factory()->withDefaultSetAsidePercent(10)->create();
        $targetUser = User::factory()->create();

        $path = app(BackupUserData::class)->handle($sourceUser);
        $snapshot = json_decode(file_get_contents($path), true);

        $this->assertSame(11, $snapshot['version']);
        $this->assertSame(10, $snapshot['default_set_aside_percent']);

        app(RestoreUserData::class)->handle($targetUser, $path);

        $this->assertSame(10, $targetUser->refresh()->default_set_aside_percent);
    }

    public function test_restore_of_an_empty_percent_clears_the_percent_of_the_target_user(): void
    {
        Storage::fake('local');

        $sourceUser = User::factory()->create();
        $targetUser = User::factory()->withDefaultSetAsidePercent(20)->create();

        $path = app(BackupUserData::class)->handle($sourceUser);
        app(RestoreUserData::class)->handle($targetUser, $path);

        $this->assertNull($targetUser->refresh()->default_set_aside_percent);
    }

    public function test_restore_command_restores_a_v10_snapshot_with_a_null_default_set_aside_percent(): void
    {
        Storage::fake('local');

        $sourceUser = User::factory()->withDefaultSetAsidePercent(10)->create();
        $path = app(BackupUserData::class)->handle($sourceUser);
        $v10 = json_decode(file_get_contents($path), true);
        unset($v10['default_set_aside_percent']);
        $v10['version'] = 10;
        $v10Path = Storage::disk('local')->path(BackupUserData::SNAPSHOT_DIRECTORY.'/v10.json');
        file_put_contents($v10Path, json_encode($v10, JSON_THROW_ON_ERROR));

        $targetUser = User::factory()->withDefaultSetAsidePercent(30)->create();

        $this->assertSame(0, Artisan::call('user:restore', ['user' => $targetUser->email, 'path' => $v10Path, '--force' => true]), Artisan::output());
        $this->assertNull($targetUser->refresh()->default_set_aside_percent);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidDefaultSetAsidePercents(): array
    {
        return [
            'above 100' => [101],
            'negative' => [-1],
            'text' => ['10'],
            'fraction' => [10.5],
        ];
    }

    #[DataProvider('invalidDefaultSetAsidePercents')]
    public function test_restore_command_refuses_an_invalid_default_set_aside_percent_in_a_v11_snapshot(mixed $percent): void
    {
        Storage::fake('local');

        $sourceUser = User::factory()->create();
        $path = app(BackupUserData::class)->handle($sourceUser);
        $snapshot = json_decode(file_get_contents($path), true);
        $snapshot['default_set_aside_percent'] = $percent;
        $badPath = Storage::disk('local')->path(BackupUserData::SNAPSHOT_DIRECTORY.'/bad-percent.json');
        file_put_contents($badPath, json_encode($snapshot, JSON_THROW_ON_ERROR));

        $targetUser = User::factory()->withDefaultSetAsidePercent(20)->create();

        $this->assertSame(1, Artisan::call('user:restore', ['user' => $targetUser->email, 'path' => $badPath, '--force' => true]));
        $this->assertSame(20, $targetUser->refresh()->default_set_aside_percent);
    }

    /**
     * Turns a snapshot written by the current code into version 9: the Rhythm
     * history without Opening Collected.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function asV9Snapshot(array $snapshot): array
    {
        $snapshot['categories'] = array_map(function (array $category): array {
            $category['rhythms'] = array_map(function (array $entry): array {
                unset($entry['opening_collected_minor_units']);

                return $entry;
            }, $category['rhythms']);

            return $category;
        }, $snapshot['categories']);
        $snapshot['version'] = 9;

        return $snapshot;
    }

    /**
     * Turns a snapshot written by the current code into version 8: Category
     * Groups, but no Rhythm history. A version 8 Budget Line carried the flags
     * the current code no longer writes; `$lineFlags` puts them on every line.
     *
     * @param  array<string, mixed>  $snapshot
     * @param  array{is_paced?: bool, month_item_kind?: string}  $lineFlags
     * @return array<string, mixed>
     */
    private function asV8Snapshot(array $snapshot, array $lineFlags = []): array
    {
        $snapshot['budget_lines'] = array_map(fn (array $line): array => [...$line, ...$lineFlags], $snapshot['budget_lines']);
        $snapshot['categories'] = array_map(function (array $category): array {
            unset($category['rhythms']);

            return $category;
        }, $snapshot['categories']);
        $snapshot['version'] = 8;

        return $snapshot;
    }

    /**
     * Turns a snapshot written by the current code into version 7: Category
     * descriptions, but no Category Groups and no group membership.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function asV7Snapshot(array $snapshot): array
    {
        unset($snapshot['category_groups']);
        $snapshot['categories'] = array_map(function (array $category): array {
            unset($category['category_group_id']);

            return $category;
        }, $snapshot['categories']);
        $snapshot['version'] = 7;

        return $snapshot;
    }

    /**
     * Turns a snapshot written by the current code into version 6: no
     * Category Groups, no group membership and no description on Categories.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function asV6Snapshot(array $snapshot): array
    {
        unset($snapshot['category_groups']);
        $snapshot['categories'] = array_map(function (array $category): array {
            unset($category['category_group_id'], $category['description']);

            return $category;
        }, $snapshot['categories']);
        $snapshot['version'] = 6;

        return $snapshot;
    }

    /**
     * Turns a snapshot written by the current code into the shape of versions
     * 1 to 5: Budget Lines carry the Category description, and operations and
     * Month Plan items name their line `category_id`.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function asLegacySnapshot(array $snapshot, int $version): array
    {
        $categories = collect($snapshot['categories'])->keyBy('id');

        $snapshot['categories'] = array_map(function (array $line) use ($categories): array {
            $category = $categories[$line['category_id']];
            unset($line['category_id']);

            return [
                ...$line,
                'name' => $category['name'],
                'icon' => $category['icon'],
                'color' => $category['color'],
                'sort_order' => $category['sort_order'],
                'type' => $category['type'],
            ];
        }, $snapshot['budget_lines']);
        unset($snapshot['budget_lines']);

        $rename = function (array $row): array {
            $row['category_id'] = $row['budget_line_id'];
            unset($row['budget_line_id']);

            return $row;
        };

        $snapshot['financial_operations'] = array_map($rename, $snapshot['financial_operations']);
        $snapshot['month_plans'] = array_map(function (array $monthPlan) use ($rename): array {
            $monthPlan['items'] = array_map($rename, $monthPlan['items']);

            return $monthPlan;
        }, $snapshot['month_plans']);
        $snapshot['version'] = $version;

        return $snapshot;
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
        $category = BudgetLine::factory()->for($budget)->income()->create([
            'name' => 'Salary',
            'execution_minor_units' => 150000,
        ]);

        FinancialOperation::factory()->for($user)->create([
            'account_id' => $sourceAccount->id,
            'budget_id' => $budget->id,
            'budget_line_id' => $category->id,
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

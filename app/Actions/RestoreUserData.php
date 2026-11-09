<?php

namespace App\Actions;

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\BudgetPhase;
use App\Enums\CapitalMovementDirection;
use App\Enums\CategoryType;
use App\Enums\FinancialOperationType;
use App\Enums\MonthItemKind;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\MonthPlan;
use App\Models\User;
use App\Support\CategoryList\LegacyCategoryConversion;
use App\Support\UserSnapshot\UserSnapshotException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RestoreUserData
{
    public function __construct(private ResetUserData $resetUserData) {}

    public function handle(User $targetUser, string $path): void
    {
        $snapshot = $this->readSnapshot($path);

        DB::transaction(function () use ($targetUser, $snapshot): void {
            $this->resetUserData->handle($targetUser);

            $accountIdMap = $this->restoreAccounts($targetUser, $snapshot['accounts']);
            $termDepositIdMap = $this->restoreTermDeposits($targetUser, $snapshot['term_deposits'] ?? []);
            $budgetIdMap = $this->restoreBudgets($targetUser, $snapshot['budgets']);
            $categoryIdMap = $this->restoreCategories($targetUser, $snapshot['budgets'], $snapshot['categories'], $budgetIdMap);
            $this->restoreMonthPlans($snapshot['month_plans'] ?? [], $budgetIdMap, $categoryIdMap);
            $this->restoreFinancialOperations($targetUser, $snapshot['financial_operations'], $accountIdMap, $categoryIdMap, $budgetIdMap);
            $this->restoreOwnTransfers($targetUser, $snapshot['own_transfers'], $accountIdMap);
            $this->restoreCapitalMovements(
                $targetUser,
                $snapshot['capital_movements'] ?? [],
                $accountIdMap,
                $termDepositIdMap,
            );

            $targetUser->forceFill([
                'first_run_checklist_dismissed_at' => isset($snapshot['first_run_checklist_dismissed_at'])
                    ? Carbon::parse($snapshot['first_run_checklist_dismissed_at'])
                    : null,
            ])->save();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function readSnapshot(string $path): array
    {
        if (! is_file($path)) {
            throw new UserSnapshotException("Snapshot file not found: {$path}");
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new UserSnapshotException("Unable to read snapshot file: {$path}");
        }

        try {
            $snapshot = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new UserSnapshotException("Invalid snapshot JSON: {$exception->getMessage()}", previous: $exception);
        }

        if (! is_array($snapshot)) {
            throw new UserSnapshotException('Snapshot must decode to a JSON object.');
        }

        $version = $snapshot['version'] ?? null;

        if (! in_array($version, [1, 2, 3, 4, BackupUserData::SNAPSHOT_VERSION], true)) {
            throw new UserSnapshotException('Unsupported snapshot version.');
        }

        foreach (['source', 'accounts', 'budgets', 'categories', 'financial_operations', 'own_transfers'] as $key) {
            if (! array_key_exists($key, $snapshot)) {
                throw new UserSnapshotException("Snapshot is missing required key: {$key}");
            }
        }

        if (in_array($version, [2, 3, 4, BackupUserData::SNAPSHOT_VERSION], true) && ! array_key_exists('term_deposits', $snapshot)) {
            throw new UserSnapshotException('Snapshot is missing required key: term_deposits');
        }

        if (in_array($version, [3, 4, BackupUserData::SNAPSHOT_VERSION], true) && ! array_key_exists('capital_movements', $snapshot)) {
            throw new UserSnapshotException('Snapshot is missing required key: capital_movements');
        }

        if ($version === BackupUserData::SNAPSHOT_VERSION && ! array_key_exists('month_plans', $snapshot)) {
            throw new UserSnapshotException('Snapshot is missing required key: month_plans');
        }

        return $snapshot;
    }

    /**
     * @param  list<array<string, mixed>>  $accounts
     * @return array<int, int>
     */
    private function restoreAccounts(User $targetUser, array $accounts): array
    {
        $accountIdMap = [];

        foreach ($accounts as $accountData) {
            $account = $targetUser->accounts()->create([
                'name' => $accountData['name'],
                'type' => AccountType::from($accountData['type']),
                'bank' => isset($accountData['bank']) ? Bank::from($accountData['bank']) : null,
                'opening_date' => $accountData['opening_date'],
                'opening_balance_minor_units' => $accountData['opening_balance_minor_units'],
                'current_balance_minor_units' => $accountData['current_balance_minor_units'],
                'archived_at' => isset($accountData['archived_at']) ? Carbon::parse($accountData['archived_at']) : null,
                'created_at' => isset($accountData['created_at']) ? Carbon::parse($accountData['created_at']) : now(),
                'updated_at' => isset($accountData['updated_at']) ? Carbon::parse($accountData['updated_at']) : now(),
            ]);

            $accountIdMap[$accountData['id']] = $account->id;
        }

        return $accountIdMap;
    }

    /**
     * @param  list<array<string, mixed>>  $termDeposits
     * @return array<int, int>
     */
    private function restoreTermDeposits(User $targetUser, array $termDeposits): array
    {
        $termDepositIdMap = [];

        foreach ($termDeposits as $termDepositData) {
            $termDeposit = $targetUser->termDeposits()->create([
                'name' => $termDepositData['name'],
                'bank' => isset($termDepositData['bank'])
                    ? Bank::from($termDepositData['bank'])
                    : Bank::Other,
                'opening_date' => $termDepositData['opening_date'],
                'end_date' => $termDepositData['end_date'] ?? null,
                'opening_balance_minor_units' => $termDepositData['opening_balance_minor_units'],
                'current_balance_minor_units' => $termDepositData['current_balance_minor_units'],
                'archived_at' => isset($termDepositData['archived_at']) ? Carbon::parse($termDepositData['archived_at']) : null,
                'created_at' => isset($termDepositData['created_at']) ? Carbon::parse($termDepositData['created_at']) : now(),
                'updated_at' => isset($termDepositData['updated_at']) ? Carbon::parse($termDepositData['updated_at']) : now(),
            ]);

            $termDepositIdMap[$termDepositData['id']] = $termDeposit->id;
        }

        return $termDepositIdMap;
    }

    /**
     * @param  list<array<string, mixed>>  $budgets
     * @return array<int, int>
     */
    private function restoreBudgets(User $targetUser, array $budgets): array
    {
        $budgetIdMap = [];

        foreach ($budgets as $budgetData) {
            $budget = $targetUser->budgets()->create([
                'year' => $budgetData['year'],
                'phase' => BudgetPhase::from($budgetData['phase']),
                'created_at' => isset($budgetData['created_at']) ? Carbon::parse($budgetData['created_at']) : now(),
                'updated_at' => isset($budgetData['updated_at']) ? Carbon::parse($budgetData['updated_at']) : now(),
            ]);

            $budgetIdMap[$budgetData['id']] = $budget->id;
        }

        return $budgetIdMap;
    }

    /**
     * Snapshots 1 to 5 hold the old per-Budget Category records (now Budget
     * Lines). The User's Category list is made from them by the same class as
     * the data migration (ADR-0063, steps (a) to (d)), then the lines are
     * restored pointing at it.
     *
     * @param  list<array<string, mixed>>  $budgets
     * @param  list<array<string, mixed>>  $categories
     * @param  array<int, int>  $budgetIdMap
     * @return array<int, int>
     */
    private function restoreCategories(User $targetUser, array $budgets, array $categories, array $budgetIdMap): array
    {
        $listCategoryIds = $this->restoreCategoryList($targetUser, $budgets, $categories, $budgetIdMap);
        $categoryIdMap = [];

        foreach ($categories as $categoryData) {
            $budgetId = $budgetIdMap[$categoryData['budget_id']] ?? null;

            if ($budgetId === null) {
                throw new UserSnapshotException("Snapshot references unknown budget id {$categoryData['budget_id']}.");
            }

            $category = BudgetLine::query()->create([
                'budget_id' => $budgetId,
                'category_id' => $listCategoryIds[$categoryData['id']],
                'name' => $categoryData['name'],
                'icon' => $categoryData['icon'],
                'color' => $categoryData['color'],
                'sort_order' => $categoryData['sort_order'],
                'type' => CategoryType::from($categoryData['type']),
                'is_paced' => $this->restoredCategoryIsPaced($categoryData),
                'month_item_kind' => $this->restoredCategoryMonthItemKind($categoryData),
                'planned_amount_minor_units' => $categoryData['planned_amount_minor_units'],
                'initial_plan_minor_units' => $categoryData['initial_plan_minor_units'],
                'execution_minor_units' => $categoryData['execution_minor_units'],
                'archived_at' => isset($categoryData['archived_at']) ? Carbon::parse($categoryData['archived_at']) : null,
                'created_at' => isset($categoryData['created_at']) ? Carbon::parse($categoryData['created_at']) : now(),
                'updated_at' => isset($categoryData['updated_at']) ? Carbon::parse($categoryData['updated_at']) : now(),
            ]);

            $categoryIdMap[$categoryData['id']] = $category->id;
        }

        return $categoryIdMap;
    }

    /**
     * @param  list<array<string, mixed>>  $budgets
     * @param  list<array<string, mixed>>  $categories
     * @param  array<int, int>  $budgetIdMap
     * @return array<int, int> snapshot Budget Line id => id of its list Category
     */
    private function restoreCategoryList(User $targetUser, array $budgets, array $categories, array $budgetIdMap): array
    {
        foreach ($categories as $categoryData) {
            if (! isset($budgetIdMap[$categoryData['budget_id']])) {
                throw new UserSnapshotException("Snapshot references unknown budget id {$categoryData['budget_id']}.");
            }
        }

        $conversion = LegacyCategoryConversion::convert(
            array_map(fn (array $budget): array => [
                'id' => (int) $budget['id'],
                'year' => (int) $budget['year'],
                'phase' => (string) $budget['phase'],
            ], $budgets),
            array_map(fn (array $category): array => [
                'id' => (int) $category['id'],
                'budget_id' => (int) $category['budget_id'],
                'name' => (string) $category['name'],
                'icon' => (string) $category['icon'],
                'color' => (string) $category['color'],
                'sort_order' => (int) $category['sort_order'],
                'type' => (string) $category['type'],
                'archived_at' => isset($category['archived_at']) ? (string) $category['archived_at'] : null,
            ], $categories),
            CarbonImmutable::now(),
        );

        $now = now();

        Category::query()->insert(array_map(fn (array $category): array => [
            'user_id' => $targetUser->id,
            'name' => $category['name'],
            'name_key' => $category['name_key'],
            'icon' => $category['icon'],
            'color' => $category['color'],
            'sort_order' => $category['sort_order'],
            'type' => $category['type'],
            'archived_at' => $category['archived_at']?->format('Y-m-d H:i:s'),
            'created_at' => $now,
            'updated_at' => $now,
        ], $conversion['categories']));

        $idsByKey = [];

        foreach ($targetUser->categories()->get(['id', 'type', 'name_key']) as $listCategory) {
            $idsByKey[$listCategory->type->value."\0".$listCategory->name_key] = $listCategory->id;
        }

        $listIds = [];

        foreach ($conversion['categories'] as $category) {
            $listIds[$category['key']] = $idsByKey[$category['type']."\0".$category['name_key']];
        }

        $lineCategoryIds = [];

        foreach ($conversion['lineCategory'] as $lineId => $key) {
            $lineCategoryIds[$lineId] = $listIds[$key];
        }

        return $lineCategoryIds;
    }

    /**
     * @param  list<array<string, mixed>>  $monthPlans
     * @param  array<int, int>  $budgetIdMap
     * @param  array<int, int>  $categoryIdMap
     */
    private function restoreMonthPlans(array $monthPlans, array $budgetIdMap, array $categoryIdMap): void
    {
        $seenPlans = [];

        foreach ($monthPlans as $monthPlanData) {
            if (! isset($monthPlanData['budget_id'], $monthPlanData['month'], $monthPlanData['approved_at'])) {
                throw new UserSnapshotException('Snapshot month plan is missing budget_id, month or approved_at.');
            }

            $month = $monthPlanData['month'];

            if (! is_int($month) || $month < 1 || $month > 12) {
                throw new UserSnapshotException('Snapshot month plan has an invalid month.');
            }

            $budgetId = $budgetIdMap[$monthPlanData['budget_id']] ?? null;

            if ($budgetId === null) {
                throw new UserSnapshotException("Snapshot references unknown budget id {$monthPlanData['budget_id']}.");
            }

            if (isset($seenPlans["{$budgetId}:{$month}"])) {
                throw new UserSnapshotException('Snapshot contains a duplicated month plan.');
            }

            $seenPlans["{$budgetId}:{$month}"] = true;

            $monthPlan = MonthPlan::query()->create([
                'budget_id' => $budgetId,
                'month' => $month,
                'approved_at' => Carbon::parse($monthPlanData['approved_at']),
                'approved_set_aside_minor_units' => $monthPlanData['approved_set_aside_minor_units'] ?? 0,
                'current_set_aside_minor_units' => $monthPlanData['current_set_aside_minor_units'] ?? 0,
            ]);

            $seenCategories = [];

            foreach ($monthPlanData['items'] ?? [] as $itemData) {
                if (! isset($itemData['category_id'], $itemData['approved_amount_minor_units'], $itemData['current_amount_minor_units'])) {
                    throw new UserSnapshotException('Snapshot month plan item is missing a required key.');
                }

                $categoryId = $categoryIdMap[$itemData['category_id']] ?? null;

                if ($categoryId === null) {
                    throw new UserSnapshotException("Snapshot references unknown category id {$itemData['category_id']}.");
                }

                if (isset($seenCategories[$categoryId])) {
                    throw new UserSnapshotException('Snapshot month plan contains a duplicated category.');
                }

                $seenCategories[$categoryId] = true;

                if (BudgetLine::query()->whereKey($categoryId)->value('budget_id') !== $budgetId) {
                    throw new UserSnapshotException('Snapshot month plan item references a category of another budget.');
                }

                $monthPlan->items()->create([
                    'budget_line_id' => $categoryId,
                    'approved_amount_minor_units' => $itemData['approved_amount_minor_units'],
                    'current_amount_minor_units' => $itemData['current_amount_minor_units'],
                ]);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $operations
     * @param  array<int, int>  $accountIdMap
     * @param  array<int, int>  $categoryIdMap
     * @param  array<int, int>  $budgetIdMap
     */
    private function restoreFinancialOperations(
        User $targetUser,
        array $operations,
        array $accountIdMap,
        array $categoryIdMap,
        array $budgetIdMap,
    ): void {
        foreach ($operations as $operationData) {
            $accountId = $accountIdMap[$operationData['account_id']] ?? null;
            $categoryId = $categoryIdMap[$operationData['category_id']] ?? null;
            $budgetId = $budgetIdMap[$operationData['budget_id']] ?? null;

            if ($accountId === null || $categoryId === null || $budgetId === null) {
                throw new UserSnapshotException('Snapshot references unknown financial operation relations.');
            }

            $targetUser->financialOperations()->create([
                'type' => FinancialOperationType::from($operationData['type']),
                'account_id' => $accountId,
                'budget_line_id' => $categoryId,
                'budget_id' => $budgetId,
                'operation_date' => $operationData['operation_date'],
                'amount_minor_units' => $operationData['amount_minor_units'],
                'description' => $operationData['description'],
                'counterparty' => $operationData['counterparty'] ?? null,
                'raw_statement_description' => $operationData['raw_statement_description'] ?? null,
                'import_identity_account_id' => $operationData['import_identity_account_id'] ?? null,
                'import_identity_date' => $operationData['import_identity_date'] ?? null,
                'import_identity_amount_minor_units' => $operationData['import_identity_amount_minor_units'] ?? null,
                'import_identity_digest' => $operationData['import_identity_digest'] ?? null,
                'created_at' => isset($operationData['created_at']) ? Carbon::parse($operationData['created_at']) : now(),
                'updated_at' => isset($operationData['updated_at']) ? Carbon::parse($operationData['updated_at']) : now(),
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $transfers
     * @param  array<int, int>  $accountIdMap
     */
    private function restoreOwnTransfers(User $targetUser, array $transfers, array $accountIdMap): void
    {
        foreach ($transfers as $transferData) {
            $sourceAccountId = $accountIdMap[$transferData['source_account_id']] ?? null;
            $destinationAccountId = $accountIdMap[$transferData['destination_account_id']] ?? null;

            if ($sourceAccountId === null || $destinationAccountId === null) {
                throw new UserSnapshotException('Snapshot references unknown own transfer account relations.');
            }

            $targetUser->ownTransfers()->create([
                'source_account_id' => $sourceAccountId,
                'destination_account_id' => $destinationAccountId,
                'transfer_date' => $transferData['transfer_date'],
                'amount_minor_units' => $transferData['amount_minor_units'],
                'description' => $transferData['description'],
                'source_raw_statement_description' => $transferData['source_raw_statement_description']
                    ?? $transferData['raw_statement_description']
                    ?? null,
                'destination_raw_statement_description' => $transferData['destination_raw_statement_description'] ?? null,
                'source_import_identity_account_id' => $transferData['source_import_identity_account_id'] ?? null,
                'source_import_identity_date' => $transferData['source_import_identity_date'] ?? null,
                'source_import_identity_amount_minor_units' => $transferData['source_import_identity_amount_minor_units'] ?? null,
                'source_import_identity_digest' => $transferData['source_import_identity_digest'] ?? null,
                'destination_import_identity_account_id' => $transferData['destination_import_identity_account_id'] ?? null,
                'destination_import_identity_date' => $transferData['destination_import_identity_date'] ?? null,
                'destination_import_identity_amount_minor_units' => $transferData['destination_import_identity_amount_minor_units'] ?? null,
                'destination_import_identity_digest' => $transferData['destination_import_identity_digest'] ?? null,
                'created_at' => isset($transferData['created_at']) ? Carbon::parse($transferData['created_at']) : now(),
                'updated_at' => isset($transferData['updated_at']) ? Carbon::parse($transferData['updated_at']) : now(),
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $movements
     * @param  array<int, int>  $accountIdMap
     * @param  array<int, int>  $termDepositIdMap
     */
    private function restoreCapitalMovements(
        User $targetUser,
        array $movements,
        array $accountIdMap,
        array $termDepositIdMap,
    ): void {
        foreach ($movements as $movementData) {
            $accountId = $accountIdMap[$movementData['account_id']] ?? null;
            $termDepositId = $termDepositIdMap[$movementData['term_deposit_id']] ?? null;

            if ($accountId === null || $termDepositId === null) {
                throw new UserSnapshotException('Snapshot references unknown capital movement relations.');
            }

            $importIdentityAccountId = isset($movementData['import_identity_account_id'])
                ? ($accountIdMap[$movementData['import_identity_account_id']] ?? null)
                : null;

            if (
                isset($movementData['import_identity_account_id'])
                && $importIdentityAccountId === null
            ) {
                throw new UserSnapshotException('Snapshot references unknown capital movement import identity account.');
            }

            $targetUser->capitalMovements()->create([
                'account_id' => $accountId,
                'term_deposit_id' => $termDepositId,
                'movement_date' => $movementData['movement_date'],
                'amount_minor_units' => $movementData['amount_minor_units'],
                'direction' => CapitalMovementDirection::from($movementData['direction']),
                'description' => $movementData['description'],
                'raw_statement_description' => $movementData['raw_statement_description'] ?? null,
                'import_identity_account_id' => $importIdentityAccountId,
                'import_identity_date' => $movementData['import_identity_date'] ?? null,
                'import_identity_amount_minor_units' => $movementData['import_identity_amount_minor_units'] ?? null,
                'import_identity_digest' => $movementData['import_identity_digest'] ?? null,
                'created_at' => isset($movementData['created_at']) ? Carbon::parse($movementData['created_at']) : now(),
                'updated_at' => isset($movementData['updated_at']) ? Carbon::parse($movementData['updated_at']) : now(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $categoryData
     */
    private function restoredCategoryIsPaced(array $categoryData): bool
    {
        if ($categoryData['type'] !== CategoryType::Expense->value) {
            return true;
        }

        return $categoryData['is_paced'] ?? true;
    }

    /**
     * @param  array<string, mixed>  $categoryData
     */
    private function restoredCategoryMonthItemKind(array $categoryData): MonthItemKind
    {
        if ($categoryData['type'] !== CategoryType::Expense->value) {
            return MonthItemKind::Running;
        }

        return MonthItemKind::tryFrom((string) ($categoryData['month_item_kind'] ?? '')) ?? MonthItemKind::Running;
    }
}

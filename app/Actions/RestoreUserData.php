<?php

namespace App\Actions;

use App\Enums\AccountType;
use App\Enums\Bank;
use App\Enums\BudgetPhase;
use App\Enums\CapitalMovementDirection;
use App\Enums\CategoryType;
use App\Enums\FinancialOperationType;
use App\Enums\Rhythm;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\CategoryRhythm;
use App\Models\MonthPlan;
use App\Models\User;
use App\Support\CategoryList\CategoryNameKey;
use App\Support\CategoryList\LegacyCategoryConversion;
use App\Support\CategoryList\LegacyRhythmConversion;
use App\Support\UserSnapshot\RestoredImportIdentity;
use App\Support\UserSnapshot\UserSnapshotException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RestoreUserData
{
    /**
     * Versions holding the User's Category list and Budget Lines (6; 7 with the
     * description; 8 with the Category Groups; 9 with the Rhythm history; 10 with the Opening
     * Collected on Irregular entries; 11 with the Default Set-aside Percent).
     *
     * @var list<int>
     */
    private const LIST_FORMAT_VERSIONS = [6, 7, 8, 9, 10, BackupUserData::SNAPSHOT_VERSION];

    /**
     * Versions that carry the Rhythm history; older ones are converted from the
     * flags of their Budget Lines.
     *
     * @var list<int>
     */
    private const RHYTHM_VERSIONS = [9, 10, BackupUserData::SNAPSHOT_VERSION];

    /**
     * The flags of the Budget Lines of a snapshot of version 1 to 8, kept only
     * as the input of `LegacyRhythmConversion`: the lines no longer store them.
     *
     * @var list<array{budget_id: int, category_id: int, category_type: string, is_paced: bool, month_item_kind: string}>
     */
    private array $legacyFlagLines = [];

    public function __construct(
        private ResetUserData $resetUserData,
        private RestoredImportIdentity $restoredImportIdentity,
    ) {}

    public function handle(User $targetUser, string $path): void
    {
        $snapshot = $this->readSnapshot($path);
        $isCurrentFormat = in_array($snapshot['version'], self::LIST_FORMAT_VERSIONS, true);

        if (! $isCurrentFormat) {
            $snapshot = $this->withBudgetLineKeys($snapshot);
        }

        $this->legacyFlagLines = [];

        DB::transaction(function () use ($targetUser, $snapshot, $isCurrentFormat): void {
            $this->resetUserData->handle($targetUser);

            $accountIdMap = $this->restoreAccounts($targetUser, $snapshot['accounts']);
            $termDepositIdMap = $this->restoreTermDeposits($targetUser, $snapshot['term_deposits'] ?? []);
            $budgetIdMap = $this->restoreBudgets($targetUser, $snapshot['budgets']);
            $categoryIdMap = $isCurrentFormat
                ? $this->restoreBudgetLines($targetUser, $snapshot['category_groups'] ?? [], $snapshot['categories'], $snapshot['budget_lines'], $budgetIdMap, $snapshot['version'])
                : $this->restoreCategories($targetUser, $snapshot['budgets'], $snapshot['categories'], $budgetIdMap);
            if (! in_array($snapshot['version'], self::RHYTHM_VERSIONS, true)) {
                $this->restoreLegacyRhythms($targetUser);
            }

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
                'default_set_aside_percent' => $snapshot['default_set_aside_percent'] ?? null,
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

        if (! in_array($version, [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, BackupUserData::SNAPSHOT_VERSION], true)) {
            throw new UserSnapshotException('Unsupported snapshot version.');
        }

        foreach (['source', 'accounts', 'budgets', 'categories', 'financial_operations', 'own_transfers'] as $key) {
            if (! array_key_exists($key, $snapshot)) {
                throw new UserSnapshotException("Snapshot is missing required key: {$key}");
            }
        }

        if (in_array($version, [2, 3, 4, 5, 6, 7, 8, 9, 10, BackupUserData::SNAPSHOT_VERSION], true) && ! array_key_exists('term_deposits', $snapshot)) {
            throw new UserSnapshotException('Snapshot is missing required key: term_deposits');
        }

        if (in_array($version, [3, 4, 5, 6, 7, 8, 9, 10, BackupUserData::SNAPSHOT_VERSION], true) && ! array_key_exists('capital_movements', $snapshot)) {
            throw new UserSnapshotException('Snapshot is missing required key: capital_movements');
        }

        if (in_array($version, [5, 6, 7, 8, 9, 10, BackupUserData::SNAPSHOT_VERSION], true) && ! array_key_exists('month_plans', $snapshot)) {
            throw new UserSnapshotException('Snapshot is missing required key: month_plans');
        }

        if (in_array($version, self::LIST_FORMAT_VERSIONS, true) && ! array_key_exists('budget_lines', $snapshot)) {
            throw new UserSnapshotException('Snapshot is missing required key: budget_lines');
        }

        if (in_array($version, [8, 9, 10, BackupUserData::SNAPSHOT_VERSION], true) && ! array_key_exists('category_groups', $snapshot)) {
            throw new UserSnapshotException('Snapshot is missing required key: category_groups');
        }

        if ($version === BackupUserData::SNAPSHOT_VERSION) {
            $percent = $snapshot['default_set_aside_percent'] ?? null;

            if (! array_key_exists('default_set_aside_percent', $snapshot)) {
                throw new UserSnapshotException('Snapshot is missing required key: default_set_aside_percent');
            }

            if ($percent !== null && (! is_int($percent) || $percent < 0 || $percent > 100)) {
                throw new UserSnapshotException('Snapshot default_set_aside_percent must be a whole number from 0 to 100 or null.');
            }
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
     * Snapshots 1 to 5 name the Budget Line of an operation or Month Plan item
     * `category_id`; the restore below reads `budget_line_id` only.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function withBudgetLineKeys(array $snapshot): array
    {
        $rename = function (array $row): array {
            if (array_key_exists('category_id', $row)) {
                $row['budget_line_id'] = $row['category_id'];
                unset($row['category_id']);
            }

            return $row;
        };

        $snapshot['financial_operations'] = array_map($rename, $snapshot['financial_operations']);
        $snapshot['month_plans'] = array_map(function (array $monthPlan) use ($rename): array {
            $monthPlan['items'] = array_map($rename, $monthPlan['items'] ?? []);

            return $monthPlan;
        }, $snapshot['month_plans'] ?? []);

        return $snapshot;
    }

    /**
     * Snapshots 6 to 8: the User's Category list (with the Category Groups of
     * 8) and the Budget Lines pointing at it.
     *
     * @param  list<array<string, mixed>>  $categoryGroups
     * @param  list<array<string, mixed>>  $categories
     * @param  list<array<string, mixed>>  $budgetLines
     * @param  array<int, int>  $budgetIdMap
     * @return array<int, int> snapshot Budget Line id => restored Budget Line id
     */
    private function restoreBudgetLines(User $targetUser, array $categoryGroups, array $categories, array $budgetLines, array $budgetIdMap, int $version): array
    {
        $listCategories = $this->restoreCategoryListFromSnapshot($targetUser, $this->restoreCategoryGroups($targetUser, $categoryGroups), $categories, $version);
        $lineIdMap = [];
        $seenInBudget = [];
        $snapshotLineKeys = [];
        $rows = [];

        foreach ($budgetLines as $lineData) {
            $budgetId = $budgetIdMap[$lineData['budget_id']] ?? null;

            if ($budgetId === null) {
                throw new UserSnapshotException("Snapshot references unknown budget id {$lineData['budget_id']}.");
            }

            $listCategory = $listCategories[$lineData['category_id']] ?? null;

            if ($listCategory === null) {
                throw new UserSnapshotException("Snapshot references unknown category id {$lineData['category_id']}.");
            }

            if (isset($seenInBudget[$budgetId][$listCategory->id])) {
                throw new UserSnapshotException('Snapshot contains two budget lines of one category in one budget.');
            }

            $seenInBudget[$budgetId][$listCategory->id] = true;

            if (! in_array($version, self::RHYTHM_VERSIONS, true)) {
                $this->rememberLegacyFlags($budgetId, $listCategory->id, [...$lineData, 'type' => $listCategory->type->value]);
            }

            $rows[] = [
                'budget_id' => $budgetId,
                'category_id' => $listCategory->id,
                'planned_amount_minor_units' => $lineData['planned_amount_minor_units'],
                'initial_plan_minor_units' => $lineData['initial_plan_minor_units'],
                'execution_minor_units' => $lineData['execution_minor_units'],
                'archived_at' => isset($lineData['archived_at']) ? Carbon::parse($lineData['archived_at']) : null,
                'created_at' => isset($lineData['created_at']) ? Carbon::parse($lineData['created_at']) : now(),
                'updated_at' => isset($lineData['updated_at']) ? Carbon::parse($lineData['updated_at']) : now(),
            ];
            $snapshotLineKeys[$lineData['id']] = $budgetId.':'.$listCategory->id;
        }

        BudgetLine::query()->insert($rows);

        $restoredIds = [];

        foreach (BudgetLine::query()->whereIn('budget_id', array_values($budgetIdMap))->get(['id', 'budget_id', 'category_id']) as $line) {
            $restoredIds[$line->budget_id.':'.$line->category_id] = $line->id;
        }

        foreach ($snapshotLineKeys as $snapshotId => $key) {
            $lineIdMap[$snapshotId] = $restoredIds[$key];
        }

        return $lineIdMap;
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     * @return array<int, CategoryGroup> snapshot group id => restored group
     */
    private function restoreCategoryGroups(User $targetUser, array $groups): array
    {
        $restored = [];
        $seenKeys = [];

        foreach ($groups as $groupData) {
            $type = CategoryType::tryFrom((string) $groupData['type']);

            if ($type === null) {
                throw new UserSnapshotException("Snapshot category group {$groupData['id']} has an invalid type.");
            }

            $nameKey = CategoryNameKey::of((string) $groupData['name']);
            $uniqueKey = $type->value."\0".$nameKey;

            if (isset($seenKeys[$uniqueKey])) {
                throw new UserSnapshotException("Snapshot contains the category group \"{$groupData['name']}\" twice.");
            }

            $seenKeys[$uniqueKey] = true;

            $restored[$groupData['id']] = $targetUser->categoryGroups()->create([
                'name' => $groupData['name'],
                'name_key' => $nameKey,
                'type' => $type,
                'sort_order' => $groupData['sort_order'],
                'created_at' => isset($groupData['created_at']) ? Carbon::parse($groupData['created_at']) : now(),
                'updated_at' => isset($groupData['updated_at']) ? Carbon::parse($groupData['updated_at']) : now(),
            ]);
        }

        return $restored;
    }

    /**
     * @param  array<int, CategoryGroup>  $groups
     * @param  list<array<string, mixed>>  $categories
     * @return array<int, Category> snapshot Category id => restored Category
     */
    private function restoreCategoryListFromSnapshot(User $targetUser, array $groups, array $categories, int $version): array
    {
        $restored = [];
        $seenKeys = [];

        foreach ($categories as $categoryData) {
            $type = CategoryType::tryFrom((string) $categoryData['type']);

            if ($type === null) {
                throw new UserSnapshotException("Snapshot category {$categoryData['id']} has an invalid type.");
            }

            $uniqueKey = $type->value."\0".CategoryNameKey::of((string) $categoryData['name']);

            if (isset($seenKeys[$uniqueKey])) {
                throw new UserSnapshotException("Snapshot contains the category \"{$categoryData['name']}\" twice.");
            }

            $seenKeys[$uniqueKey] = true;

            $group = null;

            if (isset($categoryData['category_group_id'])) {
                $group = $groups[$categoryData['category_group_id']] ?? null;

                if ($group === null) {
                    throw new UserSnapshotException("Snapshot category {$categoryData['id']} references unknown category group id {$categoryData['category_group_id']}.");
                }

                if ($group->type !== $type) {
                    throw new UserSnapshotException("Snapshot category {$categoryData['id']} belongs to a category group of another type.");
                }
            }

            $category = $targetUser->categories()->create([
                'category_group_id' => $group?->id,
                'name' => $categoryData['name'],
                'name_key' => CategoryNameKey::of((string) $categoryData['name']),
                'icon' => $categoryData['icon'],
                'color' => $categoryData['color'],
                'description' => $categoryData['description'] ?? null,
                'sort_order' => $categoryData['sort_order'],
                'type' => $type,
                'archived_at' => isset($categoryData['archived_at']) ? Carbon::parse($categoryData['archived_at']) : null,
                'created_at' => isset($categoryData['created_at']) ? Carbon::parse($categoryData['created_at']) : now(),
                'updated_at' => isset($categoryData['updated_at']) ? Carbon::parse($categoryData['updated_at']) : now(),
            ]);

            $restored[$categoryData['id']] = $category;

            if (in_array($version, self::RHYTHM_VERSIONS, true)) {
                $this->restoreCategoryRhythms($category, $categoryData, $version);
            }
        }

        return $restored;
    }

    /**
     * The Rhythm history of a version 9 or 10 Category: an expense has at least
     * one entry, an income none, each entry a known Rhythm and a month 1 to 12,
     * and no start twice (ADR-0072). Version 10 carries the Opening Collected
     * (ADR-0073): a number >= 0 on an Irregular entry, `null` on the others;
     * version 9 never wrote an Irregular entry nor an amount.
     *
     * @param  array<string, mixed>  $categoryData
     */
    private function restoreCategoryRhythms(Category $category, array $categoryData, int $version): void
    {
        $entries = $categoryData['rhythms'] ?? null;

        if (! is_array($entries)) {
            throw new UserSnapshotException("Snapshot category {$categoryData['id']} is missing rhythms.");
        }

        if ($category->type === CategoryType::Income && $entries !== []) {
            throw new UserSnapshotException("Snapshot income category {$categoryData['id']} has a rhythm.");
        }

        if ($category->type === CategoryType::Expense && $entries === []) {
            throw new UserSnapshotException("Snapshot expense category {$categoryData['id']} has no rhythm.");
        }

        $seenStarts = [];
        $rows = [];
        $now = now();

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                throw new UserSnapshotException("Snapshot category {$categoryData['id']} has a rhythm that is not an object.");
            }

            $rhythm = Rhythm::tryFrom((string) ($entry['rhythm'] ?? ''));
            $year = $entry['start_year'] ?? null;
            $month = $entry['start_month'] ?? null;

            if ($rhythm === null) {
                throw new UserSnapshotException("Snapshot category {$categoryData['id']} has an unknown rhythm.");
            }

            if (! is_int($year) || ! is_int($month) || $year < 1 || $year > 9999 || $month < 1 || $month > 12) {
                throw new UserSnapshotException("Snapshot category {$categoryData['id']} has a rhythm with an invalid start month.");
            }

            if (isset($seenStarts[$year * 12 + $month])) {
                throw new UserSnapshotException("Snapshot category {$categoryData['id']} has two rhythms starting in one month.");
            }

            $opening = $this->openingCollectedOf($entry, $rhythm, $version, $categoryData['id']);

            $seenStarts[$year * 12 + $month] = true;
            $rows[] = [
                'category_id' => $category->id,
                'start_year' => $year,
                'start_month' => $month,
                'rhythm' => $rhythm->value,
                'opening_collected_minor_units' => $opening,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        CategoryRhythm::query()->insert($rows);
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function openingCollectedOf(array $entry, Rhythm $rhythm, int $version, mixed $categoryId): ?int
    {
        $opening = $entry['opening_collected_minor_units'] ?? null;

        if ($version === 9) {
            if ($rhythm === Rhythm::Irregular) {
                throw new UserSnapshotException("Snapshot category {$categoryId} has an irregular rhythm, which version 9 never wrote.");
            }

            return null;
        }

        if ($rhythm !== Rhythm::Irregular) {
            if ($opening !== null) {
                throw new UserSnapshotException("Snapshot category {$categoryId} has an opening collected amount on a rhythm that is not irregular.");
            }

            return null;
        }

        if ($opening === null) {
            throw new UserSnapshotException("Snapshot category {$categoryId} has an irregular rhythm without an opening collected amount.");
        }

        if (! is_int($opening) || $opening < 0) {
            throw new UserSnapshotException("Snapshot category {$categoryId} has an invalid opening collected amount.");
        }

        return $opening;
    }

    /**
     * Snapshots 1 to 8 carry no Rhythm: it is made from the flags their Budget
     * Lines carry by the class the backfill migration uses, so a restored
     * snapshot equals what the migration would make of the same data
     * (ADR-0072). A Category without a line is Running from the restore month.
     */
    private function restoreLegacyRhythms(User $targetUser): void
    {
        $yearByBudget = $targetUser->budgets()->pluck('year', 'id');

        $lines = array_map(fn (array $line): array => [
            'category_id' => $line['category_id'],
            'category_type' => $line['category_type'],
            'year' => (int) $yearByBudget[$line['budget_id']],
            'is_paced' => $line['is_paced'],
            'month_item_kind' => $line['month_item_kind'],
        ], $this->legacyFlagLines);

        $expenseCategoryIds = $targetUser->categories()
            ->where('type', CategoryType::Expense->value)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $since = CarbonImmutable::today('UTC');
        $entries = $expenseCategoryIds === []
            ? []
            : LegacyRhythmConversion::convert($lines, array_values($expenseCategoryIds), $since->year, $since->month);

        $now = now();

        foreach (array_chunk($entries, 500) as $chunk) {
            CategoryRhythm::query()->insert(array_map(fn (array $entry): array => [
                ...$entry,
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk));
        }
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

            $this->rememberLegacyFlags($budgetId, $listCategoryIds[$categoryData['id']], $categoryData);

            $category = BudgetLine::query()->create([
                'budget_id' => $budgetId,
                'category_id' => $listCategoryIds[$categoryData['id']],
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
            'description' => null,
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
                if (! isset($itemData['budget_line_id'], $itemData['approved_amount_minor_units'], $itemData['current_amount_minor_units'])) {
                    throw new UserSnapshotException('Snapshot month plan item is missing a required key.');
                }

                $categoryId = $categoryIdMap[$itemData['budget_line_id']] ?? null;

                if ($categoryId === null) {
                    throw new UserSnapshotException("Snapshot references unknown category id {$itemData['budget_line_id']}.");
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
        $budgetOfLine = BudgetLine::query()
            ->whereIn('id', array_values($categoryIdMap))
            ->pluck('budget_id', 'id')
            ->map(fn ($budgetId): int => (int) $budgetId);

        foreach ($operations as $operationData) {
            $accountId = $accountIdMap[$operationData['account_id']] ?? null;
            $categoryId = $categoryIdMap[$operationData['budget_line_id']] ?? null;
            $budgetId = $budgetIdMap[$operationData['budget_id']] ?? null;

            if ($accountId === null || $categoryId === null || $budgetId === null) {
                throw new UserSnapshotException('Snapshot references unknown financial operation relations.');
            }

            if ($budgetOfLine->get($categoryId) !== $budgetId) {
                throw new UserSnapshotException('Snapshot financial operation references a budget line of another budget.');
            }

            $rawStatementDescription = $operationData['raw_statement_description'] ?? null;
            $identity = $this->restoredImportIdentity->forLeg(
                $targetUser,
                $accountIdMap,
                [
                    'account_id' => $operationData['import_identity_account_id'] ?? null,
                    'date' => $operationData['import_identity_date'] ?? null,
                    'amount_minor_units' => $operationData['import_identity_amount_minor_units'] ?? null,
                    'digest' => $operationData['import_identity_digest'] ?? null,
                ],
                $rawStatementDescription,
                'financial operation '.($operationData['id'] ?? '?'),
            );

            $targetUser->financialOperations()->create([
                'type' => FinancialOperationType::from($operationData['type']),
                'account_id' => $accountId,
                'budget_line_id' => $categoryId,
                'budget_id' => $budgetId,
                'operation_date' => $operationData['operation_date'],
                'amount_minor_units' => $operationData['amount_minor_units'],
                'description' => $operationData['description'],
                'counterparty' => $operationData['counterparty'] ?? null,
                'raw_statement_description' => $rawStatementDescription,
                'import_identity_account_id' => $identity['account_id'],
                'import_identity_date' => $identity['date'],
                'import_identity_amount_minor_units' => $identity['amount_minor_units'],
                'import_identity_digest' => $identity['digest'],
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

            $sourceRaw = $transferData['source_raw_statement_description']
                ?? $transferData['raw_statement_description']
                ?? null;
            $destinationRaw = $transferData['destination_raw_statement_description'] ?? null;
            $sourceIdentity = $this->restoredImportIdentity->forLeg(
                $targetUser,
                $accountIdMap,
                [
                    'account_id' => $transferData['source_import_identity_account_id'] ?? null,
                    'date' => $transferData['source_import_identity_date'] ?? null,
                    'amount_minor_units' => $transferData['source_import_identity_amount_minor_units'] ?? null,
                    'digest' => $transferData['source_import_identity_digest'] ?? null,
                ],
                $sourceRaw,
                'own transfer '.($transferData['id'] ?? '?').' source',
            );
            $destinationIdentity = $this->restoredImportIdentity->forLeg(
                $targetUser,
                $accountIdMap,
                [
                    'account_id' => $transferData['destination_import_identity_account_id'] ?? null,
                    'date' => $transferData['destination_import_identity_date'] ?? null,
                    'amount_minor_units' => $transferData['destination_import_identity_amount_minor_units'] ?? null,
                    'digest' => $transferData['destination_import_identity_digest'] ?? null,
                ],
                $destinationRaw,
                'own transfer '.($transferData['id'] ?? '?').' destination',
            );

            $targetUser->ownTransfers()->create([
                'source_account_id' => $sourceAccountId,
                'destination_account_id' => $destinationAccountId,
                'transfer_date' => $transferData['transfer_date'],
                'amount_minor_units' => $transferData['amount_minor_units'],
                'description' => $transferData['description'],
                'source_raw_statement_description' => $sourceRaw,
                'destination_raw_statement_description' => $destinationRaw,
                'source_import_identity_account_id' => $sourceIdentity['account_id'],
                'source_import_identity_date' => $sourceIdentity['date'],
                'source_import_identity_amount_minor_units' => $sourceIdentity['amount_minor_units'],
                'source_import_identity_digest' => $sourceIdentity['digest'],
                'destination_import_identity_account_id' => $destinationIdentity['account_id'],
                'destination_import_identity_date' => $destinationIdentity['date'],
                'destination_import_identity_amount_minor_units' => $destinationIdentity['amount_minor_units'],
                'destination_import_identity_digest' => $destinationIdentity['digest'],
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

            $rawStatementDescription = $movementData['raw_statement_description'] ?? null;
            $identity = $this->restoredImportIdentity->forLeg(
                $targetUser,
                $accountIdMap,
                [
                    'account_id' => $movementData['import_identity_account_id'] ?? null,
                    'date' => $movementData['import_identity_date'] ?? null,
                    'amount_minor_units' => $movementData['import_identity_amount_minor_units'] ?? null,
                    'digest' => $movementData['import_identity_digest'] ?? null,
                ],
                $rawStatementDescription,
                'capital movement '.($movementData['id'] ?? '?'),
            );

            $targetUser->capitalMovements()->create([
                'account_id' => $accountId,
                'term_deposit_id' => $termDepositId,
                'movement_date' => $movementData['movement_date'],
                'amount_minor_units' => $movementData['amount_minor_units'],
                'direction' => CapitalMovementDirection::from($movementData['direction']),
                'description' => $movementData['description'],
                'raw_statement_description' => $rawStatementDescription,
                'import_identity_account_id' => $identity['account_id'],
                'import_identity_date' => $identity['date'],
                'import_identity_amount_minor_units' => $identity['amount_minor_units'],
                'import_identity_digest' => $identity['digest'],
                'created_at' => isset($movementData['created_at']) ? Carbon::parse($movementData['created_at']) : now(),
                'updated_at' => isset($movementData['updated_at']) ? Carbon::parse($movementData['updated_at']) : now(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $lineData  a Budget Line of a snapshot of version 1 to 8, with the `type` of its Category
     */
    private function rememberLegacyFlags(int $budgetId, int $categoryId, array $lineData): void
    {
        $this->legacyFlagLines[] = [
            'budget_id' => $budgetId,
            'category_id' => $categoryId,
            'category_type' => (string) $lineData['type'],
            'is_paced' => $this->restoredCategoryIsPaced($lineData),
            'month_item_kind' => $this->restoredCategoryMonthItemKind($lineData),
        ];
    }

    /**
     * Input of `LegacyRhythmConversion` for snapshots 1 to 8 only.
     *
     * @param  array<string, mixed>  $categoryData
     */
    private function restoredCategoryIsPaced(array $categoryData): bool
    {
        if ($categoryData['type'] !== CategoryType::Expense->value) {
            return true;
        }

        return (bool) ($categoryData['is_paced'] ?? true);
    }

    /**
     * Input of `LegacyRhythmConversion` for snapshots 1 to 8 only: `bill` or
     * `running`, the values of the removed Month Item Kind.
     *
     * @param  array<string, mixed>  $categoryData
     */
    private function restoredCategoryMonthItemKind(array $categoryData): string
    {
        if ($categoryData['type'] !== CategoryType::Expense->value) {
            return 'running';
        }

        return ($categoryData['month_item_kind'] ?? null) === 'bill' ? 'bill' : 'running';
    }
}

<?php

namespace App\Support\CategoryList;

use App\Enums\BudgetPhase;
use App\Enums\Rhythm;
use App\Models\Category;
use App\Support\CategoryRhythms;
use Carbon\CarbonImmutable;

/**
 * What merging one Category into another does (ADR-0076): the one reading that
 * both the preview and `MergeCategories` take their numbers from, so the two
 * cannot disagree.
 *
 * A fixed number of queries whatever the number of Budgets and operations: the
 * lines of both Categories with their Budget, the operations per line, the Month
 * Plan items of those lines, and the Rhythm and Collected of the two Categories.
 * "Today" is passed in, never read from the clock.
 *
 * @phpstan-import-type Item from CategoryMergeLoader
 *
 * @phpstan-type BudgetRow array{budget_id: int, year: int, phase: BudgetPhase, source_line_id: int, target_line_id: int|null, source_archived: bool, source_planned: int, source_initial: int, source_execution: int, target_planned: int, operations_count: int}
 */
final class CategoryMergeReading
{
    /**
     * @param  list<BudgetRow>  $budgets  oldest year first
     * @param  list<Item>  $sourceItems
     * @param  array{source: int, target: int|null, after: int|null}|null  $collected
     */
    private function __construct(
        private readonly Category $source,
        private readonly Category $target,
        private readonly CarbonImmutable $today,
        private readonly array $budgets,
        private readonly array $sourceItems,
        private readonly ?array $collected,
        private readonly bool $targetIsIrregular,
    ) {}

    public static function for(Category $source, Category $target, CarbonImmutable $today): self
    {
        $lines = CategoryMergeLoader::loadLines($source, $target);
        $operations = CategoryMergeLoader::operationCounts($lines);
        $targetLineByBudget = [];

        foreach ($lines as $line) {
            if ($line['category_id'] === $target->id) {
                $targetLineByBudget[$line['budget_id']] = $line;
            }
        }

        $budgets = [];

        foreach ($lines as $line) {
            if ($line['category_id'] !== $source->id) {
                continue;
            }

            $targetLine = $targetLineByBudget[$line['budget_id']] ?? null;

            $budgets[] = [
                'budget_id' => $line['budget_id'],
                'year' => $line['year'],
                'phase' => $line['phase'],
                'source_line_id' => $line['id'],
                'target_line_id' => $targetLine['id'] ?? null,
                'source_archived' => $line['archived'],
                'source_planned' => $line['planned'],
                'source_initial' => $line['initial'],
                'source_execution' => $line['execution'],
                'target_planned' => $targetLine['planned'] ?? 0,
                'operations_count' => $operations[$line['id']] ?? 0,
            ];
        }

        usort($budgets, fn (array $a, array $b): int => [$a['year'], $a['budget_id']] <=> [$b['year'], $b['budget_id']]);

        $rhythms = CategoryRhythms::forCategoryIds([$source->id, $target->id]);
        $targetIsIrregular = $rhythms->rhythmIn($target->id, $today->year, $today->month) === Rhythm::Irregular;

        return new self(
            $source,
            $target,
            $today,
            $budgets,
            CategoryMergeLoader::loadSourceItems($lines, $source, $target),
            CategoryMergeLoader::collected($source, $target, $today, $rhythms, $targetIsIrregular),
            $targetIsIrregular,
        );
    }

    /**
     * The payload of the preview.
     *
     * @return array{
     *     source: array{id: int, name: string, archived: bool},
     *     target: array{id: int, name: string},
     *     budgets: list<array{year: int, phase: string, operations_count: int, target_plan_before_minor_units: int, target_plan_after_minor_units: int}>,
     *     operations_count: int,
     *     month_plan_items: array{count: int, first: array{year: int, month: int}|null, last: array{year: int, month: int}|null},
     *     collected: array{source_minor_units: int, target_minor_units: int|null, after_minor_units: int|null, since: array{year: int, month: int}}|null,
     *     restored_budget_year: int|null,
     * }
     */
    public function toArray(): array
    {
        $months = array_map(fn (array $item): int => $item['year'] * 12 + $item['month'], $this->sourceItems);
        $bounds = fn (int $index): array => ['year' => intdiv($index - 1, 12), 'month' => ($index - 1) % 12 + 1];

        return [
            'source' => ['id' => $this->source->id, 'name' => $this->source->name, 'archived' => $this->source->isArchived()],
            'target' => ['id' => $this->target->id, 'name' => $this->target->name],
            'budgets' => array_map(fn (array $budget): array => [
                'year' => $budget['year'],
                'phase' => $budget['phase']->value,
                'operations_count' => $budget['operations_count'],
                'target_plan_before_minor_units' => $budget['target_planned'],
                'target_plan_after_minor_units' => $budget['target_planned'] + $budget['source_planned'],
            ], $this->budgets),
            'operations_count' => array_sum(array_column($this->budgets, 'operations_count')),
            'month_plan_items' => [
                'count' => count($this->sourceItems),
                'first' => $months === [] ? null : $bounds(min($months)),
                'last' => $months === [] ? null : $bounds(max($months)),
            ],
            'collected' => $this->collected === null ? null : [
                'source_minor_units' => $this->collected['source'],
                'target_minor_units' => $this->collected['target'],
                'after_minor_units' => $this->collected['after'],
                'since' => ['year' => $this->today->year, 'month' => $this->today->month],
            ],
            'restored_budget_year' => $this->restoredBudgetYear(),
        ];
    }

    /**
     * Budget Lines of the source that fold into the line of the target in the same Budget.
     *
     * @return list<array{budget_id: int, source_line_id: int, target_line_id: int, planned: int, initial: int, execution: int}>
     */
    public function linesToFold(): array
    {
        $folds = [];

        foreach ($this->budgets as $budget) {
            if ($budget['target_line_id'] === null) {
                continue;
            }

            $folds[] = [
                'budget_id' => $budget['budget_id'],
                'source_line_id' => $budget['source_line_id'],
                'target_line_id' => $budget['target_line_id'],
                'planned' => $budget['source_planned'],
                'initial' => $budget['source_initial'],
                'execution' => $budget['source_execution'],
            ];
        }

        return $folds;
    }

    /**
     * Ids of the source's Budget Lines in Budgets where the target has none.
     *
     * @return list<int>
     */
    public function lineIdsToRepoint(): array
    {
        $ids = [];

        foreach ($this->budgets as $budget) {
            if ($budget['target_line_id'] === null) {
                $ids[] = $budget['source_line_id'];
            }
        }

        return $ids;
    }

    /**
     * Re-pointed lines that stop being archived: an archived source merges as if
     * it had been restored first (ADR-0063 archives only the line of the Budget
     * in execution of the current year).
     *
     * @return list<int>
     */
    public function lineIdsToUnarchive(): array
    {
        $ids = [];

        foreach ($this->budgets as $budget) {
            if ($budget['target_line_id'] === null && $this->isRestoredLine($budget)) {
                $ids[] = $budget['source_line_id'];
            }
        }

        return $ids;
    }

    /**
     * Month Plan items of a folded source line in a plan where the target has no
     * item: they move to the target's line. Map item id => target line id.
     *
     * @return array<int, int>
     */
    public function itemsToMove(): array
    {
        $targetLine = [];

        foreach ($this->budgets as $budget) {
            if ($budget['target_line_id'] !== null) {
                $targetLine[$budget['source_line_id']] = $budget['target_line_id'];
            }
        }

        $moves = [];

        foreach ($this->sourceItems as $item) {
            if (isset($targetLine[$item['budget_line_id']]) && $item['target_item_id'] === null) {
                $moves[$item['id']] = $targetLine[$item['budget_line_id']];
            }
        }

        return $moves;
    }

    /**
     * Items of the source and of the target in one plan: the amounts of the source
     * item are added to the target item and the source item is deleted. Map
     * source item id => target item id and amounts.
     *
     * @return array<int, array{target_item_id: int, approved: int, current: int}>
     */
    public function itemsToFold(): array
    {
        $folds = [];

        foreach ($this->sourceItems as $item) {
            if ($item['target_item_id'] !== null) {
                $folds[$item['id']] = [
                    'target_item_id' => $item['target_item_id'],
                    'approved' => $item['approved'],
                    'current' => $item['current'],
                ];
            }
        }

        return $folds;
    }

    /**
     * The Opening Collected of the new period on the target; null when the
     * target is not Irregular this month and nothing is written.
     */
    public function openingCollectedMinorUnits(): ?int
    {
        return $this->targetIsIrregular ? ($this->collected['after'] ?? null) : null;
    }

    private function restoredBudgetYear(): ?int
    {
        foreach ($this->budgets as $budget) {
            if ($this->isRestoredLine($budget)) {
                return $budget['year'];
            }
        }

        return null;
    }

    /**
     * @param  BudgetRow  $budget
     */
    private function isRestoredLine(array $budget): bool
    {
        return $this->source->isArchived()
            && $budget['source_archived']
            && $budget['phase'] === BudgetPhase::Execution
            && $budget['year'] === $this->today->year;
    }
}

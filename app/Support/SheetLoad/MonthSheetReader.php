<?php

namespace App\Support\SheetLoad;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Reads one month sheet by labels, not by fixed rows: the layout shifts between years and inside January.
 */
final class MonthSheetReader
{
    private const FIRST_ROW = 3;

    public function read(Worksheet $worksheet, int $month, SheetLayout $layout = SheetLayout::Since2022): MonthSheet
    {
        $cells = new SheetCells($worksheet);

        $irregularRow = $this->requireLabel($cells, 'B', 'wydatki nieregularne', self::FIRST_ROW);
        $expenses = $this->entries($cells, 'B', 'D', self::FIRST_ROW, $irregularRow - 1);
        $irregularTotal = $this->entry($cells, 'B', 'D', $irregularRow);
        $balance = $this->entry($cells, 'B', 'D', $irregularRow + 1);

        $savingsRow = $this->requireLabel($cells, 'G', 'oszczędności', self::FIRST_ROW);
        $income = $this->entries($cells, 'G', $layout->incomeColumn(), self::FIRST_ROW, $savingsRow);
        $fund = $this->fundWithdrawal($cells, $savingsRow + 1, $layout);
        $daily = $this->dailyBreakdown($cells, $savingsRow + 1, $layout);

        [$irregularList, $medicalList, $examsList] = $this->lists($cells, $layout);

        $warnings = $this->warnings(
            $cells, $expenses, $irregularTotal, $balance, $fund, $daily,
            $irregularList, $medicalList, $examsList,
        );

        return new MonthSheet(
            $month, $cells->name(), $expenses, $irregularTotal, $balance, $income, $fund,
            $irregularList, $medicalList, $examsList, $daily, $warnings,
        );
    }

    /**
     * The irregular, treatment and exams lists. A 2020-2021 month may have no medical block: its irregular list then
     * ends at the first blank list row, and the treatment and exams lists are empty.
     *
     * @return array{0: list<SheetListItem>, 1: list<SheetListItem>, 2: list<SheetListItem>}
     */
    private function lists(SheetCells $cells, SheetLayout $layout): array
    {
        $column = $layout->categoryColumn();
        $categoryRow = $this->requireLabel($cells, $column, 'kategoria', 1);
        $healthRow = $layout->requiresMedicalBlock()
            ? $this->requireLabel($cells, $column, 'opieka zdrowotna', $categoryRow + 1)
            : $this->findLabel($cells, $column, 'opieka zdrowotna', $categoryRow + 1);

        if ($healthRow === null) {
            $irregular = $this->listItems($cells, $layout, $categoryRow + 1, $this->listEnd($cells, $layout, $categoryRow + 1), true);

            return [$irregular, [], []];
        }

        $treatmentRow = $this->requireLabel($cells, $column, $layout->treatmentLabel(), $healthRow + 1);
        $examsRow = $this->requireLabel($cells, $column, 'badania', $treatmentRow + 1);
        $medicalStart = $layout->requiresNameHeader()
            ? $this->requireLabel($cells, $layout->nameColumn(), 'nazwa', $treatmentRow + 1, $examsRow - 1) + 1
            : $treatmentRow + 1;
        $examsStart = $this->examsStart($cells, $layout, $examsRow);

        return [
            $this->listItems($cells, $layout, $categoryRow + 1, $healthRow - 1, true),
            $this->listItems($cells, $layout, $medicalStart, $examsRow - 1, false),
            $this->listItems($cells, $layout, $examsStart, $this->listEnd($cells, $layout, $examsStart), false),
        ];
    }

    private function findLabel(SheetCells $cells, string $column, string $label, int $from): ?int
    {
        for ($row = $from; $row <= $cells->highestRow(); $row++) {
            if ($cells->label($column, $row) === $label) {
                return $row;
            }
        }

        return null;
    }

    private function requireLabel(SheetCells $cells, string $column, string $label, int $from, ?int $to = null): int
    {
        $to ??= $cells->highestRow();

        for ($row = $from; $row <= $to; $row++) {
            if ($cells->label($column, $row) === $label) {
                return $row;
            }
        }

        throw new SheetReadException(
            $cells->rangeAddress($column, $from, max($from, $to)),
            'brak etykiety „'.$label.'”',
        );
    }

    private function entry(SheetCells $cells, string $labelColumn, string $amountColumn, int $row): SheetEntry
    {
        $raw = $cells->text($labelColumn, $row) ?? '';

        return new SheetEntry(
            SheetCells::normalizeLabel($raw),
            $raw,
            $cells->amount($amountColumn, $row),
            $cells->address($amountColumn, $row),
        );
    }

    /**
     * @return list<SheetEntry>
     */
    private function entries(SheetCells $cells, string $labelColumn, string $amountColumn, int $from, int $to): array
    {
        $entries = [];

        for ($row = $from; $row <= $to; $row++) {
            if (! $cells->isEmpty($labelColumn, $row)) {
                $entries[] = $this->entry($cells, $labelColumn, $amountColumn, $row);
            }
        }

        return $entries;
    }

    private function fundWithdrawal(SheetCells $cells, int $from, SheetLayout $layout): int
    {
        $sum = 0;

        for ($row = $from; $row <= $cells->highestRow(); $row++) {
            if ($cells->label('G', $row) === 'wydatki z funduszu' || $cells->label('H', $row) === 'wydatki z funduszu') {
                $sum += $cells->amount($layout->incomeColumn(), $row);
            }
        }

        return $sum;
    }

    /**
     * The breakdown under G "Codzienne wydatki" ends before an empty G, the "Do kiedy wydatki" row (a date in I) or,
     * in 2020, the "Na tydzień" row.
     *
     * @return list<SheetEntry>|null
     */
    private function dailyBreakdown(SheetCells $cells, int $from, SheetLayout $layout): ?array
    {
        for ($row = $from; $row <= $cells->highestRow(); $row++) {
            if (! in_array($cells->label('G', $row), ['codzienne', 'codzienne wydatki'], true)) {
                continue;
            }

            $items = [];

            for ($item = $row + 1; $item <= $cells->highestRow() && ! $cells->isEmpty('G', $item) && ! in_array($cells->label('G', $item), ['do kiedy wydatki', 'na tydzień'], true); $item++) {
                $items[] = $this->entry($cells, 'G', $layout->incomeColumn(), $item);
            }

            return $items;
        }

        return null;
    }

    /**
     * The exams list may carry the same header as the medical list; it is not an item.
     */
    private function examsStart(SheetCells $cells, SheetLayout $layout, int $examsRow): int
    {
        return $cells->label($layout->nameColumn(), $examsRow + 1) === 'nazwa' ? $examsRow + 2 : $examsRow + 1;
    }

    /**
     * A list ends before the first row empty in all four list columns; lower lists are not its items.
     */
    private function listEnd(SheetCells $cells, SheetLayout $layout, int $from): int
    {
        $row = $from;

        while ($row <= $cells->highestRow() && ! $this->isBlankListRow($cells, $layout, $row)) {
            $row++;
        }

        return $row - 1;
    }

    private function isBlankListRow(SheetCells $cells, SheetLayout $layout, int $row): bool
    {
        foreach ($layout->listColumns() as $column) {
            if (! $cells->isEmpty($column, $row)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Rows without name, plan and expense and rows with dashes in the name or the expense (anywhere in the list) are not items.
     *
     * @return list<SheetListItem>
     */
    private function listItems(SheetCells $cells, SheetLayout $layout, int $from, int $to, bool $withCategory): array
    {
        $items = [];
        [$categoryColumn, $nameColumn, $planColumn, $spentColumn] = $layout->listColumns();

        for ($row = $from; $row <= $to; $row++) {
            if ($cells->isEmpty($nameColumn, $row) && $cells->isEmpty($planColumn, $row) && $cells->isEmpty($spentColumn, $row)) {
                continue;
            }

            if ($cells->isDashes($nameColumn, $row) || $cells->isDashes($spentColumn, $row)) {
                continue;
            }

            $rawCategory = $withCategory ? $cells->text($categoryColumn, $row) : null;
            $category = $rawCategory === null ? null : SheetCells::normalizeLabel($rawCategory);

            $items[] = new SheetListItem(
                $category === '' || $category === '-' ? null : $category,
                $rawCategory,
                $cells->text($nameColumn, $row),
                $cells->amount($spentColumn, $row),
                $cells->address($spentColumn, $row),
            );
        }

        return $items;
    }

    /**
     * @param  list<SheetEntry>  $expenses
     * @param  list<SheetEntry>|null  $daily
     * @param  list<SheetListItem>  $irregularList
     * @param  list<SheetListItem>  $medicalList
     * @param  list<SheetListItem>  $examsList
     * @return list<SheetWarning>
     */
    private function warnings(
        SheetCells $cells,
        array $expenses,
        SheetEntry $irregularTotal,
        SheetEntry $balance,
        int $fund,
        ?array $daily,
        array $irregularList,
        array $medicalList,
        array $examsList,
    ): array {
        $warnings = [];

        $this->compare($warnings, $irregularTotal, $this->sum($irregularList) - $fund, 'suma nieregularnych', 'lista (po odjęciu wydatków z funduszu)');

        foreach ($expenses as $expense) {
            if (in_array($expense->label, MonthSheet::TREATMENT_ROWS, true)) {
                $this->compare($warnings, $expense, $this->sum($medicalList), $expense->label, 'lista leczenia');
            } elseif ($expense->label === 'badania') {
                $this->compare($warnings, $expense, $this->sum($examsList), 'badania', 'lista badań');
            } elseif ($daily !== null && str_starts_with($expense->label, 'codzienne')) {
                $this->compare($warnings, $expense, array_sum(array_map(fn (SheetEntry $e): int => $e->amountMinorUnits, $daily)), 'codzienne wydatki', 'rozpisanie');
            }
        }

        if ($balance->amountMinorUnits !== 0) {
            $warnings[] = new SheetWarning($balance->address, 'bilans miesiąca '.$balance->amountMinorUnits.' gr zamiast 0');
        }

        return $warnings;
    }

    /**
     * @param  list<SheetWarning>  $warnings
     */
    private function compare(array &$warnings, SheetEntry $block, int $listSum, string $blockName, string $listName): void
    {
        if ($block->amountMinorUnits !== $listSum) {
            $warnings[] = new SheetWarning(
                $block->address,
                $blockName.' '.$block->amountMinorUnits.' gr różni się od: '.$listName.' '.$listSum.' gr',
            );
        }
    }

    /**
     * @param  list<SheetListItem>  $items
     */
    private function sum(array $items): int
    {
        return array_sum(array_map(fn (SheetListItem $item): int => $item->amountMinorUnits, $items));
    }
}

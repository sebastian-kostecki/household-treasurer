<?php

namespace App\Support\SheetLoad;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Reads one month sheet by labels, not by fixed rows: the layout shifts between years and inside January.
 */
final class MonthSheetReader
{
    private const FIRST_ROW = 3;

    public function read(Worksheet $worksheet, int $month): MonthSheet
    {
        $cells = new SheetCells($worksheet);

        $irregularRow = $this->requireLabel($cells, 'B', 'wydatki nieregularne', self::FIRST_ROW);
        $expenses = $this->entries($cells, 'B', 'D', self::FIRST_ROW, $irregularRow - 1);
        $irregularTotal = $this->entry($cells, 'B', 'D', $irregularRow);
        $balance = $this->entry($cells, 'B', 'D', $irregularRow + 1);

        $savingsRow = $this->requireLabel($cells, 'G', 'oszczędności', self::FIRST_ROW);
        $income = $this->entries($cells, 'G', 'I', self::FIRST_ROW, $savingsRow);
        $fund = $this->fundWithdrawal($cells, $savingsRow + 1);
        $daily = $this->dailyBreakdown($cells, $savingsRow + 1);

        $categoryRow = $this->requireLabel($cells, 'J', 'kategoria', 1);
        $healthRow = $this->requireLabel($cells, 'J', 'opieka zdrowotna', $categoryRow + 1);
        $treatmentRow = $this->requireLabel($cells, 'J', 'leczenie prywatne', $healthRow + 1);
        $examsRow = $this->requireLabel($cells, 'J', 'badania', $treatmentRow + 1);
        $headerRow = $this->requireLabel($cells, 'K', 'nazwa', $treatmentRow + 1, $examsRow - 1);

        $irregularList = $this->listItems($cells, $categoryRow + 1, $healthRow - 1, true);
        $medicalList = $this->listItems($cells, $headerRow + 1, $examsRow - 1, false);
        $examsList = $this->listItems($cells, $this->examsStart($cells, $examsRow), $this->examsEnd($cells, $examsRow), false);

        $warnings = $this->warnings(
            $cells, $expenses, $irregularTotal, $balance, $fund, $daily,
            $irregularList, $medicalList, $examsList,
        );

        return new MonthSheet(
            $month, $cells->name(), $expenses, $irregularTotal, $balance, $income, $fund,
            $irregularList, $medicalList, $examsList, $daily, $warnings,
        );
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

    private function fundWithdrawal(SheetCells $cells, int $from): int
    {
        $sum = 0;

        for ($row = $from; $row <= $cells->highestRow(); $row++) {
            if ($cells->label('G', $row) === 'wydatki z funduszu' || $cells->label('H', $row) === 'wydatki z funduszu') {
                $sum += $cells->amount('I', $row);
            }
        }

        return $sum;
    }

    /**
     * The 2022 breakdown under G "Codzienne wydatki" ends before an empty G or the "Do kiedy wydatki" row (a date in I).
     *
     * @return list<SheetEntry>|null
     */
    private function dailyBreakdown(SheetCells $cells, int $from): ?array
    {
        for ($row = $from; $row <= $cells->highestRow(); $row++) {
            if (! in_array($cells->label('G', $row), ['codzienne', 'codzienne wydatki'], true)) {
                continue;
            }

            $items = [];

            for ($item = $row + 1; $item <= $cells->highestRow() && ! $cells->isEmpty('G', $item) && $cells->label('G', $item) !== 'do kiedy wydatki'; $item++) {
                $items[] = $this->entry($cells, 'G', 'I', $item);
            }

            return $items;
        }

        return null;
    }

    /**
     * The exams list may carry the same header as the medical list; it is not an item.
     */
    private function examsStart(SheetCells $cells, int $examsRow): int
    {
        return $cells->label('K', $examsRow + 1) === 'nazwa' ? $examsRow + 2 : $examsRow + 1;
    }

    /**
     * The exams list ends before the first row empty in J to M.
     */
    private function examsEnd(SheetCells $cells, int $examsRow): int
    {
        $row = $this->examsStart($cells, $examsRow);

        while ($row <= $cells->highestRow() && ! $this->isBlankListRow($cells, $row)) {
            $row++;
        }

        return $row - 1;
    }

    private function isBlankListRow(SheetCells $cells, int $row): bool
    {
        foreach (['J', 'K', 'L', 'M'] as $column) {
            if (! $cells->isEmpty($column, $row)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Rows without K, L and M and rows with dashes in K or M (anywhere in the list) are not items.
     *
     * @return list<SheetListItem>
     */
    private function listItems(SheetCells $cells, int $from, int $to, bool $withCategory): array
    {
        $items = [];

        for ($row = $from; $row <= $to; $row++) {
            if ($cells->isEmpty('K', $row) && $cells->isEmpty('L', $row) && $cells->isEmpty('M', $row)) {
                continue;
            }

            if ($cells->isDashes('K', $row) || $cells->isDashes('M', $row)) {
                continue;
            }

            $rawCategory = $withCategory ? $cells->text('J', $row) : null;
            $category = $rawCategory === null ? null : SheetCells::normalizeLabel($rawCategory);

            $items[] = new SheetListItem(
                $category === '' || $category === '-' ? null : $category,
                $rawCategory,
                $cells->text('K', $row),
                $cells->amount('M', $row),
                $cells->address('M', $row),
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
            if ($expense->label === 'leczenie prywatne') {
                $this->compare($warnings, $expense, $this->sum($medicalList), 'leczenie prywatne', 'lista leczenia');
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

<?php

namespace App\Support\SheetLoad;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Reads one month sheet of the 2019 workbook (`<month>_19`). The year has three variants, told apart by the
 * "Przychody" and "Wydatki" labels in column A: A (January-February), B (March-June, two rows higher) and C (July-December,
 * labels in B, plan in C, actual in D). Expense rows carry the actual amount; the personal and "Dom i akcesoria" lists
 * are bound to the row they belong to (`MonthSheet::$rowLists`).
 */
final class MonthSheet2019Reader
{
    /** Header rows of "Przychody" and "Wydatki" by variant. */
    private const array VARIANTS = ['A' => [5, 10], 'B' => [3, 8], 'C' => [2, 7]];

    /** Rows of variants A and B whose amount is the list sum in B while the actual in C stays empty. */
    private const array LIST_ROWS = ['akcesoria do domu', 'osobiste: jana', 'osobiste: sebastian', 'osobiste', 'cele: sebastian'];

    /** The header that ends the personal lists of variants A and B. */
    private const string GOALS_HEADER = 'cele (odkładane przez sebastiana)';

    private const int DESCRIPTION_LIMIT = 255;

    public function read(Worksheet $worksheet, int $month): MonthSheet
    {
        $cells = new SheetCells($worksheet);
        [$variant, $incomeRow, $expenseRow] = $this->variant($cells);
        $isNewTemplate = $variant === 'C';

        $income = $this->income($cells, $incomeRow, $expenseRow, $isNewTemplate ? 'C' : 'B');
        $expenses = $isNewTemplate ? $this->newTemplateExpenses($cells, $expenseRow) : $this->expenses($cells, $expenseRow);
        $lists = $isNewTemplate ? $this->newTemplateLists($cells) : $this->lists($cells);
        $rowLists = $this->bindLists($expenses, $lists);
        $daily = $isNewTemplate ? $this->dailyBreakdown($cells) : null;

        $headerAddress = $cells->address('A', $expenseRow);

        return new MonthSheet(
            $month,
            $cells->name(),
            $expenses,
            new SheetEntry('wydatki nieregularne', 'wydatki nieregularne', 0, $headerAddress),
            new SheetEntry('bilans', 'bilans', 0, $headerAddress),
            $income,
            0,
            [],
            [],
            [],
            $daily,
            $this->warnings($expenses, $rowLists, $daily),
            $rowLists,
        );
    }

    /**
     * @return array{0: string, 1: int, 2: int} variant, "Przychody" row, "Wydatki" row
     */
    private function variant(SheetCells $cells): array
    {
        foreach (self::VARIANTS as $variant => [$incomeRow, $expenseRow]) {
            if ($cells->label('A', $incomeRow) === 'przychody' && $cells->label('A', $expenseRow) === 'wydatki') {
                return [$variant, $incomeRow, $expenseRow];
            }
        }

        throw new SheetReadException(
            $cells->rangeAddress('A', 2, 10),
            'nieznany wariant arkusza miesiąca 2019 (oczekiwane „Przychody” i „Wydatki” w A5 i A10, A3 i A8 albo A2 i A7)',
        );
    }

    /**
     * @return list<SheetEntry>
     */
    private function income(SheetCells $cells, int $incomeRow, int $expenseRow, string $amountColumn): array
    {
        $income = [];

        for ($row = $incomeRow + 1; $row < $expenseRow; $row++) {
            if (! $cells->isEmpty('A', $row)) {
                $income[] = $cells->entry('A', $amountColumn, $row);
            }
        }

        return $income;
    }

    /**
     * Variants A and B: labels in A down to the last labelled row, the actual in C. A list row without an actual takes
     * the list sum in B; any other row with an empty C and a non-zero B holds only the plan and is refused.
     *
     * @return list<SheetEntry>
     */
    private function expenses(SheetCells $cells, int $expenseRow): array
    {
        $last = $expenseRow;

        for ($row = $expenseRow + 1; $row <= $cells->highestRow(); $row++) {
            if (! $cells->isEmpty('A', $row)) {
                $last = $row;
            }
        }

        $expenses = [];

        for ($row = $expenseRow + 1; $row <= $last; $row++) {
            if ($cells->isEmpty('A', $row)) {
                $this->requireNoAmount($cells, $row, ['B', 'C']);

                continue;
            }

            $expenses[] = $this->expenseEntry($cells, $row);
        }

        return $expenses;
    }

    private function expenseEntry(SheetCells $cells, int $row): SheetEntry
    {
        $label = (string) $cells->label('A', $row);

        if (! $cells->isEmpty('C', $row)) {
            return $cells->entry('A', 'C', $row);
        }

        if (in_array($label, self::LIST_ROWS, true)) {
            return $cells->entry('A', 'B', $row);
        }

        if ($cells->amount('B', $row) !== 0) {
            throw new SheetReadException($cells->address('B', $row), 'wiersz „'.$cells->text('A', $row).'” ma tylko plan (B) i puste wykonanie (C)');
        }

        return $cells->entry('A', 'C', $row);
    }

    /**
     * @param  list<string>  $columns
     */
    private function requireNoAmount(SheetCells $cells, int $row, array $columns): void
    {
        foreach ($columns as $column) {
            if ($cells->amount($column, $row) !== 0) {
                throw new SheetReadException($cells->address($column, $row), 'kwota w wierszu bez etykiety');
            }
        }
    }

    /**
     * Variant C: item labels in B between "Wydatki" and "Pozostałe", the actual in D. A row without a label must not
     * hold an amount in C or D.
     *
     * @return list<SheetEntry>
     */
    private function newTemplateExpenses(SheetCells $cells, int $expenseRow): array
    {
        $balanceRow = $cells->requireLabel('B', 'pozostałe', $expenseRow + 1);
        $expenses = [];

        for ($row = $expenseRow + 1; $row < $balanceRow; $row++) {
            if ($cells->isEmpty('B', $row)) {
                $this->requireNoAmount($cells, $row, ['C', 'D']);

                continue;
            }

            $expenses[] = $cells->entry('B', 'D', $row);
        }

        return $expenses;
    }

    /**
     * Variants A and B: "Wydatki osobiste" (Sebastian F/G, Jana H/I) down to "Cele (odkładane przez Sebastiana)", then
     * "Dom i akcesoria" in F/I.
     *
     * @return array{sebastian: list<SheetListItem>, jana: list<SheetListItem>, home: list<SheetListItem>}
     */
    private function lists(SheetCells $cells): array
    {
        $names = $cells->requireLabel('F', 'wydatki osobiste', 1) + 1;
        $this->requireName($cells, 'F', $names, 'sebastian');
        $this->requireName($cells, 'H', $names, 'jana');

        $end = $cells->requireLabel('F', self::GOALS_HEADER, $names + 1);
        $home = $cells->requireLabel('F', 'dom i akcesoria', $end + 1);

        return [
            'sebastian' => $this->items($cells, 'F', 'G', $names + 1, $end - 1, 'Sebastian'),
            'jana' => $this->items($cells, 'H', 'I', $names + 1, $end - 1, 'Jana'),
            'home' => $this->items($cells, 'F', 'I', $home + 1, $cells->highestRow(), null),
        ];
    }

    /**
     * Variant C: "Wydatki osobiste" in G (Sebastian G, Jana I or J) down to "Cele do odłożenia", then "Dom i akcesoria" in G.
     * From October the lists have a "plan:" / "wydatek:" header and the amount is the "wydatek:" column.
     *
     * @return array{sebastian: list<SheetListItem>, jana: list<SheetListItem>, home: list<SheetListItem>}
     */
    private function newTemplateLists(SheetCells $cells): array
    {
        $names = $cells->requireLabel('G', 'wydatki osobiste', 1) + 1;
        $split = $cells->label('H', $names + 1) === 'plan:';
        [$sebastianAmount, $janaName, $janaAmount, $homeAmount] = $split ? ['I', 'J', 'L', 'L'] : ['H', 'I', 'J', 'J'];

        $this->requireName($cells, 'G', $names, 'sebastian');
        $this->requireName($cells, $janaName, $names, 'jana');

        $first = $names + ($split ? 2 : 1);
        $end = $cells->requireLabel('G', 'cele do odłożenia', $first);
        $home = $cells->requireLabel('G', 'dom i akcesoria', $end + 1);

        return [
            'sebastian' => $this->items($cells, 'G', $sebastianAmount, $first, $end - 1, 'Sebastian'),
            'jana' => $this->items($cells, $janaName, $janaAmount, $first, $end - 1, 'Jana'),
            'home' => $this->items($cells, 'G', $homeAmount, $home + 1, $cells->highestRow(), null),
        ];
    }

    /**
     * Rows without an amount (empty cell or dashes) are not items. A personal item carries the person in its name.
     *
     * @return list<SheetListItem>
     */
    private function items(SheetCells $cells, string $nameColumn, string $amountColumn, int $from, int $to, ?string $person): array
    {
        $items = [];

        for ($row = $from; $row <= $to; $row++) {
            if ($cells->isEmpty($amountColumn, $row) || $cells->isDashes($amountColumn, $row) || $cells->isDashes($nameColumn, $row)) {
                continue;
            }

            $items[] = new SheetListItem(
                null,
                null,
                $this->itemName($cells->text($nameColumn, $row), $person),
                $cells->amount($amountColumn, $row),
                $cells->address($amountColumn, $row),
            );
        }

        return $items;
    }

    private function itemName(?string $name, ?string $person): ?string
    {
        if ($person === null) {
            return $name;
        }

        $suffix = ' ('.$person.')';

        return $name === null ? $person : mb_substr($name, 0, self::DESCRIPTION_LIMIT - mb_strlen($suffix)).$suffix;
    }

    /**
     * Personal lists bind to "osobiste: Jana" / "osobiste: Sebastian", or both to the single "osobiste" row of June and
     * July-December; "Dom i akcesoria" to "akcesoria do domu". A non-empty list without its row, or bound to a second
     * row, is refused.
     *
     * @param  list<SheetEntry>  $expenses
     * @param  array{sebastian: list<SheetListItem>, jana: list<SheetListItem>, home: list<SheetListItem>}  $lists
     * @return array<string, list<SheetListItem>>
     */
    private function bindLists(array $expenses, array $lists): array
    {
        $targets = [
            'osobiste: sebastian' => ['sebastian'],
            'osobiste: jana' => ['jana'],
            'osobiste' => ['sebastian', 'jana'],
            'akcesoria do domu' => ['home'],
        ];
        $rowLists = [];
        $bound = [];

        foreach ($expenses as $entry) {
            foreach ($targets[$entry->label] ?? [] as $list) {
                if (isset($bound[$list]) && $lists[$list] !== []) {
                    throw new SheetReadException($entry->address, 'lista „'.$list.'” należy już do wiersza '.$bound[$list]);
                }

                $bound[$list] = $entry->address;
                $rowLists[$entry->address] = [...($rowLists[$entry->address] ?? []), ...$lists[$list]];
            }
        }

        foreach ($lists as $list => $items) {
            if ($items !== [] && ! isset($bound[$list])) {
                throw new SheetReadException($items[0]->address, 'lista „'.$list.'” nie ma wiersza wydatku, do którego należy');
            }
        }

        return array_filter($rowLists, fn (array $items): bool => $items !== []);
    }

    /**
     * November and December break "Codzienne wydatki" down in M12:O16 (label M, amount O).
     *
     * @return list<SheetEntry>|null
     */
    private function dailyBreakdown(SheetCells $cells): ?array
    {
        if ($cells->label('M', 12) !== 'codzienne wydatki') {
            return null;
        }

        $items = [];

        for ($row = 13; $row <= $cells->highestRow() && ! $cells->isEmpty('M', $row); $row++) {
            $items[] = $cells->entry('M', 'O', $row);
        }

        return $items;
    }

    /**
     * @param  list<SheetEntry>  $expenses
     * @param  array<string, list<SheetListItem>>  $rowLists
     * @param  list<SheetEntry>|null  $daily
     * @return list<SheetWarning>
     */
    private function warnings(array $expenses, array $rowLists, ?array $daily): array
    {
        $warnings = [];

        foreach ($expenses as $expense) {
            if (isset($rowLists[$expense->address])) {
                $this->compare($warnings, $expense, $rowLists[$expense->address], $expense->label, 'lista wiersza');
            } elseif ($daily !== null && str_starts_with($expense->label, 'codzienne')) {
                $this->compare($warnings, $expense, $daily, 'codzienne wydatki', 'rozpisanie');
            }
        }

        return $warnings;
    }

    /**
     * @param  list<SheetWarning>  $warnings
     * @param  list<SheetListItem|SheetEntry>  $parts
     */
    private function compare(array &$warnings, SheetEntry $block, array $parts, string $blockName, string $listName): void
    {
        $sum = array_sum(array_map(fn (SheetListItem|SheetEntry $part): int => $part->amountMinorUnits, $parts));

        if ($block->amountMinorUnits !== $sum) {
            $warnings[] = new SheetWarning($block->address, $blockName.' '.$block->amountMinorUnits.' gr różni się od: '.$listName.' '.$sum.' gr');
        }
    }

    private function requireName(SheetCells $cells, string $column, int $row, string $name): void
    {
        if ($cells->label($column, $row) !== $name) {
            throw new SheetReadException($cells->address($column, $row), 'oczekiwany nagłówek listy „'.$name.'”');
        }
    }
}

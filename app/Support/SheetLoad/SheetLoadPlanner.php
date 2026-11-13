<?php

namespace App\Support\SheetLoad;

use App\Enums\FinancialOperationType;
use App\Support\MinorUnits;
use DateTimeImmutable;

/**
 * Turns a BudgetSheet and the accepted mapping into the operations of one year, without touching the database.
 * It refuses (SheetLoadException) on an unmapped label, an override above its cell, a non-positive yearly income
 * source and on totals that differ from `Analiza!B16` / `C16` by even one grosz. Cells mapped to
 * `SheetMapping::FUND` make no operation: they are fund movements, which the reconciliation adds to the operations.
 *
 * @phpstan-type ListedItem array{name: ?string, amount: int, address: string, target: int|string}
 * @phpstan-type IncomeCell array{month: int, name: string, entry: SheetEntry, amount: int}
 */
final class SheetLoadPlanner
{
    private const int DESCRIPTION_LIMIT = 255;

    public function __construct(private readonly SheetMapping $mapping) {}

    public function plan(BudgetSheet $sheet): SheetLoadPlan
    {
        $remainders = [];
        $transfers = [];
        $operations = [];
        $fundMovements = [];

        foreach ($sheet->months as $month) {
            array_push($operations, ...$this->expenseOperations($sheet->year, $month, $remainders, $fundMovements));
        }

        array_push($operations, ...$this->incomeOperations($sheet, $transfers, $fundMovements));

        $plan = new SheetLoadPlan(
            $sheet->year, $operations, $sheet->warnings(), $transfers, $remainders,
            $sheet->analysisIncome, $sheet->analysisExpense, $fundMovements,
        );

        $this->assertTotal($plan->incomeTotal(), $plan->fundIncomeTotal(), $sheet->analysisIncome, 'przychody');
        $this->assertTotal($plan->expenseTotal(), $plan->fundExpenseTotal(), $sheet->analysisExpense, 'wydatki');

        return $plan;
    }

    /**
     * @param  list<SheetWarning>  $remainders
     * @param  list<FundMovement>  $fundMovements
     * @return list<PlannedOperation>
     */
    private function expenseOperations(int $year, MonthSheet $month, array &$remainders, array &$fundMovements): array
    {
        $date = $this->lastDay($year, $month->month);
        $operations = [];

        foreach ($month->expenses as $entry) {
            $target = $this->mapping->expense($entry->label) ?? $this->unknownLabel($entry->address, $entry->rawLabel);

            if ($target === SheetMapping::FUND) {
                $this->recordFund($fundMovements, $entry, $entry->amountMinorUnits, FinancialOperationType::Expense);

                continue;
            }

            $block = $this->listedBlock($year, $month, $entry);

            if ($block === null) {
                $operations[] = $this->rowOperation($year, $date, FinancialOperationType::Expense, $target, $entry);

                continue;
            }

            array_push($operations, ...$this->listedOperations($year, $month, $date, $entry, $block, $remainders));
        }

        array_push($operations, ...$this->irregularOperations($year, $month, $date, $remainders));

        return array_values(array_filter($operations, fn (?PlannedOperation $operation): bool => $operation !== null && $operation->amountMinorUnits !== 0));
    }

    /**
     * The list that replaces the row of a block: medical, exams or (2022) the breakdown of Codzienne wydatki.
     *
     * @return array{name: string, remainder: 'medical'|'daily', items: list<array{name: ?string, amount: int, address: string, target: int|string}>}|null
     */
    private function listedBlock(int $year, MonthSheet $month, SheetEntry $entry): ?array
    {
        if (in_array($entry->label, MonthSheet::TREATMENT_ROWS, true)) {
            return ['name' => $entry->label, 'remainder' => 'medical', 'items' => $this->listItems($month->medicalList, $entry)];
        }

        if ($entry->label === 'badania') {
            return ['name' => 'badania', 'remainder' => 'medical', 'items' => $this->listItems($month->examsList, $entry)];
        }

        if ($month->dailyBreakdown !== null && str_starts_with($entry->label, 'codzienne')) {
            $items = array_map(fn (SheetEntry $item): array => [
                'name' => $item->rawLabel.' (arkusz '.$year.')',
                'amount' => $item->amountMinorUnits,
                'address' => $item->address,
                'target' => $this->mapping->dailyBreakdown($item->label) ?? $this->unknownLabel($item->address, $item->rawLabel),
            ], $month->dailyBreakdown);

            return ['name' => 'codzienne wydatki', 'remainder' => 'daily', 'items' => $items];
        }

        return null;
    }

    /**
     * @param  list<SheetListItem>  $list
     * @return list<array{name: ?string, amount: int, address: string, target: int|string}>
     */
    private function listItems(array $list, SheetEntry $row): array
    {
        $target = $this->mapping->expense($row->label) ?? $this->unknownLabel($row->address, $row->rawLabel);

        return array_map(fn (SheetListItem $item): array => [
            'name' => $item->name,
            'amount' => $item->amountMinorUnits,
            'address' => $item->address,
            'target' => $target,
        ], $list);
    }

    /**
     * @param  array{name: string, remainder: 'medical'|'daily', items: list<array{name: ?string, amount: int, address: string, target: int|string}>}  $block
     * @param  list<SheetWarning>  $remainders
     * @return list<PlannedOperation>
     */
    private function listedOperations(int $year, MonthSheet $month, string $date, SheetEntry $row, array $block, array &$remainders): array
    {
        $operations = $this->itemOperations($year, $date, $block['items']);
        $listSum = array_sum(array_column($block['items'], 'amount'));

        if ($row->amountMinorUnits !== $listSum) {
            $operations[] = $this->remainderOperation($year, $month, $row, $block['name'], $this->mapping->remainder($block['remainder']), $row->amountMinorUnits - $listSum, $remainders);
        }

        return $operations;
    }

    /**
     * @param  list<SheetWarning>  $remainders
     * @return list<PlannedOperation>
     */
    private function irregularOperations(int $year, MonthSheet $month, string $date, array &$remainders): array
    {
        $items = array_map(fn (SheetListItem $item): array => [
            'name' => $item->name,
            'amount' => $item->amountMinorUnits,
            'address' => $item->address,
            'target' => $this->mapping->irregularCategory($item->category) ?? $this->unknownLabel($item->address, $item->rawCategory ?? ''),
        ], $month->irregularList);

        $operations = $this->itemOperations($year, $date, $items);
        $listSum = array_sum(array_column($items, 'amount'));
        $total = $month->irregularTotal;

        if ($total->amountMinorUnits !== $listSum) {
            $operations[] = $this->remainderOperation($year, $month, $total, 'nieregularne', $this->mapping->remainder('irregular'), $total->amountMinorUnits - $listSum, $remainders);
        }

        return $operations;
    }

    /**
     * @param  list<array{name: ?string, amount: int, address: string, target: int|string}>  $items
     * @return list<PlannedOperation>
     */
    private function itemOperations(int $year, string $date, array $items): array
    {
        $operations = [];

        foreach ($items as $item) {
            if ($item['target'] === SheetMapping::FUND) {
                throw new SheetLoadException($item['address'], 'cel ruch funduszu jest dozwolony tylko dla wierszy wydatków i przychodów');
            }

            if ($item['target'] === SheetMapping::SKIP) {
                continue;
            }

            $description = $item['name'] === null
                ? 'bez nazwy (arkusz '.$year.')'
                : mb_substr($item['name'], 0, self::DESCRIPTION_LIMIT);

            $operations[] = new PlannedOperation($date, FinancialOperationType::Expense, $item['target'], $item['amount'], $description, $item['address']);
        }

        return $operations;
    }

    /**
     * @param  list<SheetWarning>  $remainders
     */
    private function remainderOperation(int $year, MonthSheet $month, SheetEntry $block, string $blockName, int $category, int $amount, array &$remainders): PlannedOperation
    {
        $description = 'reszta bez rozpisania: '.$blockName.' (arkusz '.$year.', '.$month->name.')';
        $remainders[] = new SheetWarning($block->address, $description.': '.MinorUnits::toMajorDisplay($amount).' zł');

        return new PlannedOperation($this->lastDay($year, $month->month), FinancialOperationType::Expense, $category, $amount, mb_substr($description, 0, self::DESCRIPTION_LIMIT), $block->address);
    }

    private function rowOperation(int $year, string $date, FinancialOperationType $type, int|string $target, SheetEntry $entry, ?int $amount = null): ?PlannedOperation
    {
        if ($target === SheetMapping::SKIP) {
            return null;
        }

        $suffix = ' (arkusz '.$year.')';
        $description = mb_substr(rtrim($entry->rawLabel, "* \t"), 0, self::DESCRIPTION_LIMIT - mb_strlen($suffix)).$suffix;

        return new PlannedOperation($date, $type, $target, $amount ?? $entry->amountMinorUnits, $description, $entry->address);
    }

    /**
     * @param  list<SheetWarning>  $transfers
     * @param  list<FundMovement>  $fundMovements
     * @return list<PlannedOperation>
     */
    private function incomeOperations(BudgetSheet $sheet, array &$transfers, array &$fundMovements): array
    {
        $operations = [];
        $groups = $this->incomeGroups($sheet, $operations, $fundMovements);

        foreach ($groups as $cells) {
            $this->assertPositiveYear($cells);
            $cells = $this->moveNegatives($cells, $transfers);

            foreach ($cells as $cell) {
                $target = $this->mapping->income($cell['entry']->label);
                $operations[] = $this->rowOperation($sheet->year, $this->lastDay($sheet->year, $cell['month']), FinancialOperationType::Income, $target, $cell['entry'], $cell['amount']);
            }
        }

        return array_values(array_filter($operations, fn (?PlannedOperation $operation): bool => $operation !== null && $operation->amountMinorUnits !== 0));
    }

    /**
     * The income cells per source (the target Category), with the overrides already taken off the cells.
     *
     * @param  list<PlannedOperation>  $operations  receives the override operations
     * @param  list<FundMovement>  $fundMovements  receives the cells mapped to `SheetMapping::FUND`, which join no group
     * @return array<int|string, list<array{month: int, entry: SheetEntry, amount: int}>>
     */
    private function incomeGroups(BudgetSheet $sheet, array &$operations, array &$fundMovements): array
    {
        $overrides = $this->mapping->overrides($sheet->year);
        $used = [];
        $groups = [];

        foreach ($sheet->months as $month) {
            foreach ($month->income as $entry) {
                $target = $this->mapping->income($entry->label) ?? $this->unknownLabel($entry->address, $entry->rawLabel);
                $amount = $entry->amountMinorUnits;

                if ($target === SheetMapping::FUND) {
                    $this->recordFund($fundMovements, $entry, $amount, FinancialOperationType::Income);

                    continue;
                }

                foreach ($overrides as $index => $override) {
                    if ($override['month'] === $month->month && $override['label'] === $entry->label) {
                        $amount = $this->applyOverride($override, $entry, $amount, $sheet->year, $operations);
                        $used[$index] = true;
                    }
                }

                if ($target !== SheetMapping::SKIP) {
                    $groups[$target][] = ['month' => $month->month, 'entry' => $entry, 'amount' => $amount];
                }
            }
        }

        foreach ($overrides as $index => $override) {
            if (! isset($used[$index])) {
                throw new SheetLoadException('mapowanie', 'nadpisanie '.$override['year'].'-'.$override['month'].' „'.$override['label'].'” nie pasuje do żadnej komórki');
            }
        }

        return $groups;
    }

    /**
     * @param  array{year: int, month: int, label: string, amount_minor_units: int, category: int, description: string}  $override
     * @param  list<PlannedOperation>  $operations
     */
    private function applyOverride(array $override, SheetEntry $entry, int $amount, int $year, array &$operations): int
    {
        if ($override['amount_minor_units'] > $amount) {
            throw new SheetLoadException($entry->address, 'nadpisanie '.MinorUnits::toMajorDisplay($override['amount_minor_units']).' zł przekracza kwotę komórki '.MinorUnits::toMajorDisplay($amount).' zł');
        }

        $operations[] = new PlannedOperation(
            $this->lastDay($year, $override['month']), FinancialOperationType::Income, $override['category'],
            $override['amount_minor_units'], mb_substr($override['description'], 0, self::DESCRIPTION_LIMIT), $entry->address,
        );

        return $amount - $override['amount_minor_units'];
    }

    /**
     * @param  list<array{month: int, entry: SheetEntry, amount: int}>  $cells
     */
    private function assertPositiveYear(array $cells): void
    {
        $sum = array_sum(array_column($cells, 'amount'));
        $first = null;

        foreach ($cells as $cell) {
            if ($cell['amount'] !== 0) {
                $first ??= $cell['entry'];
            }
        }

        if ($first !== null && $sum <= 0) {
            throw new SheetLoadException($first->address, 'roczna suma źródła przychodu „'.$first->rawLabel.'” wynosi '.MinorUnits::toMajorDisplay($sum).' zł (musi być dodatnia)');
        }
    }

    /**
     * A negative income cell moves to the nearest later cell of the source that can take it, else to the nearest
     * earlier one; every income operation then has an amount of at least 1 (a cell netted to 0 has no operation).
     *
     * @param  list<array{month: int, entry: SheetEntry, amount: int}>  $cells
     * @param  list<SheetWarning>  $transfers
     * @return list<array{month: int, entry: SheetEntry, amount: int}>
     */
    private function moveNegatives(array $cells, array &$transfers): array
    {
        foreach (array_keys($cells) as $index) {
            if ($cells[$index]['amount'] >= 0) {
                continue;
            }

            $deficit = -$cells[$index]['amount'];
            $to = $this->transferTarget($cells, $index, $deficit);
            $source = $cells[$index];
            $target = $cells[$to];
            $cells[$to] = ['month' => $target['month'], 'entry' => $target['entry'], 'amount' => $target['amount'] - $deficit];
            $cells[$index] = ['month' => $source['month'], 'entry' => $source['entry'], 'amount' => 0];

            $transfers[] = new SheetWarning(
                $source['entry']->address,
                'ujemny przychód −'.MinorUnits::toMajorDisplay($deficit).' zł przeniesiony do '.$target['entry']->address,
            );
        }

        return $cells;
    }

    /**
     * @param  list<array{month: int, entry: SheetEntry, amount: int}>  $cells
     */
    private function transferTarget(array $cells, int $from, int $deficit): int
    {
        $count = count($cells);
        $candidates = [...range($from + 1, $count - 1), ...range($from - 1, 0)];

        foreach ($candidates as $index) {
            if ($index >= 0 && $index < $count && $index !== $from && $cells[$index]['amount'] >= $deficit) {
                return $index;
            }
        }

        throw new SheetLoadException($cells[$from]['entry']->address, 'ujemny przychód −'.MinorUnits::toMajorDisplay($deficit).' zł nie mieści się w żadnym innym miesiącu źródła');
    }

    private function assertTotal(int $planned, int $fund, SheetEntry $analysis, string $name): void
    {
        if ($planned + $fund !== $analysis->amountMinorUnits) {
            throw new SheetLoadException(
                $analysis->address,
                'suma operacji ('.$name.') '.MinorUnits::toMajorDisplay($planned).' zł + ruchy funduszu '.MinorUnits::toMajorDisplay($fund).' zł różni się od „Analizy” '.MinorUnits::toMajorDisplay($analysis->amountMinorUnits).' zł',
            );
        }
    }

    /**
     * @param  list<FundMovement>  $fundMovements
     */
    private function recordFund(array &$fundMovements, SheetEntry $entry, int $amount, FinancialOperationType $type): void
    {
        if ($amount !== 0) {
            $fundMovements[] = new FundMovement($entry->address, $entry->rawLabel, $amount, $type);
        }
    }

    private function unknownLabel(string $address, string $label): never
    {
        throw new SheetLoadException($address, 'etykieta „'.$label.'” nie ma wpisu w mapowaniu');
    }

    private function lastDay(int $year, int $month): string
    {
        return (new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->format('Y-m-t');
    }
}

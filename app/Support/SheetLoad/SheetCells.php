<?php

namespace App\Support\SheetLoad;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Typed access to the cells of one worksheet: normalised labels, amounts in minor units, addresses.
 */
final class SheetCells
{
    private ?int $highestRow = null;

    public function __construct(private readonly Worksheet $sheet) {}

    public function name(): string
    {
        return $this->sheet->getTitle();
    }

    /**
     * Computed once: `getHighestDataRow()` walks every cell, and the readers call this in their loop conditions.
     */
    public function highestRow(): int
    {
        return $this->highestRow ??= $this->sheet->getHighestDataRow();
    }

    public function address(string $column, int $row): string
    {
        return $this->name().'!'.$column.$row;
    }

    public function rangeAddress(string $column, int $from, int $to): string
    {
        return $this->name().'!'.$column.$from.':'.$column.$to;
    }

    public function text(string $column, int $row): ?string
    {
        $value = $this->value($column, $row);

        if ($value === null) {
            return null;
        }

        $text = trim(str_replace("\u{00A0}", ' ', (string) $value));

        return $text === '' ? null : $text;
    }

    public function label(string $column, int $row): ?string
    {
        $text = $this->text($column, $row);

        return $text === null ? null : self::normalizeLabel($text);
    }

    public function isEmpty(string $column, int $row): bool
    {
        return $this->text($column, $row) === null;
    }

    public function isDashes(string $column, int $row): bool
    {
        $text = $this->text($column, $row);

        return $text !== null && preg_match('/^-{2,}$/', $text) === 1;
    }

    /**
     * Empty and dash-only cells are zero; a number becomes minor units right here; any other text refuses.
     */
    public function amount(string $column, int $row): int
    {
        $value = $this->value($column, $row);

        if (is_int($value) || is_float($value)) {
            return (int) round($value * 100);
        }

        $text = $this->text($column, $row);

        if ($text === null || preg_match('/^-+$/', $text) === 1) {
            return 0;
        }

        throw new SheetReadException($this->address($column, $row), 'tekst „'.$text.'” w komórce kwoty');
    }

    public static function normalizeLabel(string $text): string
    {
        $label = mb_strtolower(trim(str_replace("\u{00A0}", ' ', $text)));
        $label = (string) preg_replace('/\s+/u', ' ', $label);
        $label = (string) preg_replace('/[\s*]+$/u', '', $label);

        return (string) preg_replace('/^work:/u', 'praca:', $label);
    }

    /**
     * The stored value of the cell; for a formula the value the sheet saved with it, never a recalculation.
     */
    private function value(string $column, int $row): mixed
    {
        $coordinate = $column.$row;

        if (! $this->sheet->cellExists($coordinate)) {
            return null;
        }

        $cell = $this->sheet->getCell($coordinate);

        if ($cell->getDataType() !== DataType::TYPE_FORMULA) {
            return $cell->getValue();
        }

        $stored = $cell->getOldCalculatedValue();

        if ($stored === null) {
            throw new SheetReadException($this->address($column, $row), 'formuła „'.$cell->getValue().'” bez zapisanej wartości; otwórz plik w Excelu i zapisz go ponownie');
        }

        return $stored;
    }
}

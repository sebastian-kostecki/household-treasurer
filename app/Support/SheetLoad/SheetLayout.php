<?php

namespace App\Support\SheetLoad;

/**
 * What differs between the two known workbook layouts: the columns of the income amounts and of the lists, the label of
 * the treatment list, and whether the "nazwa" header and the medical block must be there.
 */
enum SheetLayout
{
    /** 2022-2025: a `Ustawienia` sheet, income amounts in I, lists in J-M. */
    case Since2022;

    /** 2020-2021: no `Ustawienia`, income amounts in H, lists in I-L, the medical block without a "nazwa" header. */
    case Before2022;

    public function incomeColumn(): string
    {
        return match ($this) {
            self::Since2022 => 'I',
            self::Before2022 => 'H',
        };
    }

    public function categoryColumn(): string
    {
        return match ($this) {
            self::Since2022 => 'J',
            self::Before2022 => 'I',
        };
    }

    public function nameColumn(): string
    {
        return match ($this) {
            self::Since2022 => 'K',
            self::Before2022 => 'J',
        };
    }

    public function planColumn(): string
    {
        return match ($this) {
            self::Since2022 => 'L',
            self::Before2022 => 'K',
        };
    }

    public function spentColumn(): string
    {
        return match ($this) {
            self::Since2022 => 'M',
            self::Before2022 => 'L',
        };
    }

    /**
     * @return list<string> category, name, plan and expense column
     */
    public function listColumns(): array
    {
        return [$this->categoryColumn(), $this->nameColumn(), $this->planColumn(), $this->spentColumn()];
    }

    public function treatmentLabel(): string
    {
        return match ($this) {
            self::Since2022 => 'leczenie prywatne',
            self::Before2022 => 'lekarze - dentysta',
        };
    }

    public function requiresNameHeader(): bool
    {
        return $this === self::Since2022;
    }

    public function requiresMedicalBlock(): bool
    {
        return $this === self::Since2022;
    }
}

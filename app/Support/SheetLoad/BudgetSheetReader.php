<?php

namespace App\Support\SheetLoad;

use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Reads a whole budget workbook (stored calculated values, no recalculation) into a BudgetSheet.
 */
final class BudgetSheetReader
{
    public const MONTH_SHEETS = [
        1 => 'styczeń', 2 => 'luty', 3 => 'marzec', 4 => 'kwiecień', 5 => 'maj', 6 => 'czerwiec',
        7 => 'lipiec', 8 => 'sierpień', 9 => 'wrzesień', 10 => 'październik', 11 => 'listopad', 12 => 'grudzień',
    ];

    public function __construct(private readonly MonthSheetReader $monthReader = new MonthSheetReader) {}

    public function read(string $path): BudgetSheet
    {
        $spreadsheet = $this->load($path);

        $layout = $this->layout($spreadsheet);
        $year = $layout === SheetLayout::Since2022 ? $this->settingsYear($spreadsheet) : $this->decemberYear($spreadsheet);
        $analysis = $this->sheet($spreadsheet, 'Analiza');
        $months = [];

        foreach (self::MONTH_SHEETS as $number => $name) {
            $months[$number] = $this->monthReader->read($spreadsheet->getSheetByName($name) ?? $this->missing($name), $number, $layout);
        }

        return new BudgetSheet(
            $year,
            $months,
            new SheetEntry('przychody', 'przychody', $analysis->amount('B', 16), $analysis->address('B', 16)),
            new SheetEntry('wydatki', 'wydatki', $analysis->amount('C', 16), $analysis->address('C', 16)),
        );
    }

    /**
     * A `Ustawienia` sheet means 2022-2025; without it "kategoria" in `styczeń!I3` means 2020-2021; anything else is refused.
     */
    private function layout(Spreadsheet $spreadsheet): SheetLayout
    {
        if ($spreadsheet->getSheetByName('Ustawienia') !== null) {
            return SheetLayout::Since2022;
        }

        $january = $spreadsheet->getSheetByName(self::MONTH_SHEETS[1]);

        if ($january !== null && (new SheetCells($january))->label('I', 3) === 'kategoria') {
            return SheetLayout::Before2022;
        }

        throw new SheetReadException('Ustawienia', 'brak arkusza; to nie jest też układ 2020–2021 (oczekiwane „kategoria” w komórce styczeń!I3)');
    }

    private function settingsYear(Spreadsheet $spreadsheet): int
    {
        $settings = $this->sheet($spreadsheet, 'Ustawienia');
        $year = $settings->amount('B', 2);

        if ($year % 100 !== 0 || $year < 100_000 || $year > 999_900) {
            throw new SheetReadException($settings->address('B', 2), 'rok musi być liczbą całkowitą');
        }

        return intdiv($year, 100);
    }

    /**
     * The 2020-2021 workbooks have no year cell; `grudzień!A1` is the date 1 December of the year (a formula, read from its stored value).
     */
    private function decemberYear(Spreadsheet $spreadsheet): int
    {
        $december = $this->sheet($spreadsheet, self::MONTH_SHEETS[12]);
        $serial = $december->serial('A', 1);

        if ($serial !== null) {
            $date = Date::excelToDateTimeObject($serial);

            if ($date->format('m-d') === '12-01') {
                return (int) $date->format('Y');
            }
        }

        throw new SheetReadException($december->address('A', 1), 'oczekiwana data 1 grudnia roku arkusza (liczba będąca datą Excela)');
    }

    private function load(string $path): Spreadsheet
    {
        if (! is_file($path)) {
            throw new SheetReadException($path, 'brak pliku');
        }

        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);

            return $reader->load($path);
        } catch (SpreadsheetException $e) {
            throw new SheetReadException($path, 'nie da się odczytać skoroszytu: '.$e->getMessage(), $e);
        }
    }

    private function sheet(Spreadsheet $spreadsheet, string $name): SheetCells
    {
        return new SheetCells($spreadsheet->getSheetByName($name) ?? $this->missing($name));
    }

    private function missing(string $name): never
    {
        throw new SheetReadException($name, 'brak arkusza');
    }
}

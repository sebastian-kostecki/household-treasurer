<?php

namespace App\Support\SheetLoad;

use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use PhpOffice\PhpSpreadsheet\IOFactory;
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

        $settings = $this->sheet($spreadsheet, 'Ustawienia');
        $year = $settings->amount('B', 2);

        if ($year % 100 !== 0 || $year < 100_000 || $year > 999_900) {
            throw new SheetReadException($settings->address('B', 2), 'rok musi być liczbą całkowitą');
        }

        $analysis = $this->sheet($spreadsheet, 'Analiza');
        $months = [];

        foreach (self::MONTH_SHEETS as $number => $name) {
            $months[$number] = $this->monthReader->read($spreadsheet->getSheetByName($name) ?? $this->missing($name), $number);
        }

        return new BudgetSheet(
            intdiv($year, 100),
            $months,
            new SheetEntry('przychody', 'przychody', $analysis->amount('B', 16), $analysis->address('B', 16)),
            new SheetEntry('wydatki', 'wydatki', $analysis->amount('C', 16), $analysis->address('C', 16)),
        );
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

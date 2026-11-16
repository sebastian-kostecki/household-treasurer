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

    public function __construct(
        private readonly MonthSheetReader $monthReader = new MonthSheetReader,
        private readonly MonthSheet2019Reader $month2019Reader = new MonthSheet2019Reader,
    ) {}

    public function read(string $path): BudgetSheet
    {
        $spreadsheet = $this->load($path);
        $suffix = $this->suffix2019($spreadsheet);

        if ($suffix !== null) {
            return $this->read2019($spreadsheet, $suffix);
        }

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
     * The 2019 workbook has a `Podsumowanie` sheet and month sheets with a year suffix (`styczeń_19`); returns that suffix.
     */
    private function suffix2019(Spreadsheet $spreadsheet): ?string
    {
        if ($spreadsheet->getSheetByName('Podsumowanie') === null) {
            return null;
        }

        foreach ($spreadsheet->getSheetNames() as $name) {
            if (preg_match('/^'.preg_quote(self::MONTH_SHEETS[1], '/').'(_\d{2})$/u', $name, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }

    private function read2019(Spreadsheet $spreadsheet, string $suffix): BudgetSheet
    {
        $months = [];

        foreach (self::MONTH_SHEETS as $number => $name) {
            $months[$number] = $this->month2019Reader->read($spreadsheet->getSheetByName($name.$suffix) ?? $this->missing($name.$suffix), $number);
        }

        $summary = $this->sheet($spreadsheet, 'Podsumowanie');

        return new BudgetSheet(
            $this->year2019($spreadsheet, $suffix),
            $months,
            new SheetEntry('przychody', 'przychody', $summary->amount('I', 16), $summary->address('I', 16)),
            new SheetEntry('wydatki', 'wydatki', $summary->amount('H', 16), $summary->address('H', 16)),
        );
    }

    /**
     * `grudzień_19!C31` is the last day of the month: 31 December of the year, which must agree with the sheet suffix.
     */
    private function year2019(Spreadsheet $spreadsheet, string $suffix): int
    {
        $december = $this->sheet($spreadsheet, self::MONTH_SHEETS[12].$suffix);
        $serial = $december->serial('C', 31);

        if ($serial !== null) {
            $date = Date::excelToDateTimeObject($serial);

            if ($date->format('m-d') === '12-31' && (int) $date->format('Y') % 100 === (int) substr($suffix, 1)) {
                return (int) $date->format('Y');
            }
        }

        throw new SheetReadException($december->address('C', 31), 'oczekiwana data 31 grudnia roku arkusza zgodnego z sufiksem '.$suffix.' (liczba będąca datą Excela)');
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

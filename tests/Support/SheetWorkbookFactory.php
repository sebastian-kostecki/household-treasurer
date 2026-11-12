<?php

namespace Tests\Support;

use Closure;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Builds a small budget workbook with literal values (no real user data) in the 2023-2025 or the 2022 layout.
 *
 * Options (all optional):
 * - layout: '2023' (default) or '2022' (8 income sources, empty plan column, daily breakdown, total one row lower)
 * - year: int, default 2025
 * - januaryOffset: extra empty rows in the January lists (1 or 2 in the real sheets)
 * - months: array<int, array> overrides per month number with keys expenses (label => zloty), income (label => zloty),
 *   irregular / medical / exams (list of [category, name, amount]; null is an empty row), daily (label => zloty,
 *   2022 only), irregularTotal, balance, fund (zloty)
 * - analysis: [income, expense] in zloty, default the computed sums without the savings rows
 * - mutate: Closure(Spreadsheet): void, applied last, for cell-level edge cases
 */
final class SheetWorkbookFactory
{
    public const MONTHS = [
        1 => 'styczeń', 2 => 'luty', 3 => 'marzec', 4 => 'kwiecień', 5 => 'maj', 6 => 'czerwiec',
        7 => 'lipiec', 8 => 'sierpień', 9 => 'wrzesień', 10 => 'październik', 11 => 'listopad', 12 => 'grudzień',
    ];

    /**
     * @param  array<string, mixed>  $options
     * @param  Closure(string): void  $callback
     */
    public static function with(array $options, Closure $callback): void
    {
        $path = self::build($options);

        try {
            $callback($path);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public static function build(array $options = []): string
    {
        $layout = $options['layout'] ?? '2023';
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        $settings = new Worksheet($spreadsheet, 'Ustawienia');
        $spreadsheet->addSheet($settings);
        $settings->setCellValue('B2', $options['year'] ?? 2025);

        $incomeSum = 0;
        $expenseSum = 0;

        foreach (self::MONTHS as $number => $name) {
            $sheet = new Worksheet($spreadsheet, $name);
            $spreadsheet->addSheet($sheet);
            $totals = self::month($sheet, $number, $layout, $options, $options['months'][$number] ?? []);
            $incomeSum += $totals[0];
            $expenseSum += $totals[1];
        }

        $analysis = new Worksheet($spreadsheet, 'Analiza');
        $spreadsheet->addSheet($analysis);
        [$income, $expense] = $options['analysis'] ?? [$incomeSum / 100, $expenseSum / 100];
        $analysis->setCellValue('B16', $income);
        $analysis->setCellValue('C16', $expense);

        if (isset($options['mutate'])) {
            ($options['mutate'])($spreadsheet);
        }

        $path = tempnam(sys_get_temp_dir(), 'sheet-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $month
     * @return array{0: int, 1: int} income and expense in minor units without the savings rows
     */
    private static function month(Worksheet $sheet, int $number, string $layout, array $options, array $month): array
    {
        $is2022 = $layout === '2022';
        $expenses = $month['expenses'] ?? [
            'Rata kredytu' => 1800.50, 'Czynsz' => 700.00, 'Prąd**' => 120.10, 'Codzienne wydatki' => 900.00,
            'Apteka' => 80.00, 'Badania' => 50.00, 'Leczenie prywatne' => 200.00, 'Oszczędności długoterminowe***' => 300.00,
        ];
        $income = $month['income'] ?? ($is2022
            ? ['praca: Sebastian' => 4000.00, 'work: Jana' => 3000.00, '500+' => 800.00, 'darowizny' => 0.0, 'odsetki' => 5.00, 'pozostałe' => 0.0, 'przychody nieregularne' => 0.0, 'oszczędności' => 300.00]
            : ['Praca: Sebastian' => 4000.00, 'work: Jana' => 3000.00, '500+' => 800.00, 'darowizny' => 0.0, 'odsetki' => 5.00, 'pozostałe' => 0.0, 'oszczędności' => 300.00]);
        $irregular = $month['irregular'] ?? [['Ubrania', 'Kurtka', 250.00], ['Dom', 'Żarówki', 30.50], ['-', 'Bez kategorii', 19.50]];
        $medical = $month['medical'] ?? [['', 'Wizyta', 120.00], ['', 'Leki', 80.00]];
        $exams = $month['exams'] ?? [['', 'Morfologia', 50.00]];
        $fund = $month['fund'] ?? 0.0;
        $daily = $is2022 ? ($month['daily'] ?? ['Jedzenie' => 600.00, 'Słodycze i inne' => 100.00, 'Higiena i kosmetyki' => 150.00, 'Pieluchy' => 50.00]) : null;

        $listSum = array_sum(array_map(fn (?array $row): int => self::grosze($row[2] ?? 0), $irregular));
        $irregularTotal = isset($month['irregularTotal']) ? self::grosze($month['irregularTotal']) : $listSum - self::grosze($fund);
        $incomeTotal = array_sum(array_map(self::grosze(...), $income));
        $expenseTotal = array_sum(array_map(self::grosze(...), $expenses)) + $irregularTotal;
        $balance = isset($month['balance']) ? self::grosze($month['balance']) : 0;

        $row = 3;
        $sheet->setCellValue('A1', $number);

        foreach ($expenses as $label => $amount) {
            $sheet->setCellValue('A'.$row, 'Grupa');
            $sheet->setCellValue('B'.$row, $label);
            $sheet->setCellValue('C'.$row, $amount);
            $sheet->setCellValue('D'.$row, $amount);
            $row += $label === 'Prąd**' ? 2 : 1;
        }

        $irregularRow = $is2022 ? 31 : 30;
        $sheet->setCellValue('B'.$irregularRow, 'Wydatki nieregularne');
        $sheet->setCellValue('D'.$irregularRow, $irregularTotal / 100);
        $sheet->setCellValue('B'.($irregularRow + 1), $is2022 ? 'Pozostałe' : 'Bilans');
        $sheet->setCellValue('D'.($irregularRow + 1), $balance / 100);

        $row = 3;

        foreach ($income as $label => $amount) {
            $sheet->setCellValue('G'.$row, $label);
            $sheet->setCellValue('I'.$row, $amount);

            if (! $is2022) {
                $sheet->setCellValue('H'.$row, $amount);
            }

            $row++;
        }

        $sheet->setCellValue('G16', 'Wydatki z funduszu');
        $sheet->setCellValue('I16', $fund);

        if ($daily !== null) {
            self::dailyBreakdown($sheet, $daily);
        }

        $offset = $number === 1 ? ($options['januaryOffset'] ?? 0) : 0;
        self::lists($sheet, $irregular, $medical, $exams, $offset);

        $excluded = array_sum(array_map(self::grosze(...), array_filter($expenses, fn (string $label): bool => str_starts_with($label, 'Oszczędności'), ARRAY_FILTER_USE_KEY)));
        $excludedIncome = self::grosze($income['oszczędności'] ?? 0);

        return [$incomeTotal - $excludedIncome, $expenseTotal - $excluded];
    }

    /**
     * As in the real 2022 sheets: the header "Codzienne wydatki" in G25, the items under it, then "Do kiedy wydatki"
     * with a date serial in H and I, then the item labels again without amounts.
     *
     * @param  array<string, float>  $daily
     */
    private static function dailyBreakdown(Worksheet $sheet, array $daily): void
    {
        $sheet->setCellValue('G25', 'Codzienne wydatki');
        $row = 26;

        foreach ($daily as $label => $amount) {
            $sheet->setCellValue('G'.$row, $label);
            $sheet->setCellValue('I'.$row, $amount);
            $row++;
        }

        $sheet->setCellValue('G'.$row, 'Do kiedy wydatki');
        $sheet->setCellValue('H'.$row, 44592);
        $sheet->setCellValue('I'.$row, 44592);

        foreach (array_keys($daily) as $label) {
            $sheet->setCellValue('G'.++$row, mb_strtolower($label));
        }
    }

    /**
     * @param  list<array{0: ?string, 1: ?string, 2: float|string|null}|null>  $irregular
     * @param  list<array{0: ?string, 1: ?string, 2: float|string|null}|null>  $medical
     * @param  list<array{0: ?string, 1: ?string, 2: float|string|null}|null>  $exams
     */
    private static function lists(Worksheet $sheet, array $irregular, array $medical, array $exams, int $offset): void
    {
        $sheet->fromArray(['kategoria', 'nazwa', 'plan', 'wydatek'], null, 'J3');
        self::writeRows($sheet, $irregular, 4, true);

        $healthRow = 4 + max(17, count($irregular)) + $offset;
        $sheet->setCellValue('J'.$healthRow, 'Opieka zdrowotna');
        $sheet->setCellValue('J'.($healthRow + 1), 'Leczenie prywatne');
        $sheet->fromArray(['data', 'nazwa', 'plan', 'wydatek'], null, 'J'.($healthRow + 2));

        $medicalStart = $healthRow + 3;
        self::writeRows($sheet, $medical, $medicalStart, false);

        $examsRow = $medicalStart + max(6, count($medical));
        $sheet->setCellValue('J'.$examsRow, 'Badania');
        self::writeRows($sheet, $exams, $examsRow + 1, false);
    }

    /**
     * @param  list<array{0: ?string, 1: ?string, 2: float|string|null}|null>  $rows
     */
    private static function writeRows(Worksheet $sheet, array $rows, int $start, bool $withCategory): void
    {
        foreach ($rows as $index => $item) {
            if ($item === null) {
                continue;
            }

            $row = $start + $index;

            if ($withCategory && $item[0] !== null && $item[0] !== '') {
                $sheet->setCellValue('J'.$row, $item[0]);
            }

            if ($item[1] !== null) {
                $sheet->setCellValue('K'.$row, $item[1]);
            }

            if ($item[2] !== null) {
                $sheet->setCellValue('L'.$row, $item[2]);
                $sheet->setCellValue('M'.$row, $item[2]);
            }
        }
    }

    private static function grosze(mixed $amount): int
    {
        return is_numeric($amount) ? (int) round($amount * 100) : 0;
    }
}

<?php

namespace Tests\Support;

use Closure;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Builds a small budget workbook with literal values (no real user data) in the 2023-2025 or the 2022 layout.
 *
 * Options (all optional):
 * - layout: '2023' (default), '2022' (8 income sources, empty plan column, daily breakdown, total one row lower) or
 *   '2020' (no Ustawienia, income in G/H, lists in I-L, medical block without a "nazwa" header, A1 formulas, fund rows)
 * - year: int, default 2025 (2020 for layout '2020')
 * - withoutMedical: layout '2020' only, month numbers without the medical block and rows, with an unrelated list below
 * - januaryOffset: extra empty rows in the January lists (1 or 2 in the real sheets)
 * - months: array<int, array> overrides per month number with keys expenses (label => zloty), income (label => zloty),
 *   irregular / medical / exams (list of [category, name, amount]; null is an empty row), daily (label => zloty,
 *   2022 only), irregularTotal, balance, fund (zloty)
 * - analysis: [income, expense] in zloty, default the computed sums without the savings rows
 * - mutate: Closure(Spreadsheet): void, applied last, for cell-level edge cases
 *
 * Layout '2019' writes `grudzień_18`, the sheets `<month>_19` (variant A in January-February, B in March-June, C in
 * July-December) and `Podsumowanie` (H16 and I16 as sums of the months, `analysis` overrides them), with 31 December 2019
 * in `grudzień_19!C31`. Month overrides: expenses and income (label => zloty), sebastian / jana / home (list of [name, zloty]),
 * goals (variants A and B) and daily (label => zloty, November-December). List rows hold SUM formulas.
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

        if ($layout === '2019') {
            return self::build2019($options);
        }

        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        if ($layout !== '2020') {
            $settings = new Worksheet($spreadsheet, 'Ustawienia');
            $spreadsheet->addSheet($settings);
            $settings->setCellValue('B2', $options['year'] ?? 2025);
        }

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
        $is2020 = $layout === '2020';
        $withoutMedical = $is2020 && in_array($number, $options['withoutMedical'] ?? [], true);
        $expenses = $month['expenses'] ?? ($is2020
            ? array_merge(
                ['Rata kredytu' => 1800.50, 'Czynsz' => 700.00, 'Prąd**' => 120.10, 'Codzienne wydatki' => 900.00, 'Apteka' => 80.00],
                $withoutMedical ? [] : ['Badania' => 50.00, 'Lekarz - dentysta' => 200.00],
                ['Fundusz Wydatków Celowych*' => 100.00, 'Rezerwa na wydatki*' => 20.00, 'Nadpłata kredytu' => 50.00, 'Oszczędności długoterminowe***' => 300.00],
            )
            : [
                'Rata kredytu' => 1800.50, 'Czynsz' => 700.00, 'Prąd**' => 120.10, 'Codzienne wydatki' => 900.00,
                'Apteka' => 80.00, 'Badania' => 50.00, 'Leczenie prywatne' => 200.00, 'Oszczędności długoterminowe***' => 300.00,
            ]);
        $income = $month['income'] ?? ($is2020
            ? ['Praca: Sebastian' => 4000.00, 'work: Jana' => 3000.00, '500+' => 800.00, 'odsetki' => 5.00, 'przychody rozłożone' => 150.00, 'oszczędności' => 300.00]
            : ($is2022
            ? ['praca: Sebastian' => 4000.00, 'work: Jana' => 3000.00, '500+' => 800.00, 'darowizny' => 0.0, 'odsetki' => 5.00, 'pozostałe' => 0.0, 'przychody nieregularne' => 0.0, 'oszczędności' => 300.00]
            : ['Praca: Sebastian' => 4000.00, 'work: Jana' => 3000.00, '500+' => 800.00, 'darowizny' => 0.0, 'odsetki' => 5.00, 'pozostałe' => 0.0, 'oszczędności' => 300.00]));
        $irregular = $month['irregular'] ?? [['Ubrania', 'Kurtka', 250.00], ['Dom', 'Żarówki', 30.50], ['-', 'Bez kategorii', 19.50]];
        $medical = $month['medical'] ?? [['', 'Wizyta', 120.00], ['', 'Leki', 80.00]];
        $exams = $month['exams'] ?? [['', 'Morfologia', 50.00]];
        $fund = $month['fund'] ?? 0.0;
        $daily = $is2022 || $is2020 ? ($month['daily'] ?? ['Jedzenie' => 600.00, 'Słodycze i inne' => 100.00, 'Higiena i kosmetyki' => 150.00, 'Pieluchy' => 50.00]) : null;

        $listSum = array_sum(array_map(fn (?array $row): int => self::grosze($row[2] ?? 0), $irregular));
        $irregularTotal = isset($month['irregularTotal']) ? self::grosze($month['irregularTotal']) : $listSum - self::grosze($fund);
        $incomeTotal = array_sum(array_map(self::grosze(...), $income));
        $expenseTotal = array_sum(array_map(self::grosze(...), $expenses)) + $irregularTotal;
        $balance = isset($month['balance']) ? self::grosze($month['balance']) : 0;

        $row = 3;

        if ($is2020) {
            $sheet->setCellValue('A1', $number === 1 ? 'Budżet domowy' : '=DATE('.($options['year'] ?? 2020).','.$number.',1)');
        } else {
            $sheet->setCellValue('A1', $number);
        }

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

            if ($is2020) {
                $sheet->setCellValue('H'.$row, $amount);
            } else {
                $sheet->setCellValue('I'.$row, $amount);

                if (! $is2022) {
                    $sheet->setCellValue('H'.$row, $amount);
                }
            }

            $row++;
        }

        $sheet->setCellValue('G16', 'Wydatki z funduszu');
        $sheet->setCellValue($is2020 ? 'H16' : 'I16', $fund);

        if ($daily !== null) {
            self::dailyBreakdown($sheet, $daily, $is2020);
        }

        $offset = $number === 1 ? ($options['januaryOffset'] ?? 0) : 0;

        if ($is2020) {
            self::lists2020($sheet, $irregular, $medical, $exams, $withoutMedical);
        } else {
            self::lists($sheet, $irregular, $medical, $exams, $offset);
        }

        $excluded = array_sum(array_map(self::grosze(...), array_filter($expenses, fn (string $label): bool => str_starts_with($label, 'Oszczędności'), ARRAY_FILTER_USE_KEY)));
        $excludedIncome = self::grosze($income['oszczędności'] ?? 0);

        return [$incomeTotal - $excludedIncome, $expenseTotal - $excluded];
    }

    /**
     * As in the real 2022 sheets: the header "Codzienne wydatki" in G25, the items under it, then "Do kiedy wydatki"
     * with a date serial in H and I, then the item labels again without amounts.
     *
     * In the 2020 layout the amounts sit in H and the breakdown ends with a "Na tydzień" row (250).
     *
     * @param  array<string, float>  $daily
     */
    private static function dailyBreakdown(Worksheet $sheet, array $daily, bool $is2020 = false): void
    {
        $sheet->setCellValue('G25', 'Codzienne wydatki');
        $row = 26;

        foreach ($daily as $label => $amount) {
            $sheet->setCellValue('G'.$row, $label);
            $sheet->setCellValue(($is2020 ? 'H' : 'I').$row, $amount);
            $row++;
        }

        if ($is2020) {
            $sheet->setCellValue('G'.$row, 'Na tydzień');
            $sheet->setCellValue('H'.$row, 250);

            return;
        }

        $sheet->setCellValue('G'.$row, 'Do kiedy wydatki');
        $sheet->setCellValue('H'.$row, 44592);
        $sheet->setCellValue('I'.$row, 44592);

        foreach (array_keys($daily) as $label) {
            $sheet->setCellValue('G'.++$row, mb_strtolower($label));
        }
    }

    /**
     * Lists in I-L; the medical block "Opieka zdrowotna" / "Lekarze - dentysta" has no "nazwa" header. A month without
     * the block ends its list at a blank row and has an unrelated list below, as `styczeń!K48` in the real 2020 sheet.
     *
     * @param  list<array{0: ?string, 1: ?string, 2: float|string|null}|null>  $irregular
     * @param  list<array{0: ?string, 1: ?string, 2: float|string|null}|null>  $medical
     * @param  list<array{0: ?string, 1: ?string, 2: float|string|null}|null>  $exams
     */
    private static function lists2020(Worksheet $sheet, array $irregular, array $medical, array $exams, bool $withoutMedical): void
    {
        $sheet->fromArray(['kategoria', 'nazwa', 'plan', 'wydatek'], null, 'I3');
        self::writeRows($sheet, $irregular, 4, true, ['I', 'J', 'K', 'L']);

        if ($withoutMedical) {
            $sheet->setCellValue('J'.(4 + count($irregular) + 1), 'Badania');
            $sheet->setCellValue('L'.(4 + count($irregular) + 1), 155.90);

            return;
        }

        $healthRow = 4 + max(17, count($irregular));
        $sheet->setCellValue('I'.$healthRow, 'Opieka zdrowotna');
        $sheet->setCellValue('I'.($healthRow + 1), 'Lekarze - dentysta');

        $medicalStart = $healthRow + 2;
        self::writeRows($sheet, $medical, $medicalStart, false, ['I', 'J', 'K', 'L']);

        $examsRow = $medicalStart + max(6, count($medical));
        $sheet->setCellValue('I'.$examsRow, 'Badania');
        self::writeRows($sheet, $exams, $examsRow + 1, false, ['I', 'J', 'K', 'L']);
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
     * @param  array{0: string, 1: string, 2: string, 3: string}  $columns  category, name, plan, expense
     */
    private static function writeRows(Worksheet $sheet, array $rows, int $start, bool $withCategory, array $columns = ['J', 'K', 'L', 'M']): void
    {
        foreach ($rows as $index => $item) {
            if ($item === null) {
                continue;
            }

            $row = $start + $index;

            if ($withCategory && $item[0] !== null && $item[0] !== '') {
                $sheet->setCellValue($columns[0].$row, $item[0]);
            }

            if ($item[1] !== null) {
                $sheet->setCellValue($columns[1].$row, $item[1]);
            }

            if ($item[2] !== null) {
                $sheet->setCellValue($columns[2].$row, $item[2]);
                $sheet->setCellValue($columns[3].$row, $item[2]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private static function build2019(array $options): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        $previous = new Worksheet($spreadsheet, 'grudzień_18');
        $spreadsheet->addSheet($previous);
        $previous->setCellValue('A1', 'Budżet miesięczny');
        $previous->setCellValue('B5', 7520);

        $summary = new Worksheet($spreadsheet, 'Podsumowanie');
        $summary->fromArray(['Wydatki', 'Przychody'], null, 'H1');

        foreach (self::MONTHS as $number => $name) {
            $sheet = new Worksheet($spreadsheet, $name.'_19');
            $spreadsheet->addSheet($sheet);
            [$income, $expense] = $number <= 6
                ? self::monthBefore2019Template($sheet, $number, $options['months'][$number] ?? [])
                : self::monthOf2019Template($sheet, $number, $options['months'][$number] ?? []);
            $summary->setCellValue('H'.(2 + $number), $expense / 100);
            $summary->setCellValue('I'.(2 + $number), $income / 100);
        }

        $spreadsheet->addSheet($summary);
        $summary->setCellValue('H16', isset($options['analysis']) ? $options['analysis'][1] : '=SUM(H3:H14)');
        $summary->setCellValue('I16', isset($options['analysis']) ? $options['analysis'][0] : '=SUM(I3:I14)');
        $spreadsheet->getSheetByName('grudzień_19')->setCellValue('C31', 43830);

        if (isset($options['mutate'])) {
            ($options['mutate'])($spreadsheet);
        }

        $path = tempnam(sys_get_temp_dir(), 'sheet-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    /**
     * January-June: labels in A, plan in B, actual in C. Variant A ("Przychody" in A5) is two rows lower than variant B (A3);
     * June has one "osobiste" row with an actual instead of the two personal rows. The personal lists sit in F-I.
     *
     * @param  array<string, mixed>  $month
     * @return array{0: int, 1: int} income and expense in minor units, without the goals and savings rows
     */
    private static function monthBefore2019Template(Worksheet $sheet, int $number, array $month): array
    {
        $top = $number <= 2 ? 5 : 3;
        $income = $month['income'] ?? ['praca: Sebastian' => 4000.00, 'praca: Jana' => 3000.00, 'stypendium' => 1000.00];
        $expenses = $month['expenses'] ?? ['Czynsz' => 1250.00, 'prąd+gaz' => 0.0, 'Codzienne wydatki' => 900.00, 'Lekarze' => 150.00];
        $sebastian = $month['sebastian'] ?? [['fryzjer', 30.00], ['bilet', 120.00]];
        $jana = $month['jana'] ?? [['filtr', 58.00], ['lekarstwa', 50.00]];
        $home = $month['home'] ?? [['raty za stół', 110.00], ['prąd', 192.00]];
        $goals = $month['goals'] ?? [['ubezpieczenie samochodu', 120.00]];

        $sheet->setCellValue('A1', 'Budżet miesięczny');
        $sheet->setCellValue('A'.$top, 'Przychody');
        $row = $top + 1;

        foreach ($income as $label => $amount) {
            $sheet->setCellValue('A'.$row, $number === 2 && $row === $top + 1 ? self::richText($label) : $label);
            $sheet->setCellValue('B'.$row, $amount);
            $row++;
        }

        $sheet->setCellValue('B'.$top, '='.implode('+', array_map(fn (int $r): string => 'B'.$r, range($top + 1, $row - 1))));
        $expenseTop = $top + 5;
        $sheet->setCellValue('A'.$expenseTop, 'Wydatki');
        $row = $expenseTop + 1;

        foreach ($expenses as $label => $amount) {
            self::writeActualRow($sheet, $row++, $label, $amount);
        }

        $sheet->fromArray(['Wydatki osobiste'], null, 'F'.$top);
        $sheet->setCellValue('F'.($top + 1), 'Sebastian');
        $sheet->setCellValue('H'.($top + 1), 'Jana');
        self::writeNamedAmounts($sheet, $sebastian, $top + 2, 'F', 'G');
        self::writeNamedAmounts($sheet, $jana, $top + 2, 'H', 'I');
        $sheet->setCellValue('F'.($top + 13), 'Cele (odkładane przez Sebastiana)');
        self::writeNamedAmounts($sheet, $goals, $top + 14, 'F', 'I');
        $sheet->setCellValue('F'.($top + 18), 'Dom i akcesoria');
        self::writeNamedAmounts($sheet, $home, $top + 19, 'F', 'I');

        $homeSum = '=SUM(I'.($top + 19).':I'.($top + 22).')';
        $janaSum = 'SUM(I'.($top + 2).':I'.($top + 10).')';
        $sebastianSum = 'SUM(G'.($top + 2).':G'.($top + 10).')';
        $goalsSum = '=SUM(I'.($top + 14).':I'.($top + 16).')';

        if ($number === 6) {
            self::writeListRow($sheet, $row++, 'akcesoria do domu', $homeSum, true);
            self::writeListRow($sheet, $row++, 'osobiste', '='.$sebastianSum.'+'.$janaSum, true);
            self::writeListRow($sheet, $row++, 'cele: Sebastian', $goalsSum, true);
        } else {
            self::writeListRow($sheet, $row++, 'akcesoria do domu', $homeSum, false);
            self::writeListRow($sheet, $row++, 'osobiste: Jana', '='.$janaSum, false);
            self::writeListRow($sheet, $row++, 'osobiste: Sebastian', '='.$sebastianSum, false);
            self::writeListRow($sheet, $row++, 'cele: Sebastian', $goalsSum, false);
        }

        $sheet->setCellValue('A'.$row, 'Oszczędności');
        $sheet->setCellValue('B'.$row, 0);

        $expenseTotal = array_sum(array_map(self::grosze(...), $expenses))
            + self::sumAmounts($sebastian) + self::sumAmounts($jana) + self::sumAmounts($home);

        return [array_sum(array_map(self::grosze(...), $income)), $expenseTotal];
    }

    /**
     * July-December: income labels in A and amounts in C; expense labels in B, plan in C, actual in D, "Pozostałe" below
     * the last row. The personal lists sit in G-J (G-L from October with "plan:" and "wydatek:" columns); "Cele do
     * odłożenia" and "Dom i akcesoria" follow in G. In November and December "Codzienne wydatki" is broken down in M12:O16.
     *
     * @param  array<string, mixed>  $month
     * @return array{0: int, 1: int} income and expense in minor units, without the goals and savings rows
     */
    private static function monthOf2019Template(Worksheet $sheet, int $number, array $month): array
    {
        $split = $number >= 10;
        $income = $month['income'] ?? ['praca: Sebastian' => 4000.00, 'praca: Jana' => 3000.00, 'inne' => 100.00];
        $expenses = $month['expenses'] ?? ['Rata kredytu' => 1000.00, 'Czynsz' => 500.00, 'Rachunki' => 180.00, 'Codzienne wydatki' => 900.00, 'Lekarze' => 400.00];
        $sebastian = $month['sebastian'] ?? [['fryzjer', 30.00], ['bilet', 120.00]];
        $jana = $month['jana'] ?? [['filtr', 58.00], ['lekarstwa', 50.00]];
        $home = $month['home'] ?? [['farby', 259.83], ['tapeta', 438.00]];
        $daily = $number >= 11 ? ($month['daily'] ?? ['Jedzenie' => 600.00, 'Higiena i kosmetyki' => 150.00, 'Środki czystości' => 100.00, 'Gospodarstwo domowe' => 0.0]) : null;

        if ($daily !== null && isset($expenses['Codzienne wydatki'])) {
            $expenses['Codzienne wydatki'] = array_sum($daily);
        }

        $sheet->setCellValue('A1', 'BUDŻET MIESIĘCZNY');
        $sheet->setCellValue('A2', 'Przychody');
        $row = 3;

        foreach ($income as $label => $amount) {
            $sheet->setCellValue('A'.$row, $label);
            $sheet->setCellValue('C'.$row, $amount);
            $row++;
        }

        $sheet->setCellValue('C2', '=SUM(C3:C6)');
        $sheet->setCellValue('A7', 'Wydatki');
        $row = 8;

        foreach ($expenses as $label => $amount) {
            $sheet->setCellValue('B'.$row, $label);
            $sheet->setCellValue('C'.$row, $amount);
            $sheet->setCellValue('D'.$row, $daily !== null && $label === 'Codzienne wydatki' ? '=SUM(O13:O16)' : $amount);
            $row++;
        }

        $first = $split ? 5 : 4;
        $janaName = $split ? 'J' : 'I';
        [$sebastianPlan, $sebastianSpent, $janaPlan, $janaSpent, $homeSpent] = $split ? ['H', 'I', 'K', 'L', 'L'] : ['H', 'H', 'J', 'J', 'J'];

        $sheet->setCellValue('G2', 'Wydatki osobiste');
        $sheet->setCellValue('G3', 'Sebastian');
        $sheet->setCellValue($janaName.'3', 'Jana');

        if ($split) {
            $sheet->fromArray(['plan:', 'wydatek:'], null, 'H4');
            $sheet->fromArray(['plan:', 'wydatek:'], null, 'K4');
        }

        self::writeNamedAmounts($sheet, $sebastian, $first, 'G', $sebastianSpent, $split ? $sebastianPlan : null);
        self::writeNamedAmounts($sheet, $jana, $first, $janaName, $janaSpent, $split ? $janaPlan : null);
        $sheet->setCellValue('G16', 'Cele do odłożenia');
        $sheet->setCellValue('G17', 'Fundusz spłaty kredytu');
        $sheet->setCellValue($homeSpent.'17', 200);
        $sheet->setCellValue('G22', 'Dom i akcesoria');
        self::writeNamedAmounts($sheet, $home, 23, 'G', $homeSpent);

        $personal = 'SUM('.$sebastianSpent.$first.':'.$sebastianSpent.($first + 8).')+SUM('.$janaSpent.$first.':'.$janaSpent.($first + 8).')';
        $sheet->setCellValue('B'.$row, 'Osobiste');
        $sheet->setCellValue('D'.$row++, '='.$personal);
        $sheet->setCellValue('B'.$row, 'Akcesoria do domu');
        $sheet->setCellValue('D'.$row++, '=SUM('.$homeSpent.'23:'.$homeSpent.'26)');
        $sheet->setCellValue('B'.$row, 'Cele');
        $sheet->setCellValue('D'.$row++, 200);
        $sheet->setCellValue('B'.$row, 'Oszczędności (10%)');
        $sheet->setCellValue('D'.$row++, 100);
        $sheet->setCellValue('B'.$row, 'Pozostałe');

        if ($daily !== null) {
            $sheet->setCellValue('M12', 'Codzienne wydatki');
            $dailyRow = 13;

            foreach ($daily as $label => $amount) {
                $sheet->setCellValue('M'.$dailyRow, $label);
                $sheet->setCellValue('O'.$dailyRow++, $amount);
            }
        }

        $expenseTotal = array_sum(array_map(self::grosze(...), $expenses))
            + self::sumAmounts($sebastian) + self::sumAmounts($jana) + self::sumAmounts($home);

        return [array_sum(array_map(self::grosze(...), $income)), $expenseTotal];
    }

    private static function writeActualRow(Worksheet $sheet, int $row, string $label, float $amount): void
    {
        $sheet->setCellValue('A'.$row, $label);
        $sheet->setCellValue('B'.$row, $amount);
        $sheet->setCellValue('C'.$row, $amount);
    }

    /**
     * A list row of variants A and B: the list sum in B; from June also an actual in C that repeats it.
     */
    private static function writeListRow(Worksheet $sheet, int $row, string $label, string $sum, bool $withActual): void
    {
        $sheet->setCellValue('A'.$row, $label);
        $sheet->setCellValue('B'.$row, $sum);

        if ($withActual) {
            $sheet->setCellValue('C'.$row, '=B'.$row);
        }
    }

    /**
     * @param  list<array{0: string, 1: float}>  $items
     */
    private static function writeNamedAmounts(Worksheet $sheet, array $items, int $start, string $nameColumn, string $amountColumn, ?string $planColumn = null): void
    {
        foreach ($items as $index => [$name, $amount]) {
            $sheet->setCellValue($nameColumn.($start + $index), $name);
            $sheet->setCellValue($amountColumn.($start + $index), $amount);

            if ($planColumn !== null) {
                $sheet->setCellValue($planColumn.($start + $index), $amount + 1);
            }
        }
    }

    /**
     * @param  list<array{0: string, 1: float}>  $items
     */
    private static function sumAmounts(array $items): int
    {
        return array_sum(array_map(fn (array $item): int => self::grosze($item[1]), $items));
    }

    private static function richText(string $text): RichText
    {
        $richText = new RichText;
        $richText->createText($text);

        return $richText;
    }

    private static function grosze(mixed $amount): int
    {
        return is_numeric($amount) ? (int) round($amount * 100) : 0;
    }
}

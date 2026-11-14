<?php

use App\Support\SheetLoad\BudgetSheet;
use App\Support\SheetLoad\BudgetSheetReader;
use App\Support\SheetLoad\MonthSheetReader;
use App\Support\SheetLoad\SheetReadException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\Support\SheetWorkbookFactory;

/**
 * @param  array<string, mixed>  $options
 */
function readSheet(array $options = []): BudgetSheet
{
    $sheet = null;

    SheetWorkbookFactory::with($options, function (string $path) use (&$sheet): void {
        $sheet = (new BudgetSheetReader)->read($path);
    });

    return $sheet;
}

/**
 * @param  array<string, mixed>  $options
 */
function readSheetFailure(array $options): SheetReadException
{
    $failure = null;

    SheetWorkbookFactory::with($options, function (string $path) use (&$failure): void {
        try {
            (new BudgetSheetReader)->read($path);
        } catch (SheetReadException $e) {
            $failure = $e;
        }
    });

    expect($failure)->toBeInstanceOf(SheetReadException::class);

    return $failure;
}

test('reads the 2023-2025 layout: year, twelve months, analysis totals and addresses', function () {
    $sheet = readSheet();
    $january = $sheet->months[1];

    expect($sheet->year)->toBe(2025)
        ->and(array_keys($sheet->months))->toBe(range(1, 12))
        ->and($january->name)->toBe('styczeń')
        ->and($sheet->analysisIncome->address)->toBe('Analiza!B16')
        ->and($sheet->analysisIncome->amountMinorUnits)->toBe(12 * 780_500)
        ->and($january->expenses[0]->label)->toBe('rata kredytu')
        ->and($january->expenses[0]->amountMinorUnits)->toBe(180_050)
        ->and($january->expenses[0]->address)->toBe('styczeń!D3')
        ->and($january->irregularTotal->amountMinorUnits)->toBe(30_000)
        ->and($january->dailyBreakdown)->toBeNull()
        ->and($sheet->warnings())->toBe([]);
});

test('reads the stored value of a formula cell, as the real sheets sum the daily entries with =a+b', function () {
    $sheet = readSheet(['mutate' => function (Spreadsheet $spreadsheet): void {
        $january = $spreadsheet->getSheetByName('styczeń');
        $january->setCellValue('D3', '=1000.50+800');
        $january->setCellValue('I3', '=2500+1500');
    }]);
    $january = $sheet->months[1];

    expect($january->expenses[0]->amountMinorUnits)->toBe(180_050)
        ->and($january->income[0]->amountMinorUnits)->toBe(400_000)
        ->and($sheet->warnings())->toBe([]);
});

test('reading a month walks the sheet for its last row once, as the scan touches every cell of a real 1 MB sheet', function () {
    $scanning = new class extends Worksheet
    {
        public int $scans = 0;

        public function getHighestDataRow(?string $column = null): int
        {
            $this->scans++;

            return parent::getHighestDataRow($column);
        }
    };

    SheetWorkbookFactory::with(['layout' => '2022', 'year' => 2022], function (string $path) use ($scanning): void {
        $source = IOFactory::load($path)->getSheetByName('styczeń');

        foreach ($source->getCellCollection()->getCoordinates() as $coordinate) {
            $scanning->setCellValue($coordinate, $source->getCell($coordinate)->getValue());
        }
    });

    $january = (new MonthSheetReader)->read($scanning, 1);

    expect($january->dailyBreakdown)->toHaveCount(4)
        ->and($scanning->scans)->toBe(1);
});

test('normalises labels: trailing stars, case and work equals praca', function () {
    $january = readSheet()->months[1];
    $labels = array_map(fn ($e) => $e->label, $january->expenses);
    $income = array_map(fn ($e) => $e->label, $january->income);

    expect($labels)->toContain('prąd')->toContain('oszczędności długoterminowe')
        ->and($january->expenses[2]->rawLabel)->toBe('Prąd**')
        ->and($income)->toContain('praca: sebastian')->toContain('praca: jana');
});

test('allows empty expense rows and reads 7 income sources with the savings row last', function () {
    $january = readSheet()->months[1];

    expect($january->income)->toHaveCount(7)
        ->and($january->income[6]->label)->toBe('oszczędności')
        ->and($january->expenses)->toHaveCount(8);
});

test('reads the 2022 layout: 8 income sources, empty plan column, daily breakdown, total one row lower', function () {
    $sheet = readSheet(['layout' => '2022', 'year' => 2022]);
    $january = $sheet->months[1];

    expect($sheet->year)->toBe(2022)
        ->and($january->income)->toHaveCount(8)
        ->and($january->income[6]->label)->toBe('przychody nieregularne')
        ->and($january->irregularTotal->address)->toBe('styczeń!D31')
        ->and($january->balance->address)->toBe('styczeń!D32')
        ->and($january->dailyBreakdown)->toHaveCount(4)
        ->and($january->dailyBreakdown[2]->label)->toBe('higiena i kosmetyki')
        ->and($january->dailyBreakdown[2]->amountMinorUnits)->toBe(15_000)
        ->and($sheet->warnings())->toBe([]);
});

test('finds the lists when January is shifted by one or two rows', function (int $offset, string $layout) {
    $options = ['januaryOffset' => $offset, 'layout' => $layout];
    $january = readSheet($options)->months[1];
    $february = readSheet($options)->months[2];

    expect($january->irregularList)->toHaveCount(3)
        ->and($january->medicalList)->toHaveCount(2)
        ->and($january->examsList)->toHaveCount(1)
        ->and($january->examsList[0]->name)->toBe('Morfologia')
        ->and($january->warnings)->toBe([])
        ->and($february->examsList[0]->address)->not->toBe($january->examsList[0]->address);
})->with([[1, '2023'], [2, '2022']]);

test('skips a dashes row at the start of a list and dashes anywhere in K or M', function () {
    $january = readSheet(['months' => [1 => ['irregular' => [
        ['', '-------', '-------'],
        ['Ubrania', 'Kurtka', 250.00],
        ['Dom', '------', 10.00],
        ['Dom', 'Lampa', '-------'],
        ['Dom', 'Żarówki', 30.50],
    ], 'irregularTotal' => 280.50]]])->months[1];

    expect($january->irregularList)->toHaveCount(2)
        ->and(array_map(fn ($i) => $i->name, $january->irregularList))->toBe(['Kurtka', 'Żarówki'])
        ->and($january->warnings)->toBe([]);
});

test('category dash and empty mean no category; an item may have no name; amounts may be negative', function () {
    $january = readSheet(['months' => [1 => ['irregular' => [
        ['-', 'Pierwsza', 10.00],
        ['', 'Druga', 20.00],
        ['Ubrania', null, 30.00],
        ['Dom', 'Zwrot', -129.00],
    ], 'irregularTotal' => -69.00]]])->months[1];
    $items = $january->irregularList;

    expect($items)->toHaveCount(4)
        ->and($items[0]->category)->toBeNull()
        ->and($items[1]->category)->toBeNull()
        ->and($items[2]->category)->toBe('ubrania')
        ->and($items[2]->name)->toBeNull()
        ->and($items[3]->amountMinorUnits)->toBe(-12_900)
        ->and($january->warnings)->toBe([]);
});

test('a gap in the exams list stops it and shows as a warning, not a refusal', function () {
    $january = readSheet(['months' => [1 => ['exams' => [
        ['', 'Morfologia', 30.00],
        null,
        ['', 'Mocz', 20.00],
    ]]]])->months[1];

    expect($january->examsList)->toHaveCount(1)
        ->and($january->warnings)->toHaveCount(1)
        ->and($january->warnings[0]->address)->toBe('styczeń!D9');
});

test('warns when the irregular block differs from its list, net of the fund withdrawal', function () {
    $balanced = readSheet(['months' => [1 => ['fund' => 50.00]]])->months[1];
    $off = readSheet(['months' => [1 => ['irregularTotal' => 424.37]]])->months[1];

    expect($balanced->fundWithdrawalMinorUnits)->toBe(5_000)
        ->and($balanced->irregularTotal->amountMinorUnits)->toBe(25_000)
        ->and($balanced->warnings)->toBe([])
        ->and($off->warnings)->toHaveCount(1)
        ->and($off->warnings[0]->address)->toBe('styczeń!D30');
});

test('warns when the 2022 daily breakdown differs from the Codzienne row', function () {
    $january = readSheet(['layout' => '2022', 'months' => [1 => ['daily' => ['Jedzenie' => 100.00]]]])->months[1];

    expect($january->warnings)->toHaveCount(1)
        ->and($january->warnings[0]->address)->toBe('styczeń!D7');
});

test('warns when the month balance is not zero', function () {
    $january = readSheet(['months' => [1 => ['balance' => 86.58]]])->months[1];

    expect($january->warnings)->toHaveCount(1)
        ->and($january->warnings[0]->address)->toBe('styczeń!D31')
        ->and($january->balance->amountMinorUnits)->toBe(8_658);
});

test('refuses a missing Opieka zdrowotna label with the sheet and range', function () {
    $e = readSheetFailure(['mutate' => function (Spreadsheet $spreadsheet): void {
        $sheet = $spreadsheet->getSheetByName('maj');
        $sheet->setCellValue('J21', 'inne');
    }]);

    expect($e->address)->toStartWith('maj!J')
        ->and($e->getMessage())->toContain('opieka zdrowotna');
});

test('refuses text other than dashes in an amount cell, naming the cell', function () {
    $e = readSheetFailure(['mutate' => function (Spreadsheet $spreadsheet): void {
        $spreadsheet->getSheetByName('luty')->setCellValue('D4', 'dużo');
    }]);

    expect($e->address)->toBe('luty!D4')
        ->and($e->getMessage())->toContain('dużo');
});

test('refuses text in a list amount cell', function () {
    $e = readSheetFailure(['mutate' => function (Spreadsheet $spreadsheet): void {
        $spreadsheet->getSheetByName('luty')->setCellValue('M4', 'x');
    }]);

    expect($e->address)->toBe('luty!M4');
});

test('refuses a workbook without a month sheet', function () {
    $e = readSheetFailure(['mutate' => function (Spreadsheet $spreadsheet): void {
        $spreadsheet->removeSheetByIndex($spreadsheet->getIndex($spreadsheet->getSheetByName('listopad')));
    }]);

    expect($e->address)->toBe('listopad');
});

test('refuses a missing file', function () {
    expect(fn () => (new BudgetSheetReader)->read('/nonexistent/sheet.xlsx'))->toThrow(SheetReadException::class);
});

test('reads the 2020-2021 layout: year from grudzień!A1, income from H, lists from I to L, the treatment list without a name header', function () {
    $sheet = readSheet(['layout' => '2020']);
    $january = $sheet->months[1];

    expect($sheet->year)->toBe(2020)
        ->and($january->income)->toHaveCount(6)
        ->and($january->income[0]->amountMinorUnits)->toBe(400_000)
        ->and($january->income[0]->address)->toBe('styczeń!H3')
        ->and($january->income[4]->label)->toBe('przychody rozłożone')
        ->and($january->income[5]->label)->toBe('oszczędności')
        ->and(array_map(fn ($e) => $e->label, $january->expenses))->toContain('lekarz - dentysta')->toContain('fundusz wydatków celowych')
        ->and($january->irregularList)->toHaveCount(3)
        ->and($january->irregularList[0]->category)->toBe('ubrania')
        ->and($january->irregularList[0]->address)->toBe('styczeń!L4')
        ->and($january->medicalList)->toHaveCount(2)
        ->and($january->medicalList[0]->name)->toBe('Wizyta')
        ->and($january->examsList)->toHaveCount(1)
        ->and($january->examsList[0]->name)->toBe('Morfologia')
        ->and($sheet->warnings())->toBe([]);
});

test('a 2020 month without a medical block ends the irregular list at the first blank row and leaves the lower lists out', function () {
    $sheet = readSheet(['layout' => '2020', 'withoutMedical' => [1, 2]]);
    $january = $sheet->months[1];

    expect($january->irregularList)->toHaveCount(3)
        ->and($january->medicalList)->toBe([])
        ->and($january->examsList)->toBe([])
        ->and($sheet->months[3]->medicalList)->toHaveCount(2)
        ->and($sheet->warnings())->toBe([]);
});

test('a 2020 month without a medical block tolerates one blank row under the list header, as marzec!I4 does', function () {
    $sheet = readSheet(['layout' => '2020', 'withoutMedical' => [1], 'months' => [1 => [
        'irregular' => [null, ['Ubrania', 'Kurtka', 250.00], ['Dom', 'Żarówki', 30.50]],
        'irregularTotal' => 280.50,
    ]]]);

    expect($sheet->months[1]->irregularList)->toHaveCount(2)
        ->and($sheet->months[1]->irregularList[0]->name)->toBe('Kurtka')
        ->and($sheet->warnings())->toBe([]);
});

test('the daily breakdown ends at Na tydzień', function () {
    $january = readSheet(['layout' => '2020'])->months[1];

    expect($january->dailyBreakdown)->toHaveCount(4)
        ->and($january->dailyBreakdown[3]->label)->toBe('pieluchy');
});

test('refuses a workbook without Ustawienia that is not the 2020-2021 layout, naming the cell', function () {
    $e = readSheetFailure(['layout' => '2020', 'mutate' => function (Spreadsheet $spreadsheet): void {
        $spreadsheet->getSheetByName('styczeń')->setCellValue('I3', 'inne');
    }]);

    expect($e->address)->toBe('Ustawienia')
        ->and($e->getMessage())->toContain('styczeń!I3');
});

test('refuses grudzień!A1 that is not the first of December', function (mixed $value) {
    $e = readSheetFailure(['layout' => '2020', 'mutate' => function (Spreadsheet $spreadsheet) use ($value): void {
        $spreadsheet->getSheetByName('grudzień')->setCellValue('A1', $value);
    }]);

    expect($e->address)->toBe('grudzień!A1');
})->with([
    'second of December' => [44167],
    'text' => ['grudzień'],
    'first of November' => [44136],
]);

test('warns when the Lekarz - dentysta row differs from its list', function () {
    $sheet = readSheet(['layout' => '2020', 'months' => [5 => ['expenses' => ['Lekarz - dentysta' => 999.00]]]]);

    $warnings = $sheet->months[5]->warnings;

    expect($warnings)->toHaveCount(1)
        ->and($warnings[0]->message)->toContain('lista leczenia');
});

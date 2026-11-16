<?php

use PhpOffice\PhpSpreadsheet\Calculation\Calculation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;

// Proof of the 2019 workbook fix (context/changes/sheet-load-2019/workbook-fix.md). Run from the repo root on the host:
// php context/changes/sheet-load-2019/workbook-fix/verify.php "$PWD" storage/app/private/Budżet_2019.przed-korekta.xlsx storage/app/private/Budżet_2019.xlsx
// usage: verify.php <repo> <original> <fixed>
require $argv[1].'/vendor/autoload.php';
[$_, $repo, $o, $n] = $argv;
$fail = 0;
// 1. zip entries
$zo = new ZipArchive;
$zo->open($o);
$zn = new ZipArchive;
$zn->open($n);
$diffEntries = [];
for ($i = 0; $i < $zo->numFiles; $i++) {
    $name = $zo->getNameIndex($i);
    if ($zo->getFromName($name) !== $zn->getFromName($name)) {
        $diffEntries[] = $name;
    }
}
if ($zo->numFiles !== $zn->numFiles) {
    echo "entry count differs\n";
    $fail = 1;
}
echo 'changed entries: '.implode(', ', $diffEntries)."\n";
if ($diffEntries !== ['xl/workbook.xml', 'xl/worksheets/sheet4.xml', 'xl/worksheets/sheet15.xml'] && array_diff($diffEntries, ['xl/workbook.xml', 'xl/worksheets/sheet4.xml', 'xl/worksheets/sheet15.xml']) !== []) {
    echo "unexpected entries\n";
    $fail = 1;
}
// 2. cells
$load = fn ($p) => IOFactory::createReaderForFile($p)->load($p);
$so = $load($o);
$sn = $load($n);
$cells = function ($s) {
    $r = [];
    foreach ($s->getWorksheetIterator() as $ws) {
        foreach ($ws->getCellCollection()->getCoordinates() as $co) {
            $c = $ws->getCell($co);
            $v = $c->getValue();
            if ($v instanceof RichText) {
                $v = $v->getPlainText();
            } $r[$ws->getTitle().'!'.$co] = [$v, $c->isFormula() ? $c->getOldCalculatedValue() : null];
        }
    }

    return $r;
};
$co = $cells($so);
$cn = $cells($sn);
if (count($co) !== count($cn)) {
    echo 'cell count differs '.count($co).' vs '.count($cn)."\n";
    $fail = 1;
}
$expected = ['marzec_19!D1', 'marzec_19!B3', 'Podsumowanie!H3', 'Podsumowanie!H4', 'Podsumowanie!H5', 'Podsumowanie!I5', 'Podsumowanie!H6', 'Podsumowanie!H7', 'Podsumowanie!H8', 'Podsumowanie!H9', 'Podsumowanie!H10', 'Podsumowanie!H11', 'Podsumowanie!H12', 'Podsumowanie!H13', 'Podsumowanie!H16', 'Podsumowanie!I16'];
$diff = [];
foreach ($co as $k => $x) {
    if (($cn[$k] ?? null) !== $x) {
        $diff[] = $k;
    }
}
sort($diff);
$e = $expected;
sort($e);
echo 'differing cells: '.count($diff)."\n";
if ($diff !== $e) {
    echo 'UNEXPECTED: '.implode(',', array_diff($diff, $e)).' MISSING: '.implode(',', array_diff($e, $diff))."\n";
    $fail = 1;
}
// 3. stored new values = engine on fixed file
$calc = Calculation::getInstance($sn);
$calc->clearCalculationCache();
foreach ($expected as $k) {
    [$sh,$cell] = explode('!', $k);
    $c = $sn->getSheetByName($sh)->getCell($cell);
    $eng = $c->getCalculatedValue();
    $stored = $c->getOldCalculatedValue();
    $ok = abs($eng - $stored) < 1e-9;
    if (! $ok) {
        $fail = 1;
    }
    printf("%-20s %-55s stored=%s engine=%s %s\n", $k, $c->getValue(), $stored, $eng, $ok ? 'ok' : 'MISMATCH');
}
$h16 = round($sn->getSheetByName('Podsumowanie')->getCell('H16')->getOldCalculatedValue(), 2);
$i16 = round($sn->getSheetByName('Podsumowanie')->getCell('I16')->getOldCalculatedValue(), 2);
echo "H16=$h16 I16=$i16\n";
if ($h16 !== 67286.75 || $i16 !== 78039.29) {
    $fail = 1;
}
echo $fail ? "FAIL\n" : "PASS\n";
exit($fail);

<?php

use App\Support\CsvImport\ImportRowInvalidReason;

it('has a Polish sentence for every rejection code', function (): void {
    $operations = (require dirname(__DIR__, 2).'/lang/pl/ui.php')['operations'];

    foreach (ImportRowInvalidReason::cases() as $reason) {
        $key = 'import_invalid_reason_'.$reason->value;

        expect($operations[$key] ?? '')->not->toBe('', "Missing {$key} in lang/pl/ui.php");
    }
});

it('has a Polish sentence for every pair candidate kind', function (string $kind): void {
    $operations = (require dirname(__DIR__, 2).'/lang/pl/ui.php')['operations'];

    expect($operations['import_pair_reason_'.$kind] ?? '')->not->toBe('');
})->with(['financial_operation', 'own_transfer']);

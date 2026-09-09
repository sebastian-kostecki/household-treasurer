<?php

use App\Support\CsvImport\ImportPairCandidateDetector;

test('dates within inclusive three day window match', function (): void {
    $detector = app(ImportPairCandidateDetector::class);

    expect($detector->datesWithinInclusiveWindow('2026-03-15', '2026-03-15'))->toBeTrue();
    expect($detector->datesWithinInclusiveWindow('2026-03-15', '2026-03-18'))->toBeTrue();
    expect($detector->datesWithinInclusiveWindow('2026-03-15', '2026-03-12'))->toBeTrue();
    expect($detector->datesWithinInclusiveWindow('2026-03-15', '2026-03-19'))->toBeFalse();
});

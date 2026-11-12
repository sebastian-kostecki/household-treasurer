<?php

namespace App\Support\SheetLoad;

/**
 * One labelled amount of a month sheet: an expense item, an income source or a breakdown line.
 */
final readonly class SheetEntry
{
    public function __construct(
        public string $label,
        public string $rawLabel,
        public int $amountMinorUnits,
        public string $address,
    ) {}
}

<?php

namespace App\Support\SheetLoad;

/**
 * One row of a list in a month sheet (irregular, medical or exams); a missing category or name is null.
 */
final readonly class SheetListItem
{
    public function __construct(
        public ?string $category,
        public ?string $rawCategory,
        public ?string $name,
        public int $amountMinorUnits,
        public string $address,
    ) {}
}

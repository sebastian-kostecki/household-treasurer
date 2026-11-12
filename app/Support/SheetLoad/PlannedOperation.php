<?php

namespace App\Support\SheetLoad;

use App\Enums\FinancialOperationType;

/**
 * One Financial Operation the sheet load would write. `category` is a Category id or `SheetMapping::NEW_CATEGORY`;
 * a negative expense is a refund. The amount is in minor units.
 */
final readonly class PlannedOperation
{
    public function __construct(
        public string $date,
        public FinancialOperationType $type,
        public int|string $category,
        public int $amountMinorUnits,
        public string $description,
        public string $address,
    ) {}
}

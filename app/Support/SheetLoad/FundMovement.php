<?php

namespace App\Support\SheetLoad;

use App\Enums\FinancialOperationType;
use App\Support\MinorUnits;

/**
 * A cell mapped to `SheetMapping::FUND`: a payment into or a refund from a purpose fund. It is not an operation, but
 * "Analiza" counts it as an expense (payment) or an income (refund spread over the month), so it joins the
 * reconciliation and the dry-run print.
 */
final readonly class FundMovement
{
    public function __construct(
        public string $address,
        public string $label,
        public int $amountMinorUnits,
        public FinancialOperationType $type,
    ) {}

    public function __toString(): string
    {
        return $this->address.': '.$this->label.' '.MinorUnits::toMajorDisplay($this->amountMinorUnits).' zł';
    }
}

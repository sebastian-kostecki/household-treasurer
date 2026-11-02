<?php

namespace App\Support;

use Carbon\CarbonInterface;

final class MonthPlanReading
{
    /**
     * @param  'proposal'|'approved'|'none'  $state
     * @param  array<int, int>  $amounts  category id => amount in minor units
     * @param  int  $setAsideMinorUnits  signed: positive is set aside, negative is a draw
     */
    public function __construct(
        public readonly string $state,
        public readonly ?CarbonInterface $approvedAt,
        public readonly array $amounts,
        public readonly int $setAsideMinorUnits,
    ) {}
}

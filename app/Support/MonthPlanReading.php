<?php

namespace App\Support;

use Carbon\CarbonInterface;

final class MonthPlanReading
{
    /**
     * @param  'proposal'|'approved'|'none'  $state
     * @param  array<int, int>  $amounts  category id => amount in minor units
     */
    public function __construct(
        public readonly string $state,
        public readonly ?CarbonInterface $approvedAt,
        public readonly array $amounts,
    ) {}
}

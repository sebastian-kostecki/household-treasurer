<?php

namespace App\Support;

use Carbon\CarbonInterface;

final class MonthPlanReading
{
    /**
     * @param  'proposal'|'approved'|'none'  $state
     * @param  array<int, int>  $amounts  category id => amount in minor units (current amount for an approved plan)
     * @param  array<int, int>  $approvedAmounts  category id => approved amount in minor units, empty unless the plan is approved
     */
    public function __construct(
        public readonly string $state,
        public readonly ?CarbonInterface $approvedAt,
        public readonly array $amounts,
        public readonly array $approvedAmounts = [],
    ) {}
}

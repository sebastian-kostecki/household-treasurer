<?php

namespace App\Support;

use Carbon\CarbonInterface;

final class MonthPlanReading
{
    /**
     * @param  'proposal'|'approved'|'none'  $state
     * @param  array<int, int>  $amounts  category id => amount in minor units (current amount for an approved plan)
     * @param  int  $setAsideMinorUnits  signed: positive is set aside, negative is a draw
     * @param  array<int, int>  $approvedAmounts  category id => approved amount in minor units, empty unless the plan is approved
     * @param  int|null  $approvedSetAsideMinorUnits  signed, as approved; null unless the plan is approved
     */
    public function __construct(
        public readonly string $state,
        public readonly ?CarbonInterface $approvedAt,
        public readonly array $amounts,
        public readonly int $setAsideMinorUnits,
        public readonly array $approvedAmounts = [],
        public readonly ?int $approvedSetAsideMinorUnits = null,
    ) {}
}

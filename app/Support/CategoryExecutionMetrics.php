<?php

namespace App\Support;

class CategoryExecutionMetrics
{
    public static function remainingMinorUnits(int $currentPlanMinorUnits, int $executionMinorUnits): int
    {
        return $currentPlanMinorUnits - $executionMinorUnits;
    }

    public static function percentComplete(int $currentPlanMinorUnits, int $executionMinorUnits): ?int
    {
        if ($currentPlanMinorUnits === 0) {
            return null;
        }

        return (int) round($executionMinorUnits / $currentPlanMinorUnits * 100);
    }

    public static function isOverLimit(int $currentPlanMinorUnits, int $executionMinorUnits): bool
    {
        return $currentPlanMinorUnits - $executionMinorUnits < 0;
    }
}

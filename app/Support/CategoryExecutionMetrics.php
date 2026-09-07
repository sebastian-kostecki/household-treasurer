<?php

namespace App\Support;

class CategoryExecutionMetrics
{
    public static function remainingGrosze(int $currentPlanGrosze, int $executionGrosze): int
    {
        return $currentPlanGrosze - $executionGrosze;
    }

    public static function percentComplete(int $currentPlanGrosze, int $executionGrosze): ?int
    {
        if ($currentPlanGrosze === 0) {
            return null;
        }

        return (int) round($executionGrosze / $currentPlanGrosze * 100);
    }

    public static function isOverLimit(int $currentPlanGrosze, int $executionGrosze): bool
    {
        return $currentPlanGrosze - $executionGrosze < 0;
    }
}

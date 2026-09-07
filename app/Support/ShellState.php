<?php

namespace App\Support;

class ShellState
{
    /**
     * @return array{budgetYear: null, firstRun: array{hasAccount: bool, hasBudget: bool, hasCategories: bool, executionStarted: bool}}
     */
    public static function forRequest(): array
    {
        return [
            'budgetYear' => null,
            'firstRun' => [
                'hasAccount' => false,
                'hasBudget' => false,
                'hasCategories' => false,
                'executionStarted' => false,
            ],
        ];
    }
}

<?php

namespace App\Enums;

enum ImportBudgetYearState: string
{
    case Execution = 'execution';
    case Draft = 'draft';
    case Missing = 'missing';
}

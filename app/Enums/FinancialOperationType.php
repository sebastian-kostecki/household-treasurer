<?php

namespace App\Enums;

enum FinancialOperationType: string
{
    case Income = 'income';
    case Expense = 'expense';
}
